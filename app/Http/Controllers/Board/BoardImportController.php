<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardChecklist;
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

        // ── 1. Get raw CSV content ────────────────────────────────────────
        if ($request->hasFile('file')) {
            $csvContent = file_get_contents($request->file('file')->getRealPath());
        } else {
            $csvContent = $this->fetchGoogleSheetsCsv($request->sheets_url);
            if ($csvContent === null) {
                return response()->json([
                    'error' => 'Could not fetch the Google Sheet. Please ensure the sheet is shared as "Anyone with the link can view".',
                ], 422);
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

            // Label — must exist on the board if provided
            $labelId = null;
            if (!empty($row['Label'])) {
                $labelName = strtolower(trim($row['Label']));
                if (isset($boardLabels[$labelName])) {
                    $labelId = $boardLabels[$labelName];
                } else {
                    $errors[] = "Label \"{$row['Label']}\" does not exist. Please create it first.";
                }
            }

            // Assigned To — match by username
            $assignedUserId   = null;
            $assignedUserName = null;
            if (!empty($row['Assigned To'])) {
                $user = User::where('username', $row['Assigned To'])->first();
                if (!$user) {
                    $errors[] = "Username \"{$row['Assigned To']}\" not found. Please check the username.";
                } else {
                    $assignedUserId   = $user->id;
                    $assignedUserName = $user->name;
                }
            }

            // Week — resolve to list ID
            $listId   = $firstListId;
            $listName = array_search($firstListId, $boardLists) ?: 'First list';
            if (!empty($row['Week'])) {
                $matchedListId = null;
                foreach ($boardLists as $name => $id) {
                    if (strcasecmp(trim($name), trim($row['Week'])) === 0) {
                        $matchedListId = $id;
                        $listName = $name;
                        break;
                    }
                }
                if ($matchedListId) {
                    $listId = $matchedListId;
                } else {
                    $warnings[] = "Week \"{$row['Week']}\" does not match any list. Card will be placed in the first list.";
                    $listName = array_search($firstListId, $boardLists) ?: 'First list';
                }
            }

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
                'row'              => $idx + 2, // 1-indexed, +1 for header
                'title'            => $row['Title'] ?? '',
                'label'            => $row['Label'] ?? '',
                'label_id'         => $labelId,
                'description'      => $row['Description'] ?? '',
                'due_date'         => $row['Due Date'] ?? '',
                'start_date'       => $row['Start Date'] ?? '',
                'assigned_to_raw'  => $row['Assigned To'] ?? '',
                'assigned_user_id' => $assignedUserId,
                'assigned_name'    => $assignedUserName,
                'attachment_link'  => $row['Attachment Link'] ?? '',
                'checklist'        => $row['Checklist'] ?? '',
                'list_id'          => $listId,
                'list_name'        => $listName,
                'is_duplicate'     => $isDuplicate,
                'valid'            => $isValid,
                'errors'           => $errors,
                'warnings'         => $warnings,
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

            // ── 5. Label ──────────────────────────────────────────────────
            if (!empty($row['label_id'])) {
                $card->labels()->attach($row['label_id']);
            }

            // ── 6. Assignee ───────────────────────────────────────────────
            if (!empty($row['assigned_user_id'])) {
                $card->assignees()->attach($row['assigned_user_id'], ['assigned_at' => now()]);
            }

            // ── 7. Attachment link ────────────────────────────────────────
            if (!empty($row['attachment_link']) && filter_var($row['attachment_link'], FILTER_VALIDATE_URL)) {
                $linkName = $row['attachment_link'];
                // Use a readable label like "Drive Link" for Google Drive URLs
                if (str_contains($linkName, 'drive.google.com')) {
                    $linkName = 'Google Drive Link';
                } elseif (str_contains($linkName, 'docs.google.com')) {
                    $linkName = 'Google Docs Link';
                } else {
                    $parsed = parse_url($row['attachment_link']);
                    $linkName = ($parsed['host'] ?? '') ?: 'Attachment Link';
                }
                \App\Models\CardFile::create([
                    'card_id'       => $card->id,
                    'original_name' => $linkName,
                    'stored_name'   => $linkName,
                    'path'          => $row['attachment_link'],
                    'mime_type'     => 'text/uri-list',
                    'size'          => 0,
                    'disk'          => 'url',
                    'uploaded_by'   => auth()->id(),
                ]);
            }

            // ── 8. Checklist ──────────────────────────────────────────────
            if (!empty($row['checklist'])) {
                $items = array_filter(array_map('trim', explode(';', $row['checklist'])));
                if (!empty($items)) {
                    $checklist = $card->checklists()->create([
                        'title'    => 'Checklist',
                        'position' => 1,
                    ]);
                    foreach ($items as $pos => $itemText) {
                        $checklist->items()->create([
                            'content'  => $itemText,
                            'position' => $pos + 1,
                        ]);
                    }
                }
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
     * Converts the share/view URL to an export URL.
     */
    private function fetchGoogleSheetsCsv(string $url): ?string
    {
        // Extract spreadsheet ID from various Google Sheets URL formats
        if (!preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9\-_]+)/', $url, $matches)) {
            return null;
        }
        $sheetId = $matches[1];

        // Extract optional gid (tab/sheet id)
        $gid = null;
        if (preg_match('/[?&]gid=(\d+)/', $url, $gidMatches)) {
            $gid = $gidMatches[1];
        }

        $exportUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv";
        if ($gid !== null) {
            $exportUrl .= "&gid={$gid}";
        }

        try {
            $response = Http::timeout(15)->get($exportUrl);
            if ($response->successful()) {
                return $response->body();
            }
        } catch (\Exception $e) {
            // Fall through to return null
        }

        return null;
    }

    /**
     * Parse a CSV string into an array of rows.
     */
    private function parseCsv(string $content): array
    {
        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines   = explode("\n", trim($content));

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $rows[] = str_getcsv($line);
        }

        return $rows;
    }

    /**
     * Build a map of column name → array index from the header row.
     */
    private function buildColumnMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $idx => $col) {
            $clean = trim($col);
            foreach (self::HEADERS as $expected) {
                if (strcasecmp($clean, $expected) === 0) {
                    $map[$expected] = $idx;
                    break;
                }
            }
        }
        return $map;
    }

    /**
     * Map a raw CSV row array to a named field array using the column map.
     */
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
}
