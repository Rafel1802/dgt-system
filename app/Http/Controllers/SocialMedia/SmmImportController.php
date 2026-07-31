<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use App\Models\SocialMediaClass;
use App\Models\Label;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SmmImportController extends Controller
{
    /** Standard import columns */
    private const HEADERS = [
        'Cluster', 'Team', 'Work Task / Content Type', 'Title', 'Description', 'Attachement',
        'Assigned To', 'Assigned By', 'Content Public Date', 'Deadline Date', 'Deadline Time', 'Weeks'
    ];

    public function template(Board $board): Response
    {
        $headers = implode(',', self::HEADERS);
        $sample1 = 'ImpossibleMachinery,Graphic Team,Poster Design,TYPH-1702 Ebay Content,Desing 3 posters,https://example.com,Pich,Srey Pich,6-August-2026,29-July-2026,12:00';
        $sample2 = 'MachineryAsia,Video Team,Short Reel,TYPH-1703,Create short reel,https://example.com/video,Lyhour,Srey Pich,5-August-2026,30-July-2026,15:00';
        $csv = implode("\n", [$headers, $sample1, $sample2]) . "\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="smm-import-template.csv"',
        ]);
    }

    public function preview(Request $request, Board $board): JsonResponse
    {
        $request->validate([
            'file'       => ['nullable', 'file', 'mimes:csv,txt', 'max:20480'],
            'sheets_url' => ['nullable', 'url'],
            'worksheet_name' => ['nullable', 'string', 'max:100'],
        ]);

        if (!$request->hasFile('file') && !$request->filled('sheets_url')) {
            return response()->json(['error' => 'Please provide a CSV file or a Google Sheets URL.'], 422);
        }

        $worksheetName = trim((string)$request->input('worksheet_name'));

        if ($request->hasFile('file')) {
            $csvContent = file_get_contents($request->file('file')->getRealPath());
            if (empty($worksheetName)) {
                $worksheetName = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
            }
        } else {
            $csvContent = $this->fetchGoogleSheetsCsv($request->sheets_url);
            if ($csvContent === null) {
                return response()->json(['error' => 'Could not fetch the Google Sheet. Ensure it is shared as viewer.'], 422);
            }
            if (empty($worksheetName)) {
                $worksheetName = 'Imported Sheet';
            }
        }

        $rows = $this->parseCsv($csvContent);
        if (empty($rows)) {
            return response()->json(['error' => 'The file appears to be empty or could not be parsed.'], 422);
        }

        $headerRow = array_map('trim', $rows[0]);
        $colMap = $this->buildColumnMap($headerRow);

        if (!isset($colMap['Work Task / Content Type'])) {
            // fallback attempt if it's named something else
            if (isset($colMap['Content Type'])) $colMap['Work Task / Content Type'] = $colMap['Content Type'];
        }

        $dataRows = array_slice($rows, 1);
        $boardLists = $board->activeLists()->pluck('id', 'name')->all();
        $firstListId = $board->activeLists()->orderBy('position')->value('id');

        $allUsers = User::select('id', 'name', 'username')->get();
        $userLookup = [];
        foreach ($allUsers as $u) {
            $userLookup[strtolower(trim($u->name))] = ['id' => $u->id, 'name' => $u->name];
            $userLookup[strtolower(trim($u->username))] = ['id' => $u->id, 'name' => $u->name];
            $coreName = preg_replace('/^(Mr\.|Ms\.|Mrs\.)\s*/i', '', $u->name);
            $coreName = preg_replace('/\s*\(.*?\)/', '', $coreName);
            $coreName = strtolower(trim($coreName));
            if ($coreName) {
                $userLookup[$coreName] = ['id' => $u->id, 'name' => $u->name];
            }
        }

        $preview = [];
        $totalValid = 0;
        $totalInvalid = 0;
        $totalWarnings = 0;

        foreach ($dataRows as $idx => $rawRow) {
            // Skip fully empty rows
            if (count(array_filter($rawRow, fn($c) => trim($c) !== '')) === 0) continue;

            $row = $this->mapRow($rawRow, $colMap);
            
            // Skip group headers (e.g. SREYPICH'S CLUSTERS)
            // If Cluster has text but everything else is empty, it's likely a header
            if (!empty($row['Cluster']) && empty($row['Work Task / Content Type']) && empty($row['Team']) && empty($row['Assigned To'])) {
                continue;
            }

            if (empty($row['Work Task / Content Type']) && empty($row['Cluster'])) {
                continue; // Skip formatting rows
            }

            $errors = [];
            $warnings = [];
            
            // Dates Parsing
            $pubDate = $this->parseFlexibleDate($row['Content Public Date'] ?? '');
            
            $deadlineDateStr = trim($row['Deadline Date'] ?? '');
            $deadlineTimeStr = trim($row['Deadline Time'] ?? '');
            $deadline = null;
            if ($deadlineDateStr) {
                $deadline = $this->parseFlexibleDate($deadlineDateStr . ' ' . $deadlineTimeStr);
            }
            
            $startDate = $this->parseFlexibleDate($row['Start Date'] ?? '');

            // Team Label extraction (Keep exact string from spreadsheet to match existing labels)
            $teamLabel = trim($row['Team'] ?? '');
            if (strtolower($teamLabel) === 'none') $teamLabel = '';
            
            // Title
            $cluster = trim($row['Cluster'] ?? '');
            $contentType = trim($row['Work Task / Content Type'] ?? '');
            $rawTitle = trim($row['Title'] ?? '');
            
            if (empty($rawTitle) && empty($contentType)) continue;
            
            if (empty($rawTitle)) {
                $title = $contentType;
            } elseif (stripos($rawTitle, $contentType) === false) {
                $title = $rawTitle . ' - ' . $contentType;
            } else {
                $title = $rawTitle;
            }

            // Description and Attachment
            $desc = trim($row['Description'] ?? '');
            $attachment = trim($row['Attachement'] ?? '');

            // Determine Week List
            $weekNumber = '1';
            $weeksCol = $row['Weeks'] ?? '';
            if (!empty($weeksCol) && preg_match('/Week\s*(\d)/i', $weeksCol, $matches)) {
                $weekNumber = $matches[1];
            } elseif (preg_match('/Week\s*(\d)/i', $worksheetName, $matches)) {
                $weekNumber = $matches[1];
            }
            $targetWeek = "Week " . $weekNumber;
            
            $listId = $firstListId;
            $listName = 'First list';
            foreach ($boardLists as $name => $id) {
                if (strcasecmp(trim($name), $targetWeek) === 0) {
                    $listId = $id;
                    $listName = $name;
                    break;
                }
            }

            $isValid = empty($errors);
            if ($isValid) $totalValid++;
            else $totalInvalid++;

            $assignTo = $this->resolveMember($row['Assigned To'] ?? '', $userLookup);
            $assignBy = $this->resolveMember($row['Assigned By'] ?? '', $userLookup);

            if ($assignTo['warning']) {
                $warnings[] = $assignTo['warning'];
                $totalWarnings++;
            }
            if ($assignBy['warning']) {
                $warnings[] = $assignBy['warning'];
                $totalWarnings++;
            }

            $preview[] = [
                'row' => $idx + 2,
                'title' => $title,
                'smm_cluster_label' => $cluster,
                'smm_class_label' => $contentType,
                'smm_team_label' => $teamLabel,
                'description' => $desc,
                'attachment' => $attachment,
                'content_public_date' => $pubDate,
                'start_date' => $startDate,
                'deadline' => $deadline,
                'due_time' => $deadlineTimeStr,
                'assign_by_raw' => trim($row['Assigned By'] ?? ''),
                'assign_to_raw' => trim($row['Assigned To'] ?? ''),
                'assigned_name' => $assignTo['resolved_name'] ?: trim($row['Assigned To'] ?? ''),
                'assigned_by_name' => $assignBy['resolved_name'] ?: trim($row['Assigned By'] ?? ''),
                'list_id' => $listId,
                'list_name' => $listName,
                'valid' => $isValid,
                'errors' => $errors,
                'warnings' => $warnings,
                'worksheet' => $worksheetName,
            ];
        }

        return response()->json([
            'total' => count($preview),
            'valid' => $totalValid,
            'invalid' => $totalInvalid,
            'warnings' => $totalWarnings,
            'rows' => $preview,
        ]);
    }

    public function confirm(Request $request, Board $board): JsonResponse
    {
        $request->validate([
            'rows' => ['required', 'array'],
        ]);

        $rows = $request->input('rows');
        $created = [];
        $updated = [];
        $skipped = 0;
        $skippedDuplicates = 0; // We update instead of skip now, but keep stat just in case
        $failed = 0;

        $importedKeys = [];
        
        // Ensure standard Social Media Classes exist if missing
        $existingClasses = SocialMediaClass::pluck('name')->map(fn($n) => strtolower($n))->toArray();

        // 1. Pre-load all users for Assign To & By matching
        $allUsers = User::select('id', 'name', 'username')->get();
        $userLookup = [];
        foreach ($allUsers as $u) {
            $userLookup[strtolower(trim($u->name))] = ['id' => $u->id, 'name' => $u->name];
            $userLookup[strtolower(trim($u->username))] = ['id' => $u->id, 'name' => $u->name];
            $coreName = preg_replace('/^(Mr\.|Ms\.|Mrs\.)\s*/i', '', $u->name);
            $coreName = preg_replace('/\s*\(.*?\)/', '', $coreName);
            $coreName = strtolower(trim($coreName));
            if ($coreName) {
                $userLookup[$coreName] = ['id' => $u->id, 'name' => $u->name];
            }
        }

            // 2. Pre-load existing cards for this board to update duplicates efficiently
            $existingCards = Card::where('board_id', $board->id)
                ->select('id', 'title', 'start_date', 'content_public_date', 'due_at', 'description', 'smm_class_label', 'smm_team_label', 'sync_group_id')
                ->get();
                
            $existingCardsMap = [];
            foreach ($existingCards as $ec) {
                $dateKey = $ec->start_date ?? $ec->content_public_date;
                $key = strtolower($ec->title) . '|' . $dateKey;
                $existingCardsMap[$key] = $ec;
            }

        // 3. Pre-load workspaces and their active boards for team distribution
        $workspaces = Workspace::with(['boards' => function ($query) {
            $query->where('is_archived', false)->orderBy('created_at', 'desc');
        }, 'boards.lists' => function ($query) {
            $query->where('is_archived', false)->orderBy('position');
        }])->get();

        $distributedCounts = [];

        foreach ($rows as $row) {
            if (empty($row['valid'])) {
                $skipped++;
                continue;
            }

            // Fix for duplicate testing within same payload
            $dateKey = $row['start_date'] ?: $row['content_public_date'];
            $compositeKey = md5(strtolower($row['worksheet'] . '|' . $row['title'] . '|' . $row['assign_to_raw'] . '|' . $dateKey));
            
            if (in_array($compositeKey, $importedKeys)) {
                $skippedDuplicates++;
                continue; // Skip within same file
            }
            $importedKeys[] = $compositeKey;

            // Auto-create class if it doesn't exist (Class = Cluster/Brand)
            $clusterName = $row['smm_cluster_label'] ?? '';
            $className = $row['smm_class_label'] ?? '';
            if (!empty($clusterName) && !in_array(strtolower($clusterName), $existingClasses)) {
                SocialMediaClass::create([
                    'name' => $clusterName,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                ]);
                $existingClasses[] = strtolower($clusterName);
            }

            // Find User ID with robust matching
            $assignToResult = $this->resolveMember($row['assign_to_raw'] ?? '', $userLookup);
            $assignByResult = $this->resolveMember($row['assign_by_raw'] ?? '', $userLookup);
            $assignToUserId = $assignToResult['id'];
            $assignByUserId = $assignByResult['id'];
            $createdById = $assignByUserId ?: auth()->id();

            // Check if card exists for updating from pre-loaded map
            $dateKey = $row['start_date'] ?: $row['content_public_date'];
            $lookupKey = strtolower($row['title']) . '|' . $dateKey;
            $existingCard = $existingCardsMap[$lookupKey] ?? null;

            if ($existingCard) {
                // Update
                Card::where('id', $existingCard->id)->update([
                    'description' => $row['description'],
                    'due_at' => $row['deadline'] ?: null,
                    'start_date' => $row['start_date'] ?: null,
                    'content_public_date' => $row['content_public_date'] ?: null,
                    'due_time' => $row['due_time'] ?? null,
                    'smm_class_label' => $className ?: null,
                    'smm_team_label' => $row['smm_team_label'] ?: null,
                    'smm_cluster_label' => $row['smm_cluster_label'] ?: null,
                    'created_by' => $createdById,
                ]);
                
                // Collect label IDs to sync
                $labelIds = [];
                if (!empty($row['smm_team_label'])) {
                    $teamLabel = Label::firstOrCreate(['name' => trim($row['smm_team_label']), 'workspace_id' => null, 'board_id' => null], ['color' => '#f43f5e']);
                    $labelIds[] = $teamLabel->id;
                }
                
                $smmLabel = Label::firstOrCreate(['name' => 'SMM', 'workspace_id' => null, 'board_id' => null], ['color' => '#10b981']);
                $labelIds[] = $smmLabel->id;
                
                $existingCard->labels()->syncWithoutDetaching($labelIds);

                
                if ($assignToUserId) {
                    $existingCard->assignees()->syncWithoutDetaching([$assignToUserId => ['assigned_at' => now()]]);
                }
                $updated[] = $existingCard;
                
                // Distribute/Sync updated card
                $this->distributeToTeamWorkspace($existingCard, $row['smm_team_label'] ?? null, $workspaces);

            } else {
                $position = Card::where('board_list_id', $row['list_id'])->max('position') + 1;
                $card = Card::create([
                    'board_id' => $board->id,
                    'board_list_id' => $row['list_id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'smm_class_label' => $className ?: null,
                    'smm_team_label' => $row['smm_team_label'] ?: null,
                    'smm_cluster_label' => $row['smm_cluster_label'] ?: null,
                    'start_date' => $row['start_date'] ?: null,
                    'content_public_date' => $row['content_public_date'] ?: null,
                    'due_at' => $row['deadline'] ?: null,
                    'due_time' => $row['due_time'] ?? null,
                    'status' => 'todo',
                    'position' => $position,
                    'created_by' => $createdById,
                ]);

                // Collect label IDs to sync
                $labelIds = [];
                if (!empty($row['smm_team_label'])) {
                    $teamLabel = Label::firstOrCreate(['name' => trim($row['smm_team_label']), 'workspace_id' => null, 'board_id' => null], ['color' => '#f43f5e']);
                    $labelIds[] = $teamLabel->id;
                }
                
                $smmLabel = Label::firstOrCreate(['name' => 'SMM', 'workspace_id' => null, 'board_id' => null], ['color' => '#10b981']);
                $labelIds[] = $smmLabel->id;
                
                $card->labels()->sync($labelIds);

                if ($assignToUserId) {
                    $card->assignees()->sync([$assignToUserId => ['assigned_at' => now()]]);
                }
                
                $created[] = $card;
                
                // Distribute/Sync created card
                $teamBoard = $this->distributeToTeamWorkspace($card, $row['smm_team_label'] ?? null, $workspaces);
                if ($teamBoard) {
                    if (!isset($distributedCounts[$teamBoard->id])) {
                        $distributedCounts[$teamBoard->id] = ['board' => $teamBoard, 'count' => 0];
                    }
                    $distributedCounts[$teamBoard->id]['count']++;
                }
            }

            // Handle Attachment Link
            if (!empty($row['attachment'])) {
                $attachmentUrl = $row['attachment'];
                if (filter_var($attachmentUrl, FILTER_VALIDATE_URL)) {
                    $cardId = $existingCard ? $existingCard->id : $card->id;
                    \App\Models\CardFile::firstOrCreate([
                        'card_id' => $cardId,
                        'disk' => 'url',
                        'path' => $attachmentUrl,
                    ], [
                        'uploaded_by' => $createdById,
                        'original_name' => $attachmentUrl,
                        'stored_name' => '',
                    ]);
                }
            }
        }

        // Generate Import Log Message
        $logMessage = "Imported " . count($created) . " cards. Updated " . count($updated) . " cards. Skipped $skipped duplicates/invalid.";
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'import',
            'model_type' => 'App\\Models\\Board',
            'model_id' => $board->id,
            'details' => $logMessage
        ]);

        if (count($created) > 0) {
            $actor = auth()->user();
            $smmMessage = $actor->name . " imported " . count($created) . " cards into SMM board";
            
            // 1. Notify SMM Board admins
            $smmAdmins = \App\Models\User::role(['admin-digital', 'social_qc', 'super-admin'])->get();
            foreach ($smmAdmins as $admin) {
                if ($admin->id !== $actor->id) {
                    $admin->notify(new \App\Notifications\GenericDatabaseNotification([
                        'actor_id'     => $actor->id,
                        'actor_name'   => $actor->name,
                        'actor_avatar' => $actor->avatar_url,
                        'module'       => 'digital',
                        'message'      => $smmMessage,
                        'link'         => route('boards.show', $board->slug)
                    ]));
                }
            }

            // 2. Notify team board members
            foreach ($distributedCounts as $data) {
                $teamBoard = $data['board'];
                $count = $data['count'];
                $teamMessage = $actor->name . " imported $count cards into Planning Board";
                foreach ($teamBoard->members as $member) {
                    if ($member->id !== $actor->id) {
                        $member->notify(new \App\Notifications\GenericDatabaseNotification([
                            'actor_id'     => $actor->id,
                            'actor_name'   => $actor->name,
                            'actor_avatar' => $actor->avatar_url,
                            'module'       => 'digital',
                            'message'      => $teamMessage,
                            'link'         => route('boards.show', $teamBoard->slug)
                        ]));
                    }
                }
            }
        }

        return response()->json([
            'created' => count($created),
            'updated' => count($updated),
            'skipped' => $skipped,
            'skipped_duplicates' => $skippedDuplicates,
            'failed' => $failed,
            'success' => true,
        ]);
    }
    
    /** Distribute card directly to the team workspace Planning Board using preloaded workspaces */
    private function distributeToTeamWorkspace(Card $card, ?string $teamLabel, $workspaces)
    {
        if (empty($teamLabel)) return null;

        $normalizedTeamLabel = str_replace(' ', '', strtolower($teamLabel));
        $targetWorkspace = null;
        foreach ($workspaces as $workspace) {
            $normalizedWorkspaceName = str_replace(' ', '', strtolower($workspace->name));
            if (strpos($normalizedWorkspaceName, $normalizedTeamLabel) !== false) {
                $targetWorkspace = $workspace;
                break;
            }
        }
        
        if (!$targetWorkspace || $targetWorkspace->boards->isEmpty()) return null;
        
        // Extract month/year from main board (e.g. "August 2026")
        $mainBoardName = $card->board->name ?? '';
        $monthYear = '';
        if (preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4}/i', $mainBoardName, $matches)) {
            $monthYear = $matches[0];
        }
        
        $teamBoard = $targetWorkspace->boards->first(function($board) use ($monthYear) {
            $isPlanning = stripos($board->name, 'Planning') !== false;
            if ($monthYear) {
                return $isPlanning && stripos($board->name, $monthYear) !== false;
            }
            return $isPlanning;
        });

        if (!$teamBoard) {
            $teamBoard = $targetWorkspace->boards->first(function($board) {
                return stripos($board->name, 'Planning') !== false;
            });
        }

        if (!$teamBoard) {
            $teamBoard = $targetWorkspace->boards->first(function($board) {
                return stripos($board->name, 'Workflow') === false;
            });
        }
        
        if (!$teamBoard) {
            $teamBoard = $targetWorkspace->boards->first();
        }
        
        if (!$teamBoard || $teamBoard->lists->isEmpty()) return null;
        
        $originalListName = $card->boardList->name ?? '';
        $teamList = $teamBoard->lists->first(function($list) use ($originalListName) {
            return strcasecmp(trim($list->name), trim($originalListName)) === 0;
        });

        if (!$teamList) {
            $teamList = $teamBoard->lists->first();
        }
        
        // Ensure sync_group_id exists
        if (!$card->sync_group_id) {
            Card::withoutEvents(function () use ($card) {
                $card->sync_group_id = \Illuminate\Support\Str::uuid();
                $card->save();
            });
        }
        
        // Quick check if already synced (still 1 query per card distributed, but better than 5 per card)
        $alreadySynced = Card::where('board_id', $teamBoard->id)->where('sync_group_id', $card->sync_group_id)->exists();
        if (!$alreadySynced) {
            $clone = $card->replicateRelationally($teamBoard->id, $teamList->id);
            $clone->update(['status' => 'todo']);
            return $teamBoard;
        }
        return null;
    }

    private function parseFlexibleDate($dateStr)
    {
        $dateStr = trim((string)$dateStr);
        if (empty($dateStr)) return null;

        try {
            // E.g. "3-August-2026", "Jul 31 2026 - 12:00 PM"
            $cleaned = preg_replace('/-?\s*(\d{1,2}:\d{2}\s*(?:AM|PM))/i', ' $1', $dateStr);
            return Carbon::parse($cleaned)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchGoogleSheetsCsv(string $url): ?string
    {
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            $fileId = $matches[1];
            
            // To support fetching specific sheet, check if gid is in the url
            $gidStr = '';
            if (preg_match('/[#&]gid=([0-9]+)/', $url, $gidMatches)) {
                $gidStr = '&gid=' . $gidMatches[1];
            }
            
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$fileId}/export?format=csv{$gidStr}";
            $response = Http::get($csvUrl);
            if ($response->successful()) {
                return $response->body();
            }
        }
        return null;
    }

    private function parseCsv(string $content): array
    {
        $rows = [];
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $rows[] = str_getcsv($line);
        }
        return $rows;
    }

    private function buildColumnMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $idx => $colName) {
            $cleanName = strtolower(trim($colName));
            if ($cleanName === 'attachement') $cleanName = 'attachment';
            if (str_contains($cleanName, 'work task')) $cleanName = 'work task / content type';
            if (str_contains($cleanName, 'content type')) $cleanName = 'work task / content type';
            
            // Map common aliases
            if ($cleanName === 'work task / content type') $map['Work Task / Content Type'] = $idx;
            elseif ($cleanName === 'cluster') $map['Cluster'] = $idx;
            elseif ($cleanName === 'team') $map['Team'] = $idx;
            elseif ($cleanName === 'title') $map['Title'] = $idx;
            elseif ($cleanName === 'description') $map['Description'] = $idx;
            elseif (str_contains($cleanName, 'attach')) $map['Attachement'] = $idx; // Map back to what mapRow expects
            elseif ($cleanName === 'assigned to' || $cleanName === 'assign to') $map['Assigned To'] = $idx;
            elseif ($cleanName === 'assigned by' || $cleanName === 'assign by') $map['Assigned By'] = $idx;
            elseif (str_contains($cleanName, 'content public')) $map['Content Public Date'] = $idx;
            elseif (str_contains($cleanName, 'deadline date')) $map['Deadline Date'] = $idx;
            elseif (str_contains($cleanName, 'deadline time')) $map['Deadline Time'] = $idx;
            elseif (str_contains($cleanName, 'start date')) $map['Start Date'] = $idx;
            else $map[$colName] = $idx;
        }
        return $map;
    }

    private function resolveMember($rawName, $userLookup)
    {
        $rawName = trim($rawName);
        if (empty($rawName) || in_array(strtolower($rawName), ['none', 'n/a', '-', 'blank', 'no member'])) {
            return ['id' => null, 'warning' => null, 'resolved_name' => ''];
        }

        $lowerRaw = strtolower($rawName);
        if (isset($userLookup[$lowerRaw])) {
            return ['id' => $userLookup[$lowerRaw]['id'], 'warning' => null, 'resolved_name' => $userLookup[$lowerRaw]['name']];
        }

        $core = preg_replace('/^(Mr\.|Ms\.|Mrs\.)\s*/i', '', $rawName);
        $core = preg_replace('/\s*\(.*?\)/', '', $core);
        $core = strtolower(trim($core));

        if (isset($userLookup[$core])) {
            return ['id' => $userLookup[$core]['id'], 'warning' => null, 'resolved_name' => $userLookup[$core]['name']];
        }

        return [
            'id' => null, 
            'warning' => "Member \"$rawName\" could not be matched. Card imported successfully; resolve assignment later.", 
            'resolved_name' => $rawName
        ];
    }

    private function mapRow(array $rawRow, array $colMap): array
    {
        $mapped = [];
        foreach ($colMap as $name => $idx) {
            $mapped[$name] = $rawRow[$idx] ?? '';
        }
        return $mapped;
    }
}
