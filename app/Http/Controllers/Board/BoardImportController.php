<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardChecklist;
use App\Models\CardChecklistItem;
use App\Models\CardFile;
use App\Models\Label;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * BoardImportController
 *
 * Handles bulk card import from CSV or Google Sheets into a Planning Board.
 * Three endpoints:
 *   GET  /{board:slug}/import/template  — Download blank CSV template
 *   POST /{board:slug}/import/preview   — Validate & return preview (no cards created)
 *   POST /{board:slug}/import/confirm   — Create cards after user confirmation
 */
class BoardImportController extends Controller
{
    /** Standard import columns in order */
    private const HEADERS = [
        'Title', 'Label', 'Description', 'Start Date', 'Due Date',
        'Assigned To', 'Attachment Link', 'Checklist', 'Week',
    ];

    // ── Template ──────────────────────────────────────────────────────────────

    /**
     * Download the standard CSV import template.
     */
    public function template(Board $board): Response
    {
        $headers = implode(',', self::HEADERS);
        $sample1 = 'Create Blog Article,Content,Write 1200-word article about Road Rollers,2026-06-10,2026-06-15,michael,https://drive.google.com/file/example,"Research;Draft;Review",Drafting';
        $sample2 = 'Design Banner,Graphic,Homepage promotional banner,2026-06-12,2026-06-18,jenny,https://drive.google.com/file/example,"Concept;Design;Approval",Drafting';

        $csv = implode("\n", [$headers, $sample1, $sample2]) . "\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="import-template.csv"',
        ]);
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    /**
     * Parse & validate a CSV or Google Sheets URL.
     * Returns a preview payload (no cards created).
     */
    public function preview(Request $request, Board $board): JsonResponse
    {
        $request->validate([
            'file'       => ['nullable', 'file', 'mimes:csv,txt', 'max:20480'],
            'sheets_url' => ['nullable', 'url'],
        ]);

        if (!$request->hasFile('file') && !$request->filled('sheets_url')) {
            return response()->json(['error' => 'Please provide a CSV file or a Google Sheets URL.'], 422);
        }

        $worksheetName = trim((string)$request->input('worksheet_name'));
        $explicitTargetListId = $request->input('target_list_id') ? (int)$request->input('target_list_id') : null;

        // ── 1. Get raw CSV content ────────────────────────────────────────
        if ($request->hasFile('file')) {
            $csvContent = file_get_contents($request->file('file')->getRealPath());
            if (empty($worksheetName)) {
                $worksheetName = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
            }
        } else {
            $csvContent = $this->fetchGoogleSheetsCsv($request->sheets_url, $worksheetName);
            if ($csvContent === null) {
                return response()->json([
                    'error' => 'Could not fetch the Google Sheet. Please ensure the sheet is shared as "Anyone with the link can view".',
                ], 422);
            }
            if (empty($worksheetName)) {
                $worksheetName = 'Imported Sheet';
            }
        }

        // ── 2. Parse CSV ──────────────────────────────────────────────────
        $rows = $this->parseCsv($csvContent);
        if (empty($rows)) {
            return response()->json(['error' => 'The file appears to be empty or could not be parsed.'], 422);
        }

        // ── 3. Map column indices ─────────────────────────────────────────
        $headerRow = array_map('trim', $rows[0]);
        $colMap    = $this->buildColumnMap($headerRow);

        if (!isset($colMap['Title'])) {
            return response()->json(['error' => 'The file is missing a required "Title" column.'], 422);
        }

        // ── 4. Validate each data row ─────────────────────────────────────
        $dataRows = array_slice($rows, 1);

        // Pre-load board context for validation
        $boardLists  = $board->activeLists()->pluck('id', 'name')->all();
        $firstListId = $board->activeLists()->orderBy('position')->value('id');

        // Robust user lookup for "Assigned To"
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

        // Build a composite key set: "title|due_date" for precise duplicate detection
        $existingCardKeys = Card::where('board_id', $board->id)
            ->whereNull('deleted_at')
            ->select('title', 'due_at')
            ->get()
            ->map(fn($c) => strtolower(trim($c->title)) . '|' . ($c->due_at ? \Carbon\Carbon::parse($c->due_at)->format('Y-m-d') : ''))
            ->all();

        $boardLabels = Label::where(function ($q) use ($board) {
            $q->whereNull('workspace_id')->whereNull('board_id')
              ->orWhere('workspace_id', $board->workspace_id)
              ->orWhere('board_id', $board->id);
        })
        ->get()
        ->mapWithKeys(fn($l) => [strtolower(trim($l->name)) => $l->id])
        ->all();

        $preview = [];
        $totalValid   = 0;
        $totalInvalid = 0;

        foreach ($dataRows as $idx => $rawRow) {
            // Skip fully blank rows
            if (count(array_filter($rawRow, fn($c) => trim($c) !== '')) === 0) {
                continue;
            }

            $row    = $this->mapRow($rawRow, $colMap);
            $errors = [];
            $warnings = [];

            // Title — required
            if (empty(trim($row['Title'] ?? ''))) {
                $errors[] = 'Title is required.';
            }

            // Start Date — optional but must be parseable
            if (!empty($row['Start Date'])) {
                $parsed = strtotime($row['Start Date']);
                if ($parsed === false) {
                    $errors[] = "Invalid start date format: \"{$row['Start Date']}\". Use YYYY-MM-DD.";
                }
            }

            // Due Date — optional but must be parseable
            if (!empty($row['Due Date'])) {
                $parsed = strtotime($row['Due Date']);
                if ($parsed === false) {
                    $errors[] = "Invalid due date format: \"{$row['Due Date']}\". Use YYYY-MM-DD.";
                }
            }

            // Label — auto-create if doesn't exist during confirm, support multi-select
            $labelId = null;
            $labelIds = [];
            if (!empty($row['Label'])) {
                $labelParts = preg_split('/[,&+\/\n]+/', $row['Label'], -1, PREG_SPLIT_NO_EMPTY);
                foreach ($labelParts as $lp) {
                    $labelName = strtolower(trim($lp));
                    if (isset($boardLabels[$labelName])) {
                        $labelIds[] = $boardLabels[$labelName];
                    }
                }
                $labelId = $labelIds[0] ?? null;
            }

            // Assigned To — robust match (multi-select support)
            $assignedUserId   = null;
            $assignedUserName = null;
            $assignedUserIds  = [];
            if (!empty($row['Assigned To'])) {
                $matchedUser = $this->resolveMembers($row['Assigned To'], $userLookup);
                if (empty($matchedUser['ids'])) {
                    $errors[] = "User \"{$row['Assigned To']}\" not found. Please check the name or username.";
                } else {
                    $assignedUserId   = $matchedUser['primary_id'];
                    $assignedUserIds  = $matchedUser['ids'];
                    $assignedUserName = $matchedUser['resolved_name'];
                    foreach ($matchedUser['warnings'] as $mw) {
                        $warnings[] = $mw;
                    }
                }
            }

            // Week — resolve to list ID (supports "Week2", "Week 2", "W2", explicit list, etc.)
            [$listId, $listName] = $this->resolveTargetList($row['Week'] ?? '', $worksheetName, $boardLists, $firstListId, $explicitTargetListId);

            // Duplicate detection — same title AND same due date means a true duplicate
            $dueDateNorm  = !empty($row['Due Date']) ? (strtotime($row['Due Date']) ? date('Y-m-d', strtotime($row['Due Date'])) : '') : '';
            $compositeKey = strtolower(trim($row['Title'] ?? '')) . '|' . $dueDateNorm;
            $isDuplicate  = in_array($compositeKey, $existingCardKeys);
            if ($isDuplicate) {
                $warnings[] = 'Duplicate skipped: a card with this exact title and due date already exists on the board.';
            }

            $isValid = empty($errors);
            if ($isValid) $totalValid++;
            else $totalInvalid++;

            $preview[] = [
                'row'               => $idx + 2, // 1-indexed, +1 for header
                'title'             => $row['Title'] ?? '',
                'label'             => $row['Label'] ?? '',
                'label_id'          => $labelId,
                'label_ids'         => $labelIds,
                'description'       => $row['Description'] ?? '',
                'due_date'          => $row['Due Date'] ?? '',
                'start_date'        => $row['Start Date'] ?? '',
                'assigned_to_raw'   => $row['Assigned To'] ?? '',
                'assigned_user_id'  => $assignedUserId,
                'assigned_user_ids' => $assignedUserIds,
                'assigned_name'     => $assignedUserName,
                'attachment_link'   => $row['Attachment Link'] ?? '',
                'checklist'         => $row['Checklist'] ?? '',
                'list_id'           => $listId,
                'list_name'         => $listName,
                'is_duplicate'      => $isDuplicate,
                'valid'             => $isValid,
                'errors'            => $errors,
                'warnings'          => $warnings,
            ];
        }

        return response()->json([
            'total'   => count($preview),
            'valid'   => $totalValid,
            'invalid' => $totalInvalid,
            'rows'    => $preview,
        ]);
    }

    // ── Confirm ───────────────────────────────────────────────────────────────

    /**
     * Create cards for all valid rows from the preview payload.
     */
    public function confirm(Request $request, Board $board): JsonResponse
    {
        $request->validate([
            'rows'              => ['required', 'array', 'min:1'],
            'rows.*.title'      => ['required', 'string', 'max:255'],
            'rows.*.valid'      => ['required', 'boolean'],
            'rows.*.list_id'    => ['required', 'integer', 'exists:board_lists,id'],
            'rows.*.label_id'   => ['nullable', 'integer', 'exists:labels,id'],
        ]);

        $rows    = collect($request->rows)->where('valid', true);
        $created = [];
        $skipped = 0;
        $skippedDuplicates = 0;

        // Build composite key set for real-time duplicate checking during import
        $importedKeys = [];
        $existingCardKeys = Card::where('board_id', $board->id)
            ->whereNull('deleted_at')
            ->select('title', 'due_at')
            ->get()
            ->map(fn($c) => strtolower(trim($c->title)) . '|' . ($c->due_at ? \Carbon\Carbon::parse($c->due_at)->format('Y-m-d') : ''))
            ->all();

        foreach ($rows as $row) {
            // ── 0. Skip confirmed duplicates (same title + same due date) ─
            $dueDateNorm  = !empty($row['due_date']) ? (strtotime($row['due_date']) ? date('Y-m-d', strtotime($row['due_date'])) : '') : '';
            $compositeKey = strtolower(trim($row['title'])) . '|' . $dueDateNorm;

            if (in_array($compositeKey, $existingCardKeys) || in_array($compositeKey, $importedKeys)) {
                $skippedDuplicates++;
                continue;
            }
            $importedKeys[] = $compositeKey;

            // ── 1. Position ───────────────────────────────────────────────
            $position = Card::where('board_list_id', $row['list_id'])->max('position') + 1;

            // ── 2. Dates ───────────────────────────────────────────────
            $dueAt = null;
            if (!empty($row['due_date'])) {
                $ts    = strtotime($row['due_date']);
                $dueAt = $ts ? date('Y-m-d H:i:s', $ts) : null;
            }
            
            $startAt = null;
            if (!empty($row['start_date'])) {
                $ts      = strtotime($row['start_date']);
                $startAt = $ts ? date('Y-m-d H:i:s', $ts) : null;
            }

            // ── 4. Create card ────────────────────────────────────────────
            $card = Card::create([
                'board_id'      => $board->id,
                'board_list_id' => $row['list_id'],
                'title'         => $row['title'],
                'description'   => $row['description'] ?? null,
                'priority'      => 'medium',
                'start_date'    => $startAt,
                'due_at'        => $dueAt,
                'status'        => 'todo',
                'position'      => $position,
                'created_by'    => auth()->id(),
            ]);

            // ── 5. Labels (Supports multi-select) ──────────────────────────
            $labelsToAttach = [];
            if (!empty($row['label_ids']) && is_array($row['label_ids'])) {
                $labelsToAttach = array_merge($labelsToAttach, $row['label_ids']);
            } elseif (!empty($row['label_id'])) {
                $labelsToAttach[] = $row['label_id'];
            }
            if (!empty($row['label'])) {
                $labelParts = preg_split('/[,&+\/\n]+/', (string)$row['label'], -1, PREG_SPLIT_NO_EMPTY);
                foreach ($labelParts as $lp) {
                    $lName = trim($lp);
                    if ($lName !== '') {
                        $newLabel = Label::firstOrCreate(
                            ['name' => $lName, 'workspace_id' => null, 'board_id' => null],
                            ['color' => '#10b981']
                        );
                        $labelsToAttach[] = $newLabel->id;
                    }
                }
            }
            if (!empty($labelsToAttach)) {
                $card->labels()->sync(array_unique($labelsToAttach));
            }

            // ── 6. Assignees (Supports multi-select) ───────────────────────
            $assigneesToAttach = [];
            if (!empty($row['assigned_user_ids']) && is_array($row['assigned_user_ids'])) {
                $assigneesToAttach = array_merge($assigneesToAttach, $row['assigned_user_ids']);
            } elseif (!empty($row['assigned_user_id'])) {
                $assigneesToAttach[] = $row['assigned_user_id'];
            } elseif (!empty($row['assigned_to_raw'])) {
                $matchedUser = $this->resolveMembers($row['assigned_to_raw'], $userLookup ?? []);
                $assigneesToAttach = array_merge($assigneesToAttach, $matchedUser['ids']);
            }
            if (!empty($assigneesToAttach)) {
                $assigneesData = [];
                foreach (array_unique($assigneesToAttach) as $uid) {
                    $assigneesData[$uid] = ['assigned_at' => now()];
                }
                $card->assignees()->sync($assigneesData);
            }

            // ── 7. Attachment links (supports named multi-links) ──────────
            if (!empty($row['attachment_link'])) {
                $this->attachLinksToCard($card, $row['attachment_link'], auth()->id() ?? 1);
            }

            // ── 8. Checklist (auto-creates "Status" checklist) ─────────────
            if (!empty($row['checklist'])) {
                $this->syncCardChecklist($card, $row['checklist']);
            }

            // ── 9. Activity log ───────────────────────────────────────────
            $this->logImportActivity($card, 'imported via CSV Import');

            $created[] = $this->formatCardForBoard($card);
        }

        $importedCount = count($created);

        // ── Send import notification to all board members ─────────────────
        if ($importedCount > 0) {
            try {
                \App\Notifications\BoardActivityNotification::send(
                    $board,
                    'cards_imported',
                    "imported **{$importedCount} card" . ($importedCount !== 1 ? 's' : '') . "** into **{$board->name}**",
                    null,
                    true
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Import notification failed: ' . $e->getMessage());
            }
        }

        $messageParts = ["{$importedCount} card" . ($importedCount !== 1 ? 's' : '') . ' imported successfully'];
        if ($skippedDuplicates > 0) {
            $messageParts[] = "{$skippedDuplicates} duplicate" . ($skippedDuplicates !== 1 ? 's' : '') . ' skipped (same title & date already exist)';
        }

        return response()->json([
            'created'            => $importedCount,
            'skipped'            => $skipped,
            'skipped_duplicates' => $skippedDuplicates,
            'cards'              => $created,
            'message'            => implode('. ', $messageParts) . '.',
        ], 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Fetch CSV data from a Google Sheets URL.
     * Supports gid, sheet tab name, and gviz CSV fallback.
     */
    private function fetchGoogleSheetsCsv(string $url, ?string $worksheetName = null): ?string
    {
        // Extract spreadsheet ID from various Google Sheets URL formats
        if (!preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9\-_]+)/', $url, $matches)) {
            return null;
        }
        $sheetId = $matches[1];

        // Extract optional gid (tab/sheet id) - supports query ?gid=, &gid=, and hash #gid=
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

        // Try gviz endpoint first (fastest and supports &sheet=)
        try {
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/gviz/tq?tqx=out:csv{$gidStr}{$sheetParam}";
            $response = Http::timeout(15)->get($csvUrl);
            if ($response->successful() && !empty(trim($response->body()))) {
                return $response->body();
            }
        } catch (\Throwable $e) {}

        // Fallback to standard export endpoint
        try {
            $exportUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv{$gidParam}";
            $response = Http::timeout(15)->get($exportUrl);
            if ($response->successful() && !empty(trim($response->body()))) {
                return $response->body();
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Parse a CSV string into an array of rows.
     */
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

    /**
     * Build a map of column name → array index from the header row.
     */
    private function buildColumnMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $idx => $col) {
            $clean = strtolower(trim($col));
            if ($clean === 'attachement') $clean = 'attachment';
            if (str_contains($clean, 'work task')) $clean = 'work task / content type';
            if (str_contains($clean, 'content type')) $clean = 'work task / content type';

            if ($clean === 'title') $map['Title'] = $idx;
            elseif ($clean === 'label' || $clean === 'labels' || $clean === 'cluster' || $clean === 'class' || $clean === 'brand' || $clean === 'team') $map['Label'] = $idx;
            elseif ($clean === 'description') $map['Description'] = $idx;
            elseif ($clean === 'start date' || str_contains($clean, 'start date') || str_contains($clean, 'public date') || str_contains($clean, 'publish date')) $map['Start Date'] = $idx;
            elseif ($clean === 'due date' || $clean === 'deadline' || $clean === 'due' || str_contains($clean, 'deadline date')) $map['Due Date'] = $idx;
            elseif (str_contains($clean, 'assigned to') || $clean === 'assign to' || $clean === 'member' || $clean === 'assigned') $map['Assigned To'] = $idx;
            elseif (str_contains($clean, 'assigned by') || $clean === 'assign by') $map['Assigned By'] = $idx;
            elseif (str_contains($clean, 'attach')) $map['Attachment Link'] = $idx;
            elseif (str_contains($clean, 'check') || $clean === 'checklist') $map['Checklist'] = $idx;
            elseif (in_array($clean, ['weeks', 'week', 'list', 'target list', 'week list', 'target week', 'board list']) || str_contains($clean, 'week') || str_contains($clean, 'target list')) $map['Week'] = $idx;
            else {
                foreach (self::HEADERS as $expected) {
                    if (strcasecmp(trim($col), $expected) === 0) {
                        $map[$expected] = $idx;
                        break;
                    }
                }
            }
        }
        return $map;
    }

    /**
     * Robust list resolution matching week number, list name, or fallback.
     * Supports dropdowns like "Week2", "Week 2", "W2", "Week-2", etc.
     */
    private function resolveTargetList(?string $weeksVal, ?string $worksheetName, array $boardLists, ?int $firstListId, ?int $explicitTargetListId = null): array
    {
        if ($explicitTargetListId && in_array($explicitTargetListId, $boardLists)) {
            $name = array_search($explicitTargetListId, $boardLists) ?: 'Selected list';
            return [$explicitTargetListId, $name];
        }

        $weeksVal = trim((string)$weeksVal);
        $worksheetName = trim((string)$worksheetName);

        // 1. Try matching from row's week value (e.g. "Week2", "Week 2", "W2", "2")
        if (!empty($weeksVal)) {
            // Exact case-insensitive match
            foreach ($boardLists as $name => $id) {
                if (strcasecmp(trim($name), $weeksVal) === 0) {
                    return [$id, $name];
                }
            }

            // Normalized alphanumeric match (e.g. "week2" == "week2", "week-2" == "week2")
            $normVal = preg_replace('/[^a-z0-9]/', '', strtolower($weeksVal));
            if (!empty($normVal)) {
                foreach ($boardLists as $name => $id) {
                    $normListName = preg_replace('/[^a-z0-9]/', '', strtolower($name));
                    if ($normVal === $normListName) {
                        return [$id, $name];
                    }
                }
            }

            // Week number extraction (supports "Week 2", "Week2", "W2", "Week-2", "2", "Week 02")
            if (preg_match('/(?:week|w)?\s*[\-_]?\s*(\d+)/i', $weeksVal, $m)) {
                $weekNum = (int)$m[1];
                foreach ($boardLists as $name => $id) {
                    if (preg_match('/(?:week|w)\s*[\-_]?\s*' . $weekNum . '\b/i', $name)) {
                        return [$id, $name];
                    }
                }
            }

            // Urgent / Priority
            if (stripos($weeksVal, 'urgent') !== false || stripos($weeksVal, 'priority') !== false) {
                foreach ($boardLists as $name => $id) {
                    if (stripos($name, 'urgent') !== false || stripos($name, 'priority') !== false) {
                        return [$id, $name];
                    }
                }
            }
        }

        // 2. Try matching from worksheet name (e.g. "Week 4", "Week4-Sep", "September")
        if (!empty($worksheetName)) {
            foreach ($boardLists as $name => $id) {
                if (strcasecmp(trim($name), $worksheetName) === 0) {
                    return [$id, $name];
                }
            }

            $normWs = preg_replace('/[^a-z0-9]/', '', strtolower($worksheetName));
            if (!empty($normWs)) {
                foreach ($boardLists as $name => $id) {
                    $normListName = preg_replace('/[^a-z0-9]/', '', strtolower($name));
                    if ($normWs === $normListName) {
                        return [$id, $name];
                    }
                }
            }

            if (preg_match('/(?:week|w)\s*[\-_]?\s*(\d+)/i', $worksheetName, $m) || preg_match('/\b(?:week|w)?\s*(\d+)\b/i', $worksheetName, $m)) {
                $weekNum = (int)$m[1];
                foreach ($boardLists as $name => $id) {
                    if (preg_match('/(?:week|w)\s*[\-_]?\s*' . $weekNum . '\b/i', $name)) {
                        return [$id, $name];
                    }
                }
            }
        }

        // 3. Fallback to first list on board
        if ($firstListId) {
            $firstName = array_search($firstListId, $boardLists) ?: 'First list';
            return [$firstListId, $firstName];
        }

        return [0, 'First list'];
    }

    // ── Helper: Map row values to column names ────────────────────────────────

    public function resolveSingleMember(string $rawName, array $userLookup): array
    {
        $rawName = trim($rawName);
        if (empty($rawName) || in_array(strtolower($rawName), ['none', 'n/a', '-', 'blank', 'no member', 'unassigned'])) {
            return ['id' => null, 'name' => null, 'warning' => null];
        }

        $cleanAlpha = fn(string $s) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $s));

        $search = strtolower($rawName);
        if (isset($userLookup[$search])) {
            return ['id' => $userLookup[$search]['id'], 'name' => $userLookup[$search]['name'], 'warning' => null];
        }

        // Strip honorifics & bracketed tags
        $core = preg_replace('/^(Mr\.|Ms\.|Mrs\.|Miss|Dr\.)\s*/i', '', $rawName);
        $core = preg_replace('/\s*\(.*?\)/', '', $core);
        $core = preg_replace('/\s*\[.*?\]/', '', $core);
        $core = trim(strtolower($core));

        if (isset($userLookup[$core])) {
            return ['id' => $userLookup[$core]['id'], 'name' => $userLookup[$core]['name'], 'warning' => null];
        }

        $normCore = $cleanAlpha($core);
        if (!empty($normCore)) {
            foreach ($userLookup as $key => $data) {
                if ($cleanAlpha($key) === $normCore) {
                    return ['id' => $data['id'], 'name' => $data['name'], 'warning' => null];
                }
            }
        }

        // Fallback whole-word / token match
        if (strlen($core) >= 3) {
            foreach ($userLookup as $key => $data) {
                if (preg_match('/\b' . preg_quote($core, '/') . '\b/i', $key)) {
                    return ['id' => $data['id'], 'name' => $data['name'], 'warning' => null];
                }
            }
        }

        return ['id' => null, 'name' => $rawName, 'warning' => "Member \"$rawName\" could not be matched."];
    }

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
            if (!empty($res['id'])) {
                if (!in_array($res['id'], $ids)) {
                    $ids[] = $res['id'];
                    $resolvedNames[] = $res['name'];
                }
            } else {
                if (!empty($res['name']) && !in_array($res['name'], $resolvedNames)) {
                    $resolvedNames[] = $res['name'];
                }
                if (!empty($res['warning'])) {
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

    private function resolveMember($rawValue, $userLookup)
    {
        $res = $this->resolveMembers($rawValue, $userLookup);
        return [
            'id' => $res['primary_id'],
            'name' => $res['resolved_name'],
            'ids' => $res['ids'],
            'resolved_names' => $res['resolved_names'],
            'warnings' => $res['warnings'],
        ];
    }

    private function mapRow(array $rawRow, array $colMap): array
    {
        $result = [];
        foreach (self::HEADERS as $col) {
            $idx          = $colMap[$col] ?? null;
            $result[$col] = ($idx !== null && isset($rawRow[$idx])) ? trim($rawRow[$idx]) : '';
        }
        return $result;
    }

    /**
     * Generate a deterministic color for a new label from its name.
     */
    private function randomLabelColor(string $name): string
    {
        $palette = [
            '#4f46e5', '#0891b2', '#16a34a', '#dc2626', '#d97706',
            '#7c3aed', '#db2777', '#0d9488', '#ea580c', '#6366f1',
        ];
        return $palette[abs(crc32(strtolower($name))) % count($palette)];
    }

    /**
     * Log an activity on the imported card.
     */
    private function logImportActivity(Card $card, string $description): void
    {
        ActivityLog::create([
            'user_id'      => auth()->id(),
            'subject_type' => Card::class,
            'subject_id'   => $card->id,
            'action'       => 'imported',
            'description'  => $description,
        ]);
    }

    /**
     * Format a Card for the Alpine.js board data structure.
     */
    private function formatCardForBoard(Card $card): array
    {
        $card->loadMissing(['assignees', 'labels', 'checklists.items', 'files']);

        return [
            'id'               => $card->id,
            'board_id'         => $card->board_id,
            'board_list_id'    => $card->board_list_id,
            'title'            => $card->title,
            'description'      => $card->description,
            'priority'         => $card->priority?->value ?? $card->priority ?? 'medium',
            'status'           => $card->status?->value ?? $card->status ?? 'todo',
            'due_at'           => $card->due_at?->toISOString(),
            'position'         => $card->position,
            'is_archived'      => (bool) $card->is_archived,
            'labels'           => $card->labels->map(fn($l) => [
                'id'    => $l->id,
                'name'  => $l->name,
                'color' => $l->color,
            ])->values()->all(),
            'assignees'        => $card->assignees->map(fn($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'avatar'       => $u->avatar_url,
                'initials'     => $u->avatar_initials,
                'avatar_color' => $u->avatar_color,
            ])->values()->all(),
            'checklist_total'  => $card->checklists->flatMap->items->count(),
            'checklist_done'   => $card->checklists->flatMap->items->where('is_completed', true)->count(),
            'has_files'        => $card->files->isNotEmpty(),
            'comment_count'    => 0,
            'cover_image'      => $card->cover_image,
            'sync_group_id'    => $card->sync_group_id,
        ];
    }

    /**
     * Parse multi-link attachments with names.
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
    }
}
