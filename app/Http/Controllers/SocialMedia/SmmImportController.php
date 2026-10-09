<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use App\Models\SocialMediaClass;
use App\Models\Label;
use App\Models\CardChecklist;
use App\Models\CardChecklistItem;
use App\Models\CardFile;
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
        'Class', 'Team', 'Work Task / Content Type', 'Title', 'Description', 'Attachement', 'Checklist',
        'Assigned To', 'Assigned By', 'Content Public Date', 'Deadline Date', 'Deadline Time', 'Weeks', 'Status', 'Note'
    ];

    public function template(Board $board): Response
    {
        $headers = implode(',', self::HEADERS);
        $sample1 = 'ImpossibleMachinery,Graphic Team,Poster Design,TYPH-1702 Ebay Content,Desing 3 posters,https://example.com,Pich,Srey Pich,6-August-2026,29-July-2026,12:00,Week1,Not yet,Test note';
        $sample2 = 'MachineryAsia,Video Team,Short Reel,TYPH-1703,Create short reel,https://example.com/video,Lyhour,Srey Pich,5-August-2026,30-July-2026,15:00,Week1,Not yet,';
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
            $csvContent = $this->fetchGoogleSheetsCsv($request->sheets_url, $worksheetName);
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
        $allBoardLists = $board->activeLists()->orderBy('position')->get();
        $firstBoardList = $allBoardLists->first();
        $explicitTargetListId = $request->input('target_list_id');
        $explicitList = $explicitTargetListId ? $allBoardLists->firstWhere('id', (int)$explicitTargetListId) : null;

        $userLookup = $this->buildUserLookup();

        $preview = [];
        $totalValid = 0;
        $totalInvalid = 0;
        $totalWarnings = 0;

        foreach ($dataRows as $idx => $rawRow) {
            // Skip fully empty rows
            if (count(array_filter($rawRow, fn($c) => trim($c) !== '')) === 0) continue;

            $row = $this->mapRow($rawRow, $colMap);
            
            // Skip group headers (e.g. SREYPICH'S CLUSTERS)
            // If Class has text but everything else is empty, it's likely a header
            if (!empty($row['Class']) && empty($row['Work Task / Content Type']) && empty($row['Team']) && empty($row['Assigned To'])) {
                continue;
            }

            if (empty($row['Work Task / Content Type']) && empty($row['Class'])) {
                continue; // Skip formatting rows
            }

            // Only import if Status is empty or 'Not yet'
            $status = strtolower(trim($row['Status'] ?? ''));
            if ($status !== '' && $status !== 'not yet') {
                continue;
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

            // Team Label extraction (Multi-select)
            $teamResult = $this->resolveGlobalLabels($row['Team'] ?? '');
            $teamLabel = $teamResult['canonical_string'];
            
            // Cluster (Class) and Content Type (Multi-select)
            $clusterResult = SocialMediaClass::resolveClusters($row['Class'] ?? '');
            $cluster = $clusterResult['canonical_string'];

            $contentTypeResult = $this->resolveContentTypes($row['Work Task / Content Type'] ?? '');
            $contentType = $contentTypeResult['canonical_string'];

            // If Team is empty, infer from content types
            if (empty($teamLabel) && !empty($contentTypeResult['canonical_string'])) {
                $inferredTeam = $this->resolveGlobalLabels($contentTypeResult['canonical_string']);
                if (!empty($inferredTeam['canonical_string'])) {
                    $teamLabel = $inferredTeam['canonical_string'];
                }
            }

            // Title
            $rawTitle = trim($row['Title'] ?? '');
            if (empty($rawTitle) && empty($contentType)) continue;
            
            if (empty($rawTitle)) {
                $title = $contentType;
            } else {
                $missingTypes = [];
                foreach ($contentTypeResult['types'] as $ct) {
                    if (stripos($rawTitle, $ct) === false) {
                        $missingTypes[] = $ct;
                    }
                }
                if (!empty($missingTypes)) {
                    $title = $rawTitle . ' - ' . implode(', ', $missingTypes);
                } else {
                    $title = $rawTitle;
                }
            }

            // Description, Attachment and Checklist
            $desc = trim($row['Description'] ?? '');
            $attachment = trim($row['Attachement'] ?? '');
            $checklist = trim($row['Checklist'] ?? '');

            // Determine Target List
            if ($explicitList) {
                $listId = $explicitList->id;
                $listName = $explicitList->name;
            } else {
                [$listId, $listName] = $this->resolveTargetList($row['Weeks'] ?? '', $worksheetName, $allBoardLists, $firstBoardList);
            }

            $isValid = empty($errors);
            if ($isValid) $totalValid++;
            else $totalInvalid++;

            $assignTo = $this->resolveMembers($row['Assigned To'] ?? '', $userLookup);
            $assignBy = $this->resolveMembers($row['Assigned By'] ?? '', $userLookup);

            foreach ($assignTo['warnings'] as $w) {
                $warnings[] = $w;
                $totalWarnings++;
            }
            foreach ($assignBy['warnings'] as $w) {
                $warnings[] = $w;
                $totalWarnings++;
            }

            $preview[] = [
                'row' => $idx + 2,
                'title' => $title,
                'smm_class_label' => $cluster,
                'smm_cluster_label' => $contentType,
                'smm_team_label' => $teamLabel,
                'description' => $desc,
                'attachment' => $attachment,
                'checklist' => $checklist,
                'content_public_date' => $pubDate,
                'start_date' => $startDate,
                'deadline' => $deadline,
                'due_time' => $deadlineTimeStr,
                'assign_by_raw' => trim($row['Assigned By'] ?? ''),
                'assign_to_raw' => trim($row['Assigned To'] ?? ''),
                'assigned_name' => $assignTo['resolved_name'] ?: trim($row['Assigned To'] ?? ''),
                'assigned_by_name' => $assignBy['resolved_name'] ?: trim($row['Assigned By'] ?? ''),
                'assigned_to_ids' => $assignTo['ids'],
                'assigned_by_ids' => $assignBy['ids'],
                'list_id' => $listId,
                'list_name' => $listName,
                'is_duplicate' => false,
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

        $userLookup = $this->buildUserLookup();

            // 2. Pre-load existing cards for this board to update duplicates efficiently
            $existingCards = Card::where('board_id', $board->id)
                ->whereNull('deleted_at')
                ->select('id', 'title', 'start_date', 'content_public_date', 'due_at', 'description', 'smm_class_label', 'smm_team_label', 'sync_group_id', 'board_list_id')
                ->get();
                
            $existingCardsMap = [];
            foreach ($existingCards as $ec) {
                $rawDate = $ec->start_date ?? $ec->content_public_date;
                $dateKey = '';
                if ($rawDate instanceof \Carbon\CarbonInterface) {
                    $dateKey = $rawDate->format('Y-m-d');
                } elseif (!empty($rawDate)) {
                    $dateKey = substr(trim((string)$rawDate), 0, 10);
                }
                
                $classKey = strtolower(trim($ec->smm_class_label ?? ''));
                $titleKey = strtolower(trim($ec->title ?? ''));

                // CRITICAL: Scope duplicate lookup to the specific board_list_id AND smm_class_label!
                // Multiple brands (classes) have identical titles (e.g. TYPH-SPIDER, MZVT) in the same week list!
                // They must NEVER overwrite each other!
                if (!empty($classKey)) {
                    if (!empty($dateKey)) {
                        $existingCardsMap[$ec->board_list_id . '|' . $classKey . '|' . $titleKey . '|' . $dateKey] = $ec;
                    }
                    if (!isset($existingCardsMap[$ec->board_list_id . '|' . $classKey . '|' . $titleKey . '|'])) {
                        $existingCardsMap[$ec->board_list_id . '|' . $classKey . '|' . $titleKey . '|'] = $ec;
                    }

                    // Multi-select cluster support: also index each individual cluster of the card
                    if (str_contains($classKey, ',')) {
                        $indivClasses = preg_split('/[,&+\/\n]+/', $classKey, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($indivClasses as $ic) {
                            $ic = trim($ic);
                            if ($ic !== '') {
                                if (!empty($dateKey) && !isset($existingCardsMap[$ec->board_list_id . '|' . $ic . '|' . $titleKey . '|' . $dateKey])) {
                                    $existingCardsMap[$ec->board_list_id . '|' . $ic . '|' . $titleKey . '|' . $dateKey] = $ec;
                                }
                                if (!isset($existingCardsMap[$ec->board_list_id . '|' . $ic . '|' . $titleKey . '|'])) {
                                    $existingCardsMap[$ec->board_list_id . '|' . $ic . '|' . $titleKey . '|'] = $ec;
                                }
                            }
                        }
                    }
                } else {
                    // Fallback for existing unclassified cards
                    if (!empty($dateKey)) {
                        $existingCardsMap[$ec->board_list_id . '||' . $titleKey . '|' . $dateKey] = $ec;
                    }
                    if (!isset($existingCardsMap[$ec->board_list_id . '||' . $titleKey . '|'])) {
                        $existingCardsMap[$ec->board_list_id . '||' . $titleKey . '|'] = $ec;
                    }
                }
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

            // Cluster (Class) resolution (Multi-select)
            $clusterResult = SocialMediaClass::resolveClusters($row['smm_class_label'] ?? '');
            $clusterName = $clusterResult['canonical_string'];
            $clusterNames = $clusterResult['names'];

            // Content Type resolution (Multi-select)
            $contentTypeResult = $this->resolveContentTypes($row['smm_cluster_label'] ?? '');
            $contentType = $contentTypeResult['canonical_string'];

            // Team Label resolution (Multi-select)
            $teamResult = $this->resolveGlobalLabels($row['smm_team_label'] ?? '');
            if (empty($teamResult['names']) && !empty($contentTypeResult['canonical_string'])) {
                $teamResult = $this->resolveGlobalLabels($contentTypeResult['canonical_string']);
            }
            $teamName = $teamResult['canonical_string'];

            $dateKey = $row['start_date'] ?: $row['content_public_date'];
            $compositeKey = md5(strtolower(($row['worksheet'] ?? '') . '|' . $clusterName . '|' . ($row['title'] ?? '') . '|' . ($row['assign_to_raw'] ?? '') . '|' . $dateKey));
            
            if (in_array($compositeKey, $importedKeys)) {
                $skippedDuplicates++;
                continue; // Skip within same file
            }
            $importedKeys[] = $compositeKey;

            // Auto-create each cluster class if it doesn't exist (Class = Cluster/Brand)
            foreach ($clusterNames as $cName) {
                if (!empty($cName) && !in_array(strtolower($cName), $existingClasses)) {
                    SocialMediaClass::create([
                        'name' => $cName,
                        'status' => 'active',
                        'created_by' => auth()->id(),
                    ]);
                    $existingClasses[] = strtolower($cName);
                }
            }

            // Find User IDs with multi-member matching
            $assignToIds = !empty($row['assigned_to_ids']) && is_array($row['assigned_to_ids'])
                ? $row['assigned_to_ids']
                : $this->resolveMembers($row['assign_to_raw'] ?? '', $userLookup)['ids'];

            $assignByResult = !empty($row['assigned_by_ids']) && is_array($row['assigned_by_ids'])
                ? ['ids' => $row['assigned_by_ids'], 'primary_id' => $row['assigned_by_ids'][0] ?? null]
                : $this->resolveMembers($row['assign_by_raw'] ?? '', $userLookup);

            $assignByIds = $assignByResult['ids'] ?? [];
            $createdById = $assignByResult['primary_id'] ?: auth()->id();

            // Detect Team A or Team B based on all Assigned By members
            $cardTeam = null;
            $allAssignerUsers = !empty($assignByIds) ? User::whereIn('id', $assignByIds)->get() : collect();
            if ($createdById && !$allAssignerUsers->contains('id', $createdById)) {
                $cUser = User::find($createdById);
                if ($cUser) $allAssignerUsers->push($cUser);
            }

            foreach ($allAssignerUsers as $u) {
                $uName = strtolower($u->name ?? '');
                $uUname = strtolower($u->username ?? '');
                if ($u->id === 13 || str_contains($uUname, 'kim') || str_contains($uName, 'kim')) {
                    $cardTeam = 'B';
                    break;
                }
                if ($u->id === 12 || $u->id === 24 || str_contains($uUname, 'dara') || str_contains($uName, 'dara')) {
                    $cardTeam = 'A';
                }
            }

            // Check if card exists for updating from pre-loaded map (strictly scoped to target list_id and class)
            $targetListId = $row['list_id'] ?? null;
            $rawRowDate = $row['start_date'] ?: $row['content_public_date'];
            $dateKey = !empty($rawRowDate) ? substr(trim((string)$rawRowDate), 0, 10) : '';
            $rowClassKey = strtolower(trim($clusterName));
            $rowTitleKey = strtolower(trim($row['title']));
            
            $existingCard = null;
            if ($targetListId) {
                // 1. Exact full class + title + date
                if (!empty($dateKey)) {
                    $lookupKey = $targetListId . '|' . $rowClassKey . '|' . $rowTitleKey . '|' . $dateKey;
                    if (isset($existingCardsMap[$lookupKey])) {
                        $existingCard = $existingCardsMap[$lookupKey];
                    }
                }
                // 2. Exact full class + title (no date)
                if (!$existingCard) {
                    $lookupKeyNoDate = $targetListId . '|' . $rowClassKey . '|' . $rowTitleKey . '|';
                    if (isset($existingCardsMap[$lookupKeyNoDate])) {
                        $existingCard = $existingCardsMap[$lookupKeyNoDate];
                    }
                }
                // 3. Multi-cluster: match individual cluster if full cluster string didn't hit
                if (!$existingCard && count($clusterNames) > 0) {
                    foreach ($clusterNames as $cn) {
                        $cnKey = strtolower(trim($cn));
                        if (!empty($dateKey)) {
                            $lookupIndivKey = $targetListId . '|' . $cnKey . '|' . $rowTitleKey . '|' . $dateKey;
                            if (isset($existingCardsMap[$lookupIndivKey])) {
                                $existingCard = $existingCardsMap[$lookupIndivKey];
                                break;
                            }
                        }
                        $lookupIndivNoDate = $targetListId . '|' . $cnKey . '|' . $rowTitleKey . '|';
                        if (isset($existingCardsMap[$lookupIndivNoDate])) {
                            $existingCard = $existingCardsMap[$lookupIndivNoDate];
                            break;
                        }
                    }
                }
                // 4. Fallback: match unclassified card only if it had no class assigned yet
                if (!$existingCard && !empty($dateKey)) {
                    $lookupUnclassKey = $targetListId . '||' . $rowTitleKey . '|' . $dateKey;
                    if (isset($existingCardsMap[$lookupUnclassKey])) {
                        $existingCard = $existingCardsMap[$lookupUnclassKey];
                    }
                }
                if (!$existingCard) {
                    $lookupUnclassNoDate = $targetListId . '||' . $rowTitleKey . '|';
                    if (isset($existingCardsMap[$lookupUnclassNoDate])) {
                        $existingCard = $existingCardsMap[$lookupUnclassNoDate];
                    }
                }
            }

            // Prepare label IDs: all detected team labels + SMM label
            $labelIds = $teamResult['label_ids'];
            $smmLabel = Label::firstOrCreate(['name' => 'SMM', 'workspace_id' => null, 'board_id' => null], ['color' => '#50C878']);
            $labelIds[] = $smmLabel->id;
            $uniqueLabelIds = array_values(array_unique($labelIds));

            $assigneesData = [];
            foreach ($assignToIds as $uId) {
                $assigneesData[$uId] = ['assigned_at' => now()];
            }

            if ($existingCard) {
                $existingCard->update([
                    'board_list_id' => $targetListId,
                    'description' => $row['description'],
                    'smm_class_label' => $clusterName ?: null,
                    'smm_team_label' => $teamName ?: null,
                    'smm_cluster_label' => $contentType ?: null,
                    'start_date' => $row['start_date'] ?: null,
                    'content_public_date' => $row['content_public_date'] ?: null,
                    'due_at' => $row['deadline'] ?: null,
                    'due_time' => $row['due_time'] ?? null,
                    'created_by' => $createdById,
                    'team' => $cardTeam ?: $existingCard->team,
                ]);

                // Cascade update to distributed cards
                if ($existingCard->sync_group_id) {
                    Card::where('sync_group_id', $existingCard->sync_group_id)->update([
                        'smm_class_label' => $clusterName ?: null,
                        'smm_team_label' => $teamName ?: null,
                        'smm_cluster_label' => $contentType ?: null,
                        'created_by' => $createdById,
                        'team' => $cardTeam ?: $existingCard->team,
                    ]);
                }

                $existingCard->labels()->sync($uniqueLabelIds);
                $existingCard->assignees()->sync($assigneesData);

                // Cascade assignees & labels to twin cards in sync_group
                if ($existingCard->sync_group_id) {
                    $siblings = Card::where('sync_group_id', $existingCard->sync_group_id)
                        ->where('id', '!=', $existingCard->id)
                        ->get();
                    foreach ($siblings as $sibling) {
                        $sibling->assignees()->sync($assigneesData);
                        $sibling->labels()->sync($uniqueLabelIds);
                    }
                } else {
                    // Distribute to team workspace if not previously synced
                    foreach ($teamResult['names'] as $tName) {
                        if ($tName === 'SMM') continue;
                        $teamBoard = $this->distributeToTeamWorkspace($existingCard, $tName, $workspaces);
                        if ($teamBoard) {
                            if (!isset($distributedCounts[$teamBoard->id])) {
                                $distributedCounts[$teamBoard->id] = ['board' => $teamBoard, 'count' => 0];
                            }
                            $distributedCounts[$teamBoard->id]['count']++;
                        }
                    }
                }
                
                $targetCard = $existingCard;
            } else {
                $position = Card::where('board_list_id', $targetListId)->max('position') + 1;
                $card = Card::create([
                    'board_id' => $board->id,
                    'board_list_id' => $targetListId,
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'smm_class_label' => $clusterName ?: null,
                    'smm_team_label' => $teamName ?: null,
                    'smm_cluster_label' => $contentType ?: null,
                    'start_date' => $row['start_date'] ?: null,
                    'content_public_date' => $row['content_public_date'] ?: null,
                    'due_at' => $row['deadline'] ?: null,
                    'due_time' => $row['due_time'] ?? null,
                    'status' => 'todo',
                    'position' => $position,
                    'created_by' => $createdById,
                    'team' => $cardTeam,
                ]);

                $card->labels()->sync($uniqueLabelIds);
                $card->assignees()->sync($assigneesData);

                // Distribute/Sync created card for each team
                foreach ($teamResult['names'] as $tName) {
                    if ($tName === 'SMM') continue;
                    $teamBoard = $this->distributeToTeamWorkspace($card, $tName, $workspaces);
                    if ($teamBoard) {
                        if (!isset($distributedCounts[$teamBoard->id])) {
                            $distributedCounts[$teamBoard->id] = ['board' => $teamBoard, 'count' => 0];
                        }
                        $distributedCounts[$teamBoard->id]['count']++;
                    }
                }

                $targetCard = $card;
            }

            // Handle Multi-Link Attachments (e.g. "Spec Resource: link url , Content Breakdown: link url")
            if (!empty($row['attachment'])) {
                $this->attachLinksToCard($targetCard, $row['attachment'], $createdById);
            }

            // Handle Checklist (auto-creates "Status" checklist with items like Graphic, Video, Description)
            if (!empty($row['checklist'])) {
                $this->syncCardChecklist($targetCard, $row['checklist']);
            }

            if ($existingCard) {
                $existingCard->load(['labels', 'assignees', 'files', 'checklists.items']);
                $updated[] = $existingCard;
            } else {
                $card->load(['labels', 'assignees', 'files', 'checklists.items']);
                $created[] = $card;
            }
        }

        $totalSkipped = $skipped + $skippedDuplicates;
        $logMessage = "Imported " . count($created) . " cards. Skipped $totalSkipped duplicates/invalid.";
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'import',
            'model_type' => 'App\\Models\\Board',
            'model_id' => $board->id,
            'details' => $logMessage
        ]);

        if (count($created) > 0) {
            $actor = auth()->user();
            $actorId = $actor?->id ?? 1;
            $actorName = $actor?->name ?? 'System';
            $actorAvatar = $actor?->avatar_url;
            $smmMessage = $actorName . " imported " . count($created) . " cards into SMM board";
            
            // 1. Notify SMM Board admins
            $smmAdmins = \App\Models\User::role(['admin-digital', 'social_qc', 'super-admin'])->get();
            foreach ($smmAdmins as $admin) {
                if ($admin->id !== $actorId) {
                    $admin->notify(new \App\Notifications\GenericDatabaseNotification([
                        'actor_id'     => $actorId,
                        'actor_name'   => $actorName,
                        'actor_avatar' => $actorAvatar,
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
                $teamMessage = $actorName . " imported $count cards into Planning Board";
                foreach ($teamBoard->members as $member) {
                    if ($member->id !== $actorId) {
                        $member->notify(new \App\Notifications\GenericDatabaseNotification([
                            'actor_id'     => $actorId,
                            'actor_name'   => $actorName,
                            'actor_avatar' => $actorAvatar,
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
            'cards' => array_merge($created, $updated),
        ]);
    }
    
    /** Distribute card directly to the team workspace Planning Board using BoardWorkflowService */
    private function distributeToTeamWorkspace(Card $card, ?string $teamLabel, $workspaces)
    {
        return app(\App\Services\BoardWorkflowService::class)->distributeSmmCardToTeam($card, $teamLabel, $workspaces);
    }

    /**
     * Map raw team string or keyword to canonical team name:
     * Graphic, Video, Listing, Content, SMM.
     */
    public function resolveCanonicalTeamName(?string $team): ?string
    {
        if (empty($team)) return null;
        $clean = strtolower(trim($team));

        if (str_contains($clean, 'graphic') || str_contains($clean, 'poster') || str_contains($clean, 'banner') || str_contains($clean, 'flyer') || str_contains($clean, 'design')) {
            return 'Graphic';
        } elseif (str_contains($clean, 'video') || str_contains($clean, 'reel') || str_contains($clean, 'landscape') || str_contains($clean, 'motion') || str_contains($clean, 'animation') || str_contains($clean, 'tiktok') || str_contains($clean, 'youtube')) {
            return 'Video';
        } elseif (str_contains($clean, 'listing') || str_contains($clean, 'share blog') || str_contains($clean, 'caption')) {
            return 'Listing';
        } elseif (str_contains($clean, 'content') || str_contains($clean, 'writing') || str_contains($clean, 'writer') || str_contains($clean, 'blog') || str_contains($clean, 'article') || str_contains($clean, 'copywrit')) {
            return 'Content';
        } elseif ($clean === 'smm' || str_contains($clean, 'smm')) {
            return 'SMM';
        }

        return null;
    }

    /**
     * Resolve a raw team string (single or multi-select delimited) to an array of canonical names,
     * a clean joined string, Label models, and label IDs.
     */
    public function resolveGlobalLabels(?string $teamRaw): array
    {
        $teamRaw = trim((string)$teamRaw);
        if ($teamRaw === '' || strtolower($teamRaw) === 'none') {
            return [
                'names' => [],
                'canonical_string' => '',
                'labels' => [],
                'label_ids' => [],
            ];
        }

        $parts = preg_split('/[,&+\/\n]|(?:\band\b)/i', $teamRaw, -1, PREG_SPLIT_NO_EMPTY);
        $names = [];

        foreach ($parts as $p) {
            $canonical = $this->resolveCanonicalTeamName(trim($p));
            if ($canonical && !in_array($canonical, $names)) {
                $names[] = $canonical;
            }
        }

        $labels = [];
        $labelIds = [];

        foreach ($names as $name) {
            $lbl = $this->resolveGlobalLabel($name);
            if ($lbl) {
                $labels[] = $lbl;
                $labelIds[] = $lbl->id;
            }
        }

        return [
            'names' => $names,
            'canonical_string' => implode(', ', $names),
            'labels' => $labels,
            'label_ids' => $labelIds,
        ];
    }

    /**
     * Resolve a raw content type string (single or multi-select delimited) to an array of unique
     * content types and a clean joined string.
     */
    public function resolveContentTypes(?string $raw): array
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return ['types' => [], 'canonical_string' => ''];
        }

        $parts = preg_split('/[,&+\/\n]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $types = [];
        $seenLower = [];
        foreach ($parts as $p) {
            $t = trim($p);
            $lower = strtolower($t);
            if ($t !== '' && !in_array($lower, $seenLower)) {
                $types[] = $t;
                $seenLower[] = $lower;
            }
        }

        return [
            'types' => $types,
            'canonical_string' => implode(', ', $types),
        ];
    }

    /**
     * Resolve a raw cluster string to canonical clusters.
     */
    public function resolveClusters(?string $raw): array
    {
        return SocialMediaClass::resolveClusters($raw);
    }

    /**
     * Resolve team string to one of the 5 canonical global labels:
     * Graphic, Video, Listing, Content, SMM.
     */
    public function resolveGlobalLabel(?string $team): ?Label
    {
        if (empty($team)) return null;
        $targetName = $this->resolveCanonicalTeamName($team);
        if (!$targetName) return null;

        static $cachedLabels = [];
        if (isset($cachedLabels[$targetName])) {
            return $cachedLabels[$targetName];
        }

        $color = match ($targetName) {
            'Graphic', 'Video' => '#f43f5e',
            'Listing'          => '#f59e0b',
            'Content'          => '#0ea5e9',
            'SMM'              => '#50C878',
            default            => '#6366f1'
        };

        try {
            $label = Label::firstOrCreate(
                ['name' => $targetName, 'workspace_id' => null, 'board_id' => null],
                ['color' => $color]
            );
            $cachedLabels[$targetName] = $label;
            return $label;
        } catch (\Throwable $e) {
            return null;
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

    private function fetchGoogleSheetsCsv(string $url, ?string $worksheetName = null): ?string
    {
        if (preg_match('/\/d\/([a-zA-Z0-9\-_]+)/', $url, $matches)) {
            $fileId = $matches[1];
            
            $gidStr = '';
            $gidParam = '';
            if (preg_match('/[#&?]gid=([0-9]+)/', $url, $gidMatches)) {
                $gidStr = '&gid=' . $gidMatches[1];
                $gidParam = '&gid=' . $gidMatches[1];
            }

            $sheetParam = '';
            if (empty($gidStr) && !empty($worksheetName)) {
                $sheetParam = '&sheet=' . urlencode($worksheetName);
            }
            
            // Try gviz endpoint first
            try {
                $csvUrl = "https://docs.google.com/spreadsheets/d/{$fileId}/gviz/tq?tqx=out:csv{$gidStr}{$sheetParam}";
                $response = Http::timeout(15)->get($csvUrl);
                if ($response->successful() && !empty(trim($response->body()))) {
                    return $response->body();
                }
            } catch (\Throwable $e) {}

            // Fallback to standard export endpoint
            try {
                $exportUrl = "https://docs.google.com/spreadsheets/d/{$fileId}/export?format=csv{$gidParam}";
                $response = Http::timeout(15)->get($exportUrl);
                if ($response->successful() && !empty(trim($response->body()))) {
                    return $response->body();
                }
            } catch (\Throwable $e) {}
        }
        return null;
    }

    private function parseCsv(string $content): array
    {
        $rows = [];
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        while (($data = fgetcsv($stream, null, ',', '"', '\\')) !== false) {
            if (count($data) === 1 && $data[0] === null) continue;

            $isEmpty = true;
            foreach ($data as $field) {
                if (trim((string)$field) !== '') {
                    $isEmpty = false;
                    break;
                }
            }
            if ($isEmpty) continue;

            $rows[] = $data;
        }
        fclose($stream);
        
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
            elseif (
                $cleanName === 'class' ||
                $cleanName === 'cluster' ||
                str_contains($cleanName, 'class') ||
                str_contains($cleanName, 'cluster') ||
                str_contains($cleanName, 'brand')
            ) $map['Class'] = $idx;
            elseif ($cleanName === 'team') $map['Team'] = $idx;
            elseif ($cleanName === 'title') $map['Title'] = $idx;
            elseif ($cleanName === 'description') $map['Description'] = $idx;
            elseif (str_contains($cleanName, 'attach')) $map['Attachement'] = $idx; // Map back to what mapRow expects
            elseif (str_contains($cleanName, 'check') || $cleanName === 'checklist') $map['Checklist'] = $idx;
            elseif ($cleanName === 'assigned to' || $cleanName === 'assign to') $map['Assigned To'] = $idx;
            elseif ($cleanName === 'assigned by' || $cleanName === 'assign by') $map['Assigned By'] = $idx;
            elseif (str_contains($cleanName, 'content public') || str_contains($cleanName, 'public date') || str_contains($cleanName, 'publish date') || str_contains($cleanName, 'content publish') || $cleanName === 'public' || $cleanName === 'publish') $map['Content Public Date'] = $idx;
            elseif (str_contains($cleanName, 'deadline date')) $map['Deadline Date'] = $idx;
            elseif (str_contains($cleanName, 'deadline time')) $map['Deadline Time'] = $idx;
            elseif (str_contains($cleanName, 'start date')) $map['Start Date'] = $idx;
            elseif ($cleanName === 'status') $map['Status'] = $idx;
            elseif (in_array($cleanName, ['weeks', 'week', 'list', 'target list', 'week list', 'target week', 'board list']) || str_contains($cleanName, 'week') || str_contains($cleanName, 'target list')) $map['Weeks'] = $idx;
            elseif ($cleanName === 'note') $map['Note'] = $idx;
            else $map[$colName] = $idx;
        }
        return $map;
    }

    /**
     * Robust list resolution matching week number, list name, or fallback.
     */
    private function resolveTargetList(?string $weeksVal, ?string $worksheetName, $allLists, ?BoardList $firstList): array
    {
        $weeksVal = trim((string)$weeksVal);
        $worksheetName = trim((string)$worksheetName);

        // 1. Try matching from row's week column
        if (!empty($weeksVal)) {
            // Exact name match
            foreach ($allLists as $list) {
                if (strcasecmp($list->name, $weeksVal) === 0) {
                    return [$list->id, $list->name];
                }
            }

            // Cleaned name match (e.g. "week4" == "week4", "urgentpriority" == "urgent/priority")
            $normVal = preg_replace('/[^a-z0-9]/', '', strtolower($weeksVal));
            foreach ($allLists as $list) {
                $normListName = preg_replace('/[^a-z0-9]/', '', strtolower($list->name));
                if (!empty($normVal) && $normVal === $normListName) {
                    return [$list->id, $list->name];
                }
            }

            // Urgent / Priority
            if (stripos($weeksVal, 'urgent') !== false || stripos($weeksVal, 'priority') !== false) {
                $urgent = $allLists->first(fn($l) => stripos($l->name, 'urgent') !== false || stripos($l->name, 'priority') !== false);
                if ($urgent) return [$urgent->id, $urgent->name];
            }

            // Week number extraction (supports "Week 4", "W4", "Week-4", "4", "Week 04")
            if (preg_match('/(?:week|w)?\s*[\-_]?\s*(\d+)/i', $weeksVal, $m)) {
                $weekNum = (int)$m[1];
                $matched = $allLists->first(fn($l) => preg_match('/(?:week|w)\s*[\-_]?\s*' . $weekNum . '\b/i', $l->name));
                if ($matched) return [$matched->id, $matched->name];
            }
        }

        // 2. Try matching from worksheetName
        if (!empty($worksheetName)) {
            foreach ($allLists as $list) {
                if (strcasecmp($list->name, $worksheetName) === 0) {
                    return [$list->id, $list->name];
                }
            }

            $normWs = preg_replace('/[^a-z0-9]/', '', strtolower($worksheetName));
            foreach ($allLists as $list) {
                $normListName = preg_replace('/[^a-z0-9]/', '', strtolower($list->name));
                if (!empty($normWs) && $normWs === $normListName) {
                    return [$list->id, $list->name];
                }
            }

            if (stripos($worksheetName, 'urgent') !== false || stripos($worksheetName, 'priority') !== false) {
                $urgent = $allLists->first(fn($l) => stripos($l->name, 'urgent') !== false || stripos($l->name, 'priority') !== false);
                if ($urgent) return [$urgent->id, $urgent->name];
            }

            // Week number extraction from worksheetName
            if (preg_match('/(?:week|w)\s*[\-_]?\s*(\d+)/i', $worksheetName, $m) || preg_match('/\b(?:week|w)?\s*(\d+)\b/i', $worksheetName, $m)) {
                $weekNum = (int)$m[1];
                $matched = $allLists->first(fn($l) => preg_match('/(?:week|w)\s*[\-_]?\s*' . $weekNum . '\b/i', $l->name));
                if ($matched) return [$matched->id, $matched->name];
            }
        }

        // 3. Fallback: try finding a list with "Week 1", or use the first list
        $weekOneList = $allLists->first(fn($l) => preg_match('/week\s*1\b/i', $l->name));
        if ($weekOneList) {
            return [$weekOneList->id, $weekOneList->name];
        }

        if ($firstList) {
            return [$firstList->id, $firstList->name];
        }

        return [null, 'Unknown'];
    }

    /**
     * Build indexed lookup tables for fast, precise member matching.
     */
    private function buildUserLookup(): array
    {
        $allUsers = User::select('id', 'name', 'username')->get();
        $cleanAlpha = fn(string $s) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $s));

        $lookup = [
            'exact'  => [], // full lowercased name or username -> user
            'norm'   => [], // alphanumeric normalized -> user
            'tokens' => [], // individual first/last name word tokens -> user
        ];

        foreach ($allUsers as $u) {
            $userEntry = ['id' => $u->id, 'name' => $u->name];

            // 1. Exact full name & username
            $rawName = strtolower(trim($u->name));
            $rawUsername = strtolower(trim($u->username ?? ''));

            if (!empty($rawName)) {
                $lookup['exact'][$rawName] = $userEntry;
                $normName = $cleanAlpha($rawName);
                if (!empty($normName)) {
                    $lookup['norm'][$normName] = $userEntry;
                }
            }

            if (!empty($rawUsername)) {
                $lookup['exact'][$rawUsername] = $userEntry;
                $normUser = $cleanAlpha($rawUsername);
                if (!empty($normUser)) {
                    $lookup['norm'][$normUser] = $userEntry;
                }
            }

            // 2. Core name without honorifics (Mr., Ms., Dr.) and bracketed tags
            $coreName = preg_replace('/^(Mr\.|Ms\.|Mrs\.|Miss|Dr\.)\s*/i', '', $u->name);
            $coreName = preg_replace('/\s*\(.*?\)/', '', $coreName);
            $coreName = preg_replace('/\s*\[.*?\]/', '', $coreName);
            $coreName = strtolower(trim($coreName));

            if (!empty($coreName)) {
                $lookup['exact'][$coreName] = $userEntry;
                $normCore = $cleanAlpha($coreName);
                if (!empty($normCore)) {
                    $lookup['norm'][$normCore] = $userEntry;
                }

                // 3. Individual name tokens (e.g. "Keo Poly" -> tokens: "keo", "poly")
                $tokens = preg_split('/[\s\-\_\/]+/', $coreName, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($tokens as $token) {
                    $token = trim($token);
                    if (strlen($token) >= 2) {
                        // If exact name matches token, it always takes precedence
                        if (!isset($lookup['tokens'][$token]) || strtolower(trim($lookup['tokens'][$token]['name'])) !== $token) {
                            $lookup['tokens'][$token] = $userEntry;
                        }
                    }
                }
            }
        }

        return $lookup;
    }

    /**
     * Resolve a single member string against indexed user lookup.
     */
    public function resolveSingleMember(string $rawName, array $userLookup): array
    {
        $rawName = trim($rawName);
        if (empty($rawName) || in_array(strtolower($rawName), ['none', 'n/a', '-', 'blank', 'no member', 'unassigned'])) {
            return ['id' => null, 'name' => '', 'warning' => null];
        }

        $cleanAlpha = fn(string $s) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $s));

        // 1. Direct exact match
        $lowerRaw = strtolower($rawName);
        if (isset($userLookup['exact'][$lowerRaw])) {
            return ['id' => $userLookup['exact'][$lowerRaw]['id'], 'name' => $userLookup['exact'][$lowerRaw]['name'], 'warning' => null];
        }

        // 2. Strip honorifics & bracketed tags: e.g. "Mr. Pich (Graphic)" -> "Pich"
        $core = preg_replace('/^(Mr\.|Ms\.|Mrs\.|Miss|Dr\.)\s*/i', '', $rawName);
        $core = preg_replace('/\s*\(.*?\)/', '', $core);
        $core = preg_replace('/\s*\[.*?\]/', '', $core);
        $core = trim(strtolower($core));

        if (isset($userLookup['exact'][$core])) {
            return ['id' => $userLookup['exact'][$core]['id'], 'name' => $userLookup['exact'][$core]['name'], 'warning' => null];
        }

        // 3. Clean alphanumeric normalized match: "Srey Pich" == "Sreypich"
        $normCore = $cleanAlpha($core);
        if (!empty($normCore) && isset($userLookup['norm'][$normCore])) {
            return ['id' => $userLookup['norm'][$normCore]['id'], 'name' => $userLookup['norm'][$normCore]['name'], 'warning' => null];
        }

        // 4. Standalone token match (e.g. "Pich" matches user whose first/last name or username is "Pich")
        if (isset($userLookup['tokens'][$core])) {
            return ['id' => $userLookup['tokens'][$core]['id'], 'name' => $userLookup['tokens'][$core]['name'], 'warning' => null];
        }

        // 5. Whole word boundary match (only for query strings >= 4 chars to avoid false positives)
        if (strlen($core) >= 4) {
            foreach ($userLookup['exact'] as $key => $user) {
                if (preg_match('/\b' . preg_quote($core, '/') . '\b/i', $key)) {
                    return ['id' => $user['id'], 'name' => $user['name'], 'warning' => null];
                }
            }
        }

        return [
            'id' => null, 
            'name' => $rawName,
            'warning' => "Member \"$rawName\" could not be matched. Card imported successfully; resolve assignment later."
        ];
    }

    /**
     * Resolve a multi-select raw string of member names (e.g. "Pich, Samnang" or "Kim & Dara")
     * into matched user IDs, resolved names, and any warnings.
     */
    public function resolveMembers(?string $rawString, array $userLookup): array
    {
        $rawString = trim((string)$rawString);
        if (empty($rawString) || in_array(strtolower($rawString), ['none', 'n/a', '-', 'blank', 'no member', 'unassigned'])) {
            return [
                'ids' => [],
                'primary_id' => null,
                'resolved_names' => [],
                'resolved_name' => '',
                'warnings' => [],
            ];
        }

        $parts = preg_split('/[,&+\/\n]|(?:\band\b)/i', $rawString, -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        $resolvedNames = [];
        $warnings = [];

        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $res = $this->resolveSingleMember($p, $userLookup);
            if ($res['id']) {
                if (!in_array($res['id'], $ids)) {
                    $ids[] = $res['id'];
                    $resolvedNames[] = $res['name'];
                }
            } else {
                if (!empty($res['name']) && !in_array($res['name'], $resolvedNames)) {
                    $resolvedNames[] = $res['name'];
                }
                if ($res['warning']) {
                    $warnings[] = $res['warning'];
                }
            }
        }

        return [
            'ids' => $ids,
            'primary_id' => $ids[0] ?? null,
            'resolved_names' => $resolvedNames,
            'resolved_name' => implode(', ', $resolvedNames),
            'warnings' => $warnings,
        ];
    }

    public function resolveMember($rawName, $userLookup)
    {
        $res = $this->resolveMembers($rawName, $userLookup);
        return [
            'id' => $res['primary_id'],
            'warning' => $res['warnings'][0] ?? null,
            'resolved_name' => $res['resolved_name'],
            'ids' => $res['ids'],
            'resolved_names' => $res['resolved_names'],
            'warnings' => $res['warnings'],
        ];
    }

    /**
     * Parse multi-link attachments with names.
     * Supports formats like:
     *   "Spec Resource: https://... , Content Breakdown: https://..."
     *   "Spec Resource: link url , Content Breakdown: link url"
     *   Newline / comma delimited with or without label prefixes.
     */
    public function parseAttachmentLinks(?string $raw): array
    {
        if (empty($raw)) return [];
        $raw = trim($raw);
        if ($raw === '') return [];

        $lines = preg_split('/[\r\n]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $segments = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // Split on comma when followed by either "Label:" or a URL scheme
            $parts = preg_split('/,\s*(?=[^,:]+:\s*|(?:https?:\/\/))/i', $line, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') $segments[] = $p;
            }
        }

        $results = [];
        foreach ($segments as $seg) {
            $name = null;
            $url = null;

            if (preg_match('/^([^:]+):\s*(.+)$/s', $seg, $m)) {
                $nameCandidate = trim($m[1]);
                $urlCandidate = trim($m[2]);

                if (in_array(strtolower($nameCandidate), ['http', 'https'])) {
                    $url = $seg;
                } else {
                    $name = $nameCandidate;
                    $url = $urlCandidate;
                }
            } else {
                $url = $seg;
            }

            $url = trim($url, " \t\n\r\0\x0B,;");
            if (empty($url)) continue;

            $cleanUrl = $url;
            if (!preg_match('~^https?://~i', $cleanUrl)) {
                $cleanUrl = 'https://' . ltrim($cleanUrl, '/');
            }

            if (empty($name)) {
                if (str_contains($cleanUrl, 'drive.google.com')) {
                    $name = 'Google Drive Link';
                } elseif (str_contains($cleanUrl, 'docs.google.com')) {
                    $name = 'Google Docs Link';
                } elseif (str_contains($cleanUrl, 'canva.com')) {
                    $name = 'Canva Design';
                } else {
                    $parsed = parse_url($cleanUrl);
                    $host = $parsed['host'] ?? '';
                    $name = !empty($host) ? $host : 'Attachment Link';
                }
            }

            $results[] = [
                'name' => $name,
                'url' => $cleanUrl,
                'raw_url' => $url,
            ];
        }

        return $results;
    }

    /**
     * Attach parsed links to the card and any synced twin cards in sync_group_id.
     */
    public function attachLinksToCard(Card $card, ?string $rawAttachment, int $uploaderId): void
    {
        if (empty($rawAttachment)) return;

        $links = $this->parseAttachmentLinks($rawAttachment);
        if (empty($links)) return;

        $cardsToAttach = [$card];
        if ($card->sync_group_id) {
            $siblings = Card::where('sync_group_id', $card->sync_group_id)
                ->where('id', '!=', $card->id)
                ->get();
            foreach ($siblings as $s) {
                $cardsToAttach[] = $s;
            }
        }

        foreach ($cardsToAttach as $targetCard) {
            foreach ($links as $link) {
                $url = $link['url'];
                $name = $link['name'];

                $exists = CardFile::where('card_id', $targetCard->id)
                    ->where(function($q) use ($url, $link, $name) {
                        $q->where('path', $url)
                          ->orWhere('path', $link['raw_url'])
                          ->orWhere(function($sub) use ($name, $url) {
                              $sub->where('original_name', $name)->where('path', $url);
                          });
                    })
                    ->exists();

                if (!$exists) {
                    CardFile::create([
                        'card_id'       => $targetCard->id,
                        'disk'          => 'url',
                        'path'          => $url,
                        'original_name' => $name,
                        'stored_name'   => $name,
                        'mime_type'     => 'link',
                        'size'          => 0,
                        'uploaded_by'   => $uploaderId,
                    ]);
                }
            }
        }
    }

    /**
     * Auto-create or synchronize "Status" checklist with given items on card and twin cards.
     */
    public function syncCardChecklist(Card $card, ?string $rawChecklist): void
    {
        if (empty($rawChecklist)) return;

        $items = preg_split('/[,;\r\n]+/', (string)$rawChecklist, -1, PREG_SPLIT_NO_EMPTY);
        $items = array_values(array_unique(array_filter(array_map('trim', $items), fn($i) => $i !== '')));
        if (empty($items)) return;

        $cardsToSync = [$card];
        if ($card->sync_group_id) {
            $siblings = Card::where('sync_group_id', $card->sync_group_id)
                ->where('id', '!=', $card->id)
                ->get();
            foreach ($siblings as $s) {
                $cardsToSync[] = $s;
            }
        }

        foreach ($cardsToSync as $c) {
            $checklist = CardChecklist::where('card_id', $c->id)
                ->where(function($q) {
                    $q->where('title', 'Status')->orWhere('title', 'Checklist');
                })
                ->first();

            if (!$checklist) {
                $maxPos = CardChecklist::where('card_id', $c->id)->max('position') ?? 0;
                $checklist = CardChecklist::create([
                    'card_id'  => $c->id,
                    'title'    => 'Status',
                    'position' => $maxPos + 1,
                ]);
            } elseif ($checklist->title !== 'Status') {
                $checklist->update(['title' => 'Status']);
            }

            $existingItemContents = $checklist->items()->pluck('content')->map(fn($txt) => strtolower(trim($txt)))->toArray();
            $itemPos = $checklist->items()->max('position') ?? 0;

            foreach ($items as $itemText) {
                if (!in_array(strtolower($itemText), $existingItemContents)) {
                    $itemPos++;
                    $checklist->items()->create([
                        'content'          => $itemText,
                        'position'         => $itemPos,
                        'is_completed'     => false,
                        'assigned_user_id' => \App\Models\CardChecklistItem::detectUserIdForCard($itemText, $c),
                    ]);
                    $existingItemContents[] = strtolower($itemText);
                }
            }
        }

        if ($card->sync_group_id) {
            app(\App\Http\Controllers\Board\CardController::class)->syncChecklistsAcrossTwins($card);
        }
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
