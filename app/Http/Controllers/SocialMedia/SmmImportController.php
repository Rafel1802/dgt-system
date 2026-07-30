<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use App\Models\SocialMediaClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SmmImportController extends Controller
{
    /** Standard import columns */
    private const HEADERS = [
        'Cluster', 'Team', 'Work Task / Content Type', 'Assigned To', 'Assigned By', 
        'Content Public Date', 'Deadline Time & Date',
    ];

    public function template(Board $board): Response
    {
        $headers = implode(',', self::HEADERS);
        $sample1 = 'ImpossibleMachinery,Graphic Team,Poster Design,Pich,Srey Pich,3-August-2026,Jul 29 2026 - 12:00 PM';
        $sample2 = 'ImpossibleMachinery,Video Team,Short Reel,Lyhour,Srey Pich,5-August-2026,Jul 31 2026 - 12:00 PM';
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

        $preview = [];
        $totalValid = 0;
        $totalInvalid = 0;

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
            
            // Dates Parsing
            $pubDate = $this->parseFlexibleDate($row['Content Public Date'] ?? '');
            $deadline = $this->parseFlexibleDate($row['Deadline Time & Date'] ?? '');

            // Team Label extraction (Remove " Team")
            $teamRaw = trim($row['Team'] ?? '');
            $teamLabel = preg_replace('/\s+Team$/i', '', $teamRaw);
            if (strtolower($teamLabel) === 'none') $teamLabel = '';

            // Title
            $cluster = trim($row['Cluster'] ?? 'Unknown');
            $contentType = trim($row['Work Task / Content Type'] ?? 'Task');
            $title = $cluster . ' - ' . $contentType;

            // Generate Description
            $desc = "**Cluster:** {$cluster}\n";
            $desc .= "**Content Type:** {$contentType}\n";
            $desc .= "**Assigned By:** " . ($row['Assigned By'] ?? 'N/A') . "\n";
            $desc .= "**Assigned To:** " . ($row['Assigned To'] ?? 'N/A') . "\n";
            $desc .= "**Publish Date:** " . ($row['Content Public Date'] ?? 'N/A') . "\n";
            $desc .= "**Deadline:** " . ($row['Deadline Time & Date'] ?? 'N/A') . "\n";
            $desc .= "**Imported From:** {$worksheetName}\n";
            $desc .= "**Imported:** " . now()->format('F j, Y') . "\n";

            // Determine Week List
            $weekNumber = '1';
            if (preg_match('/Week\s*(\d)/i', $worksheetName, $matches)) {
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

            $preview[] = [
                'row' => $idx + 2,
                'title' => $title,
                'smm_class_label' => $contentType,
                'smm_team_label' => $teamLabel,
                'description' => $desc,
                'start_date' => $pubDate,
                'deadline' => $deadline,
                'assign_by_raw' => trim($row['Assigned By'] ?? ''),
                'assign_to_raw' => trim($row['Assigned To'] ?? ''),
                'list_id' => $listId,
                'list_name' => $listName,
                'valid' => $isValid,
                'errors' => $errors,
                'worksheet' => $worksheetName,
            ];
        }

        return response()->json([
            'total' => count($preview),
            'valid' => $totalValid,
            'invalid' => $totalInvalid,
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
            $userLookup[strtolower(trim($u->name))] = $u->id;
            $userLookup[strtolower(trim($u->username))] = $u->id;
            
            // Extract core name for fuzzy matching (remove Mr/Ms and parenthetical roles)
            $coreName = preg_replace('/^(Mr\.|Ms\.|Mrs\.)\s*/i', '', $u->name);
            $coreName = preg_replace('/\s*\(.*?\)/', '', $coreName);
            $coreName = strtolower(trim($coreName));
            if ($coreName) {
                $userLookup[$coreName] = $u->id;
            }
        }

        // 2. Pre-load existing cards for this board to update duplicates efficiently
        $existingCards = Card::where('board_id', $board->id)
            ->select('id', 'title', 'start_date', 'due_at', 'description', 'smm_class_label', 'smm_team_label', 'sync_id')
            ->get();
            
        $existingCardsMap = [];
        foreach ($existingCards as $ec) {
            $key = strtolower($ec->title) . '|' . $ec->start_date;
            $existingCardsMap[$key] = $ec;
        }

        // 3. Pre-load workspaces and their active boards for team distribution
        $workspaces = Workspace::with(['boards' => function ($query) {
            $query->where('is_archived', false)->orderBy('created_at', 'desc');
        }, 'boards.lists' => function ($query) {
            $query->where('is_archived', false)->orderBy('position');
        }])->get();

        foreach ($rows as $row) {
            if (empty($row['valid'])) {
                $skipped++;
                continue;
            }

            // Fix for duplicate testing within same payload
            $compositeKey = md5(strtolower($row['worksheet'] . '|' . $row['title'] . '|' . $row['assign_to_raw'] . '|' . $row['start_date']));
            
            if (in_array($compositeKey, $importedKeys)) {
                $skippedDuplicates++;
                continue; // Skip within same file
            }
            $importedKeys[] = $compositeKey;

            // Auto-create class if it doesn't exist
            $className = $row['smm_class_label'] ?? '';
            if (!empty($className) && !in_array(strtolower($className), $existingClasses)) {
                SocialMediaClass::create([
                    'name' => $className,
                    'color' => '#6366f1',
                    'is_active' => true,
                ]);
                $existingClasses[] = strtolower($className);
            }

            // Fuzzy match helper
            $fuzzyMatch = function($raw) use ($userLookup) {
                $raw = strtolower(trim($raw));
                if (isset($userLookup[$raw])) return $userLookup[$raw];
                
                $core = preg_replace('/^(Mr\.|Ms\.|Mrs\.)\s*/i', '', $raw);
                $core = preg_replace('/\s*\(.*?\)/', '', $core);
                $core = trim($core);
                return $userLookup[$core] ?? null;
            };

            // Find User ID with fuzzy matching
            $assignToUserId = $fuzzyMatch($row['assign_to_raw'] ?? '');
            $assignByUserId = $fuzzyMatch($row['assign_by_raw'] ?? '');
            $createdById = $assignByUserId ?: auth()->id();

            // Check if card exists for updating from pre-loaded map
            $lookupKey = strtolower($row['title']) . '|' . $row['start_date'];
            $existingCard = $existingCardsMap[$lookupKey] ?? null;

            if ($existingCard) {
                // Update
                Card::where('id', $existingCard->id)->update([
                    'description' => $row['description'],
                    'due_at' => $row['deadline'] ?: null,
                    'smm_class_label' => $className ?: null,
                    'smm_team_label' => $row['smm_team_label'] ?: null,
                ]);
                
                // Collect label IDs to sync
                $labelIds = [];
                if (!empty($row['smm_team_label'])) {
                    $teamLabel = Label::firstOrCreate(['name' => trim($row['smm_team_label']), 'workspace_id' => null, 'board_id' => null], ['color' => '#f43f5e']);
                    $labelIds[] = $teamLabel->id;
                }
                if (!empty($className)) {
                    $classLabel = Label::firstOrCreate(['name' => trim($className), 'workspace_id' => null, 'board_id' => null], ['color' => '#8b5cf6']);
                    $labelIds[] = $classLabel->id;
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
                    'start_date' => $row['start_date'] ?: null,
                    'due_at' => $row['deadline'] ?: null,
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
                if (!empty($className)) {
                    $classLabel = Label::firstOrCreate(['name' => trim($className), 'workspace_id' => null, 'board_id' => null], ['color' => '#8b5cf6']);
                    $labelIds[] = $classLabel->id;
                }
                $smmLabel = Label::firstOrCreate(['name' => 'SMM', 'workspace_id' => null, 'board_id' => null], ['color' => '#10b981']);
                $labelIds[] = $smmLabel->id;
                
                $card->labels()->sync($labelIds);

                if ($assignToUserId) {
                    $card->assignees()->sync([$assignToUserId => ['assigned_at' => now()]]);
                }
                
                $created[] = $card;
                
                // Distribute/Sync created card
                $this->distributeToTeamWorkspace($card, $row['smm_team_label'] ?? null, $workspaces);
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
        if (empty($teamLabel)) return;

        $targetWorkspace = null;
        foreach ($workspaces as $workspace) {
            if (stripos($workspace->name, $teamLabel) !== false) {
                $targetWorkspace = $workspace;
                break;
            }
        }
        
        if (!$targetWorkspace || $targetWorkspace->boards->isEmpty()) return;
        
        $teamBoard = $targetWorkspace->boards->first();
        if ($teamBoard->lists->isEmpty()) return;
        
        $teamList = $teamBoard->lists->first();
        
        // Ensure sync_id exists
        if (!$card->sync_id) {
            Card::withoutEvents(function () use ($card) {
                $card->sync_id = \Illuminate\Support\Str::uuid();
                $card->save();
            });
        }
        
        // Quick check if already synced (still 1 query per card distributed, but better than 5 per card)
        $alreadySynced = Card::where('board_id', $teamBoard->id)->where('sync_id', $card->sync_id)->exists();
        if (!$alreadySynced) {
            $clone = $card->replicateRelationally($teamBoard->id, $teamList->id);
            $clone->update(['status' => 'todo']);
        }
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
            $map[$colName] = $idx;
        }
        return $map;
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
