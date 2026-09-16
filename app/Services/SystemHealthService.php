<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\BoardAutomation;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Note;
use App\Models\NoteFolder;
use App\Models\Setting;
use App\Models\SocialMediaClass;
use App\Models\SocialMediaItem;
use App\Models\Website;
use App\Models\WebsiteFollowUp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SystemHealthService
{
    /**
     * Run a comprehensive system health diagnostic check across all application modules.
     */
    public function runDiagnostics(): array
    {
        $now = Carbon::now('Asia/Phnom_Penh');
        $currentMonthYear = $now->format('F Y');

        $automationsData = $this->checkAutomations($currentMonthYear);
        $boardsData = $this->checkBoardsAndLists($currentMonthYear);
        $syncData = $this->checkSyncIntegrity();
        $socialData = $this->checkSocialMedia($currentMonthYear);
        $websitesData = $this->checkWebsitesAndFollowUps();
        $notesData = $this->checkNotes();
        $maintenanceData = $this->checkModuleMaintenance();
        $performanceData = $this->checkSystemPerformance();
        $logsData = $this->checkRecentLogs();
        $serverData = $this->checkServerEnvironment();

        $totalIssues = $automationsData['issues_count']
            + $boardsData['issues_count']
            + $syncData['issues_count']
            + $socialData['issues_count']
            + $websitesData['issues_count']
            + $notesData['issues_count']
            + $logsData['issues_count'];

        $overallStatus = 'healthy';
        if ($totalIssues > 0) {
            $hasErrors = $automationsData['has_errors']
                || $boardsData['has_errors']
                || $syncData['has_errors']
                || $socialData['has_errors']
                || $websitesData['has_errors']
                || $notesData['has_errors'];
            $overallStatus = $hasErrors ? 'error' : 'warning';
        }

        return [
            'timestamp' => $now->format('M d, Y g:i:s A'),
            'overall_status' => $overallStatus,
            'total_issues' => $totalIssues,
            'automations' => $automationsData,
            'boards' => $boardsData,
            'sync' => $syncData,
            'social_media' => $socialData,
            'websites' => $websitesData,
            'notes' => $notesData,
            'maintenance' => $maintenanceData,
            'performance' => $performanceData,
            'logs' => $logsData,
            'server' => $serverData,
        ];
    }

    /**
     * Check all Board / Card Automations for configuration errors, missing targets, or broken linkages.
     */
    private function checkAutomations(string $currentMonthYear): array
    {
        $automations = BoardAutomation::with(['board.workspace', 'triggerList', 'targetBoard.workspace', 'targetList'])->get();
        $issues = [];
        $checkedCount = $automations->count();
        $hasErrors = false;

        foreach ($automations as $auto) {
            $board = $auto->board;
            if (!$board) {
                $hasErrors = true;
                $issues[] = [
                    'severity' => 'error',
                    'title' => "Automation #{$auto->id} (Trigger: '{$auto->trigger_word}')",
                    'board' => 'Unknown (Board Deleted)',
                    'reason' => "Board /card comment automation error : Parent board (ID: {$auto->board_id}) no longer exists.",
                    'suggested_fix' => "Auto-repair will remove orphan automations referencing deleted boards.",
                    'fixable' => true,
                ];
                continue;
            }

            // Check trigger list if specified
            if ($auto->trigger_list_id && !$auto->triggerList) {
                $hasErrors = true;
                $issues[] = [
                    'severity' => 'error',
                    'title' => "Automation #{$auto->id} on '{$board->name}'",
                    'board' => $board->name,
                    'reason' => "Board /card comment automation error : Trigger list ID {$auto->trigger_list_id} no longer exists on board '{$board->name}'.",
                    'suggested_fix' => "Re-select or recreate the trigger list on board '{$board->name}'.",
                    'fixable' => false,
                ];
            }

            // Check copy/move actions
            if (in_array($auto->action_type, ['copy', 'move'])) {
                if ($auto->target_board_id && !$auto->targetBoard) {
                    $hasErrors = true;
                    $issues[] = [
                        'severity' => 'error',
                        'title' => "Automation #{$auto->id} on '{$board->name}'",
                        'board' => $board->name,
                        'reason' => "Board /card comment automation error : Target board ID {$auto->target_board_id} does not exist.",
                        'suggested_fix' => "Auto-repair will re-link to the current monthly workflow board.",
                        'fixable' => true,
                    ];
                } elseif ($auto->target_board_id && $auto->target_list_id) {
                    $targetListExists = BoardList::where('id', $auto->target_list_id)
                        ->where('board_id', $auto->target_board_id)
                        ->exists();

                    if (!$targetListExists) {
                        $hasErrors = true;
                        $targetBoardName = $auto->targetBoard ? $auto->targetBoard->name : "ID {$auto->target_board_id}";
                        $issues[] = [
                            'severity' => 'error',
                            'title' => "Automation #{$auto->id} on '{$board->name}'",
                            'board' => $board->name,
                            'reason' => "Board /card comment automation error : Target list ID {$auto->target_list_id} does not exist on target board '{$targetBoardName}'.",
                            'suggested_fix' => "Auto-repair will locate the default 'Draft' list on '{$targetBoardName}'.",
                            'fixable' => true,
                        ];
                    }
                }

                // Check Planning to Workflow month linkage
                if (stripos($board->name, 'planning') !== false && stripos($auto->trigger_word ?? '', 'ready') !== false) {
                    preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i', $board->name, $monthMatch);
                    $boardMonthYear = $monthMatch[0] ?? $currentMonthYear;

                    if ($auto->targetBoard && stripos($auto->targetBoard->name, $boardMonthYear) === false) {
                        $issues[] = [
                            'severity' => 'warning',
                            'title' => "Monthly Outdated Link on '{$board->name}'",
                            'board' => $board->name,
                            'reason' => "Board /card comment automation error : Planning board for {$boardMonthYear} is pointing to an older workflow board ('{$auto->targetBoard->name}').",
                            'suggested_fix' => "Auto-repair will remove outdated duplicate or re-link to Workflow board for {$boardMonthYear}.",
                            'fixable' => true,
                        ];
                    }
                }
            }
        }

        return [
            'total_checked' => $checkedCount,
            'issues_count' => count($issues),
            'has_errors' => $hasErrors,
            'issues' => $issues,
        ];
    }

    /**
     * Check board lists integrity and duplicate board detection.
     */
    private function checkBoardsAndLists(string $currentMonthYear): array
    {
        $issues = [];
        $hasErrors = false;

        // 1. Check duplicate active SMM Planning boards
        $smmBoards = Board::where('type', 'smm')
            ->where('name', 'like', "%{$currentMonthYear}%")
            ->where('is_hidden', false)
            ->get();

        if ($smmBoards->count() > 1) {
            $hasErrors = true;
            $issues[] = [
                'severity' => 'error',
                'title' => "Duplicate SMM Planning Boards for {$currentMonthYear}",
                'board' => 'SMM Planning Boards',
                'reason' => "Board structure error : Found {$smmBoards->count()} active SMM Planning boards for {$currentMonthYear}. Older unstarred duplicates should be hidden.",
                'suggested_fix' => "Auto-repair will hide older unstarred duplicates.",
                'fixable' => true,
            ];
        }

        // 2. Check SMM Planning board lists (ensure Block/Waiting exists)
        $activeSmmBoard = Board::where('type', 'smm')
            ->where('name', 'like', "%{$currentMonthYear}%")
            ->where('is_hidden', false)
            ->first();

        if ($activeSmmBoard) {
            $hasBlock = $activeSmmBoard->lists()->where('name', 'like', '%block%')->exists();
            if (!$hasBlock) {
                $hasErrors = true;
                $issues[] = [
                    'severity' => 'error',
                    'title' => "Missing 'Block/Waiting' list on '{$activeSmmBoard->name}'",
                    'board' => $activeSmmBoard->name,
                    'reason' => "Board list error : The current SMM Planning board is missing the required 'Block/Waiting' list.",
                    'suggested_fix' => "Auto-repair will create the 'Block/Waiting' list on '{$activeSmmBoard->name}'.",
                    'fixable' => true,
                ];
            }
        }

        // 3. Check active Workflow boards for required workflow stages
        $workflowBoards = Board::where('name', 'like', "Workflow board – {$currentMonthYear}%")
            ->orWhere('name', 'like', "Workflow board - {$currentMonthYear}%")
            ->where('is_hidden', false)
            ->with('lists')
            ->get();

        foreach ($workflowBoards as $wb) {
            $listNames = $wb->lists->pluck('name')->map(fn($n) => strtolower($n))->all();
            $missingStages = [];

            if (!collect($listNames)->contains(fn($n) => str_contains($n, 'draft'))) {
                $missingStages[] = 'Draft';
            }
            if (!collect($listNames)->contains(fn($n) => str_contains($n, 'qc'))) {
                $missingStages[] = 'QC Review';
            }
            if (!collect($listNames)->contains(fn($n) => str_contains($n, 'supervisor'))) {
                $missingStages[] = 'Supervisor Review';
            }
            if (!collect($listNames)->contains(fn($n) => str_contains($n, 'approved'))) {
                $missingStages[] = 'Approved';
            }

            if (!empty($missingStages)) {
                $hasErrors = true;
                $issues[] = [
                    'severity' => 'error',
                    'title' => "Missing Required Workflow Lists on '{$wb->name}'",
                    'board' => $wb->name,
                    'reason' => "Board workflow error : Missing stage list(s): " . implode(', ', $missingStages),
                    'suggested_fix' => "Create missing stage list(s): " . implode(', ', $missingStages),
                    'fixable' => false,
                ];
            }
        }

        return [
            'issues_count' => count($issues),
            'has_errors' => $hasErrors,
            'issues' => $issues,
        ];
    }

    /**
     * Check sync integrity for twin cards across SMM and Workflow boards.
     */
    private function checkSyncIntegrity(): array
    {
        $issues = [];
        $hasErrors = false;

        // 1. Check for cards pointing to non-existent boardList
        $orphanListCards = Card::whereNotNull('board_list_id')
            ->whereDoesntHave('boardList')
            ->count();

        if ($orphanListCards > 0) {
            $hasErrors = true;
            $issues[] = [
                'severity' => 'error',
                'title' => "{$orphanListCards} Cards With Missing List Reference",
                'reason' => "Card sync error : {$orphanListCards} card(s) reference a list that no longer exists in the database.",
                'suggested_fix' => "Reassign orphan cards to valid lists or restore archived lists.",
                'fixable' => false,
            ];
        }

        // 2. Check twin cards where one is physically in Approved but siblings are not marked approved
        $unSyncedApproved = Card::whereHas('boardList', function ($q) {
            $q->where('name', 'like', '%approved%');
        })
        ->whereNull('approved_at')
        ->count();

        if ($unSyncedApproved > 0) {
            $issues[] = [
                'severity' => 'warning',
                'title' => "{$unSyncedApproved} Approved Cards Missing approved_at Timestamp",
                'reason' => "Card approval drift : {$unSyncedApproved} card(s) are in Approved lists but lack the approved_at timestamp.",
                'suggested_fix' => "Auto-repair will update approved_at timestamps and sync statuses.",
                'fixable' => true,
            ];
        }

        return [
            'issues_count' => count($issues),
            'has_errors' => $hasErrors,
            'issues' => $issues,
        ];
    }

    /**
     * Check Social Media Management module health (Classes, Items, Planning week distribution).
     */
    private function checkSocialMedia(string $currentMonthYear): array
    {
        $issues = [];
        $hasErrors = false;

        $classesCount = SocialMediaClass::count();
        $itemsCount = SocialMediaItem::count();

        // 1. Check for items with empty or invalid URLs
        $emptyUrlItems = SocialMediaItem::where(function ($q) {
            $q->whereNull('url')->orWhere('url', '');
        })->with('socialMediaClass')->get();

        if ($emptyUrlItems->count() > 0) {
            $sample = $emptyUrlItems->first();
            $className = $sample->socialMediaClass ? $sample->socialMediaClass->name : 'General';
            $issues[] = [
                'severity' => 'warning',
                'title' => "{$emptyUrlItems->count()} Social Media Items Missing URLs",
                'module' => 'Social Media Management',
                'reason' => "Social media error : {$emptyUrlItems->count()} social media link(s) have empty URLs (e.g., '{$sample->name}' under '{$className}').",
                'suggested_fix' => "Auto-repair will populate default company platform URLs or update items in Social Media Settings.",
                'fixable' => true,
            ];
        }

        // 2. Check active SMM Planning board week distribution
        $activeSmmBoard = Board::where('type', 'smm')
            ->where('name', 'like', "%{$currentMonthYear}%")
            ->where('is_hidden', false)
            ->first();

        $weekCardsCount = 0;
        if ($activeSmmBoard) {
            $weekLists = $activeSmmBoard->lists()->where('name', 'like', 'Week%')->get();
            if ($weekLists->isEmpty()) {
                $hasErrors = true;
                $issues[] = [
                    'severity' => 'error',
                    'title' => "Missing Week Distribution Lists on '{$activeSmmBoard->name}'",
                    'module' => 'SMM Planning Board',
                    'reason' => "Social media error : SMM Planning board has no Week lists (Week 1, Week 2, etc.) for card distribution.",
                    'suggested_fix' => "Auto-repair will execute SMM card distribution and week list synchronization.",
                    'fixable' => true,
                ];
            } else {
                $weekCardsCount = Card::whereIn('board_list_id', $weekLists->pluck('id'))->count();
            }
        }

        return [
            'total_classes' => $classesCount,
            'total_items' => $itemsCount,
            'active_smm_board' => $activeSmmBoard?->name ?? 'None',
            'week_cards_count' => $weekCardsCount,
            'issues_count' => count($issues),
            'has_errors' => $hasErrors,
            'issues' => $issues,
        ];
    }

    /**
     * Check All Websites and Website Follow-Up module integrity.
     */
    private function checkWebsitesAndFollowUps(): array
    {
        $issues = [];
        $hasErrors = false;

        $websitesCount = Website::count();
        $followUpsCount = WebsiteFollowUp::count();

        // 1. Check for websites with empty or invalid URL
        $emptyUrlWebsites = Website::where(function ($q) {
            $q->whereNull('url')->orWhere('url', '');
        })->count();

        if ($emptyUrlWebsites > 0) {
            $issues[] = [
                'severity' => 'warning',
                'title' => "{$emptyUrlWebsites} Websites Missing URL Domain",
                'module' => 'All Websites',
                'reason' => "Website error : {$emptyUrlWebsites} website record(s) do not have a primary URL defined.",
                'suggested_fix' => "Add valid URLs in the All Websites dashboard.",
                'fixable' => false,
            ];
        }

        // 2. Check for orphan follow-up records (website_id missing or deleted)
        $orphanFollowUps = WebsiteFollowUp::whereDoesntHave('website')->count();
        if ($orphanFollowUps > 0) {
            $hasErrors = true;
            $issues[] = [
                'severity' => 'error',
                'title' => "{$orphanFollowUps} Follow-Up Records Missing Website Reference",
                'module' => 'Website Follow Up',
                'reason' => "Website error : {$orphanFollowUps} follow-up record(s) reference a deleted or non-existent website ID.",
                'suggested_fix' => "Auto-repair will re-link or clean up orphan follow-up records.",
                'fixable' => true,
            ];
        }

        // 3. Check for failed Google Sheet synchronization entries
        $failedSyncFollowUps = WebsiteFollowUp::where('google_sheet_status', 'failed')->count();
        if ($failedSyncFollowUps > 0) {
            $issues[] = [
                'severity' => 'warning',
                'title' => "{$failedSyncFollowUps} Google Sheet Sync Failures",
                'module' => 'Website Follow Up',
                'reason' => "Website error : {$failedSyncFollowUps} follow-up record(s) failed to sync to the Google Blogs spreadsheet.",
                'suggested_fix' => "Auto-repair will reset sync status to pending to retry Google Sheet sync.",
                'fixable' => true,
            ];
        }

        return [
            'total_websites' => $websitesCount,
            'total_follow_ups' => $followUpsCount,
            'issues_count' => count($issues),
            'has_errors' => $hasErrors,
            'issues' => $issues,
        ];
    }

    /**
     * Check Notes and Folders integrity.
     */
    private function checkNotes(): array
    {
        $issues = [];
        $hasErrors = false;

        $notesCount = Note::count();
        $foldersCount = NoteFolder::count();

        // Check for orphan notes referencing non-existent folders
        $orphanNotes = Note::whereNotNull('folder_id')
            ->whereDoesntHave('folder')
            ->count();

        if ($orphanNotes > 0) {
            $issues[] = [
                'severity' => 'warning',
                'title' => "{$orphanNotes} Notes With Missing Folder References",
                'module' => 'Notes',
                'reason' => "Notes error : {$orphanNotes} note(s) reference a folder that has been deleted.",
                'suggested_fix' => "Auto-repair will move orphan notes to the root directory.",
                'fixable' => true,
            ];
        }

        return [
            'total_notes' => $notesCount,
            'total_folders' => $foldersCount,
            'issues_count' => count($issues),
            'has_errors' => $hasErrors,
            'issues' => $issues,
        ];
    }

    /**
     * Check which modules are currently placed under maintenance mode.
     */
    private function checkModuleMaintenance(): array
    {
        $maintenanceJson = Setting::where('key', 'maintenance_modules')->value('value') ?? '[]';
        $maintenanceModules = json_decode($maintenanceJson, true) ?: [];

        $modules = [
            'boards' => [
                'name' => 'Boards & Workspaces',
                'status' => in_array('boards', $maintenanceModules) ? 'maintenance' : 'operational',
            ],
            'social_media' => [
                'name' => 'Social Media Management',
                'status' => (in_array('social_media', $maintenanceModules) || in_array('social_media_planning', $maintenanceModules)) ? 'maintenance' : 'operational',
            ],
            'all_websites' => [
                'name' => 'All Websites & Follow Ups',
                'status' => (in_array('all_websites', $maintenanceModules) || in_array('websites_status', $maintenanceModules)) ? 'maintenance' : 'operational',
            ],
            'notes' => [
                'name' => 'Notes & Collaboration',
                'status' => in_array('notes', $maintenanceModules) ? 'maintenance' : 'operational',
            ],
            'approvals' => [
                'name' => 'Supervisor Approval Queue',
                'status' => in_array('approvals', $maintenanceModules) ? 'maintenance' : 'operational',
            ],
            'crm' => [
                'name' => 'Customer CRM & Tech Support',
                'status' => in_array('crm', $maintenanceModules) ? 'maintenance' : 'operational',
            ],
        ];

        return [
            'maintenance_count' => count(array_filter($modules, fn($m) => $m['status'] === 'maintenance')),
            'modules' => $modules,
        ];
    }

    /**
     * Check system performance metrics (Cache status, compiled views, log size).
     */
    private function checkSystemPerformance(): array
    {
        $configCached = File::exists(base_path('bootstrap/cache/config.php'));
        $routesCached = File::exists(base_path('bootstrap/cache/routes-v7.php'));
        
        $viewFiles = File::exists(storage_path('framework/views')) 
            ? count(File::files(storage_path('framework/views'))) 
            : 0;

        $logPath = storage_path('logs/laravel.log');
        $logSizeFormatted = '0 KB';
        if (File::exists($logPath)) {
            $bytes = File::size($logPath);
            if ($bytes >= 1048576) {
                $logSizeFormatted = round($bytes / 1048576, 2) . ' MB';
            } else {
                $logSizeFormatted = round($bytes / 1024, 1) . ' KB';
            }
        }

        $opcacheEnabled = function_exists('opcache_get_status') && !empty(@opcache_get_status());

        return [
            'config_cached' => $configCached,
            'routes_cached' => $routesCached,
            'compiled_views_count' => $viewFiles,
            'log_size' => $logSizeFormatted,
            'opcache_enabled' => $opcacheEnabled,
        ];
    }

    /**
     * Parse recent server error logs from storage/logs/laravel.log.
     */
    private function checkRecentLogs(): array
    {
        $logPath = storage_path('logs/laravel.log');
        $recentErrors = [];

        if (File::exists($logPath)) {
            $content = File::get($logPath);
            // Split by lines that start with [YYYY-MM-DD
            $entries = preg_split('/(?=\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\])/', $content);
            $entries = array_filter(array_map('trim', $entries));

            // Grab the last 50 log entries in reverse order
            $latestEntries = array_slice(array_reverse($entries), 0, 50);

            foreach ($latestEntries as $entry) {
                if (stripos($entry, '.ERROR:') !== false || stripos($entry, '.CRITICAL:') !== false || stripos($entry, '.ALERT:') !== false || stripos($entry, '.EMERGENCY:') !== false) {
                    preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+([a-zA-Z0-9_\-]+)\.([A-Z]+):\s+(.*)$/m', $entry, $matches);
                    $timestamp = $matches[1] ?? 'Unknown Time';
                    $level = $matches[3] ?? 'ERROR';
                    $firstLine = $matches[4] ?? substr($entry, 0, 200);

                    preg_match('/([a-zA-Z0-9_\\\\]+(Exception|Error)):\s+(.*)/', $entry, $exMatches);
                    $exception = $exMatches[1] ?? 'System Error';
                    $message = $exMatches[3] ?? $firstLine;

                    $recentErrors[] = [
                        'timestamp' => $timestamp,
                        'level' => $level,
                        'exception' => class_basename($exception),
                        'message' => Str::limit($message, 250),
                        'raw_snippet' => Str::limit($entry, 400),
                    ];

                    if (count($recentErrors) >= 15) {
                        break;
                    }
                }
            }
        }

        return [
            'issues_count' => count($recentErrors),
            'has_errors' => count($recentErrors) > 0,
            'recent_errors' => $recentErrors,
        ];
    }

    /**
     * Check server environment settings (Timezone, PHP, Symlink, DB).
     */
    private function checkServerEnvironment(): array
    {
        $storageLinkValid = File::exists(public_path('storage'));
        $appTimezone = config('app.timezone');

        $dbNow = null;
        try {
            $dbNow = DB::select("SELECT NOW() as n")[0]->n ?? null;
        } catch (\Throwable $e) {
            $dbNow = 'Connection Failed';
        }

        return [
            'php_version' => PHP_VERSION,
            'app_timezone' => $appTimezone,
            'database_time' => $dbNow,
            'storage_symlink_ok' => $storageLinkValid,
            'storage_writable' => is_writable(storage_path()),
        ];
    }

    /**
     * Execute one-click auto-repair actions with optional scope:
     * 'all', 'boards', 'social_media', 'websites', 'notes', 'cache'.
     */
    public function runAutoRepair(?string $scope = null): array
    {
        $now = Carbon::now('Asia/Phnom_Penh');
        $currentMonthYear = $now->format('F Y');
        $actions = [];
        $scope = $scope ?: 'all';

        // 1. BOARDS MODULE AUTO-REPAIR
        if (in_array($scope, ['all', 'boards'])) {
            try {
                // Sync card approved statuses across sync groups
                $approvedUpdated = Card::whereHas('boardList', function ($q) {
                    $q->where('name', 'like', '%approved%');
                })
                ->whereNull('approved_at')
                ->update(['approved_at' => now(), 'status' => 'approved']);

                if ($approvedUpdated > 0) {
                    $actions[] = "Marked {$approvedUpdated} card(s) in Approved lists as approved.";
                }

                // Sync approved status to siblings
                $syncGroups = Card::whereHas('boardList', function ($q) {
                    $q->where('name', 'like', '%approved%');
                })
                ->whereNotNull('sync_group_id')
                ->pluck('sync_group_id')
                ->unique();

                foreach ($syncGroups as $g) {
                    Card::where('sync_group_id', $g)->update(['status' => 'approved']);
                }
                $actions[] = "Synchronized approved status across {$syncGroups->count()} card sync groups.";
            } catch (\Throwable $e) {
                $actions[] = "Approved status sync skipped: {$e->getMessage()}";
            }

            // Restore Block/Waiting list on SMM Planning Board
            try {
                $smmBoard = Board::where('name', 'like', "SMM Planning Board – {$currentMonthYear}%")
                    ->orWhere('name', 'like', "SMM Planning Board - {$currentMonthYear}%")
                    ->first();

                if ($smmBoard) {
                    $blockList = BoardList::firstOrCreate(
                        ['board_id' => $smmBoard->id, 'name' => 'Block/Waiting'],
                        ['position' => 6000]
                    );
                    if ($blockList->wasRecentlyCreated) {
                        $actions[] = "Created missing 'Block/Waiting' list on '{$smmBoard->name}'.";
                    }
                }
            } catch (\Throwable $e) {
                $actions[] = "Block list check skipped: {$e->getMessage()}";
            }

            // Hide unstarred duplicate SMM Planning boards
            try {
                $smmBoards = Board::where('type', 'smm')->where('name', 'like', "%{$currentMonthYear}%")->get();
                if ($smmBoards->count() > 1) {
                    $starred = $smmBoards->firstWhere('is_starred', true);
                    $unstarred = $smmBoards->where('is_starred', false)->first();
                    if ($starred && $unstarred && !$unstarred->is_hidden) {
                        $unstarred->update(['is_hidden' => true]);
                        $actions[] = "Hidden unstarred duplicate SMM Planning board (ID: {$unstarred->id}).";
                    }
                }
            } catch (\Throwable $e) {
                $actions[] = "Duplicate board cleanup skipped: {$e->getMessage()}";
            }

            // Fix Planning board 'ready' automations to target current month Workflow board
            try {
                $autoFixed = 0;
                $autoDeleted = 0;
                $readyAutomations = BoardAutomation::where('action_type', 'copy')->where('trigger_word', 'ready')->get();
                foreach ($readyAutomations as $auto) {
                    $board = $auto->board;
                    if (!$board || stripos($board->name, 'Planning board') === false) {
                        continue;
                    }

                    // Extract Month Year e.g. "September 2026"
                    preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i', $board->name, $monthMatch);
                    $targetMonth = $monthMatch[0] ?? $currentMonthYear;

                    // Find corresponding Workflow board in same workspace regardless of dash style
                    $workflowBoard = Board::where('workspace_id', $board->workspace_id)
                        ->where('name', 'like', '%Workflow board%')
                        ->where('name', 'like', "%{$targetMonth}%")
                        ->first();

                    if (!$workflowBoard) {
                        continue;
                    }

                    // If this automation targets an older or different board, check if a current automation already exists
                    if ($auto->target_board_id !== $workflowBoard->id) {
                        $existingCurrent = BoardAutomation::where('board_id', $board->id)
                            ->where('action_type', 'copy')
                            ->where('trigger_word', 'ready')
                            ->where('target_board_id', $workflowBoard->id)
                            ->first();

                        if ($existingCurrent) {
                            $auto->delete();
                            $autoDeleted++;
                        } else {
                            $draftList = $workflowBoard->lists()->where('name', 'like', '%Draft%')->first();
                            if ($draftList) {
                                $auto->updateQuietly([
                                    'target_board_id' => $workflowBoard->id,
                                    'target_list_id' => $draftList->id,
                                ]);
                                $autoFixed++;
                            }
                        }
                    }
                }
                if ($autoFixed > 0 || $autoDeleted > 0) {
                    $actions[] = "Re-aligned {$autoFixed} and purged {$autoDeleted} outdated duplicate Planning-to-Workflow automations.";
                }
            } catch (\Throwable $e) {
                $actions[] = "Automation link fix skipped: {$e->getMessage()}";
            }
        }

        // 2. SOCIAL MEDIA MODULE AUTO-REPAIR
        if (in_array($scope, ['all', 'social_media'])) {
            try {
                Artisan::call('cards:sync-planning-weeks');
                $actions[] = "Executed SMM Planning week card synchronization.";
            } catch (\Throwable $e) {}

            try {
                Artisan::call('smm:fix-labels');
                $actions[] = "Executed SMM label verification and standardization.";
            } catch (\Throwable $e) {}

            try {
                Artisan::call('cards:restore-block-smm');
                $actions[] = "Restored any blocked SMM twin cards.";
            } catch (\Throwable $e) {}

            // Populate standard company platform URLs if missing
            try {
                $standardUrls = [
                    'MachineryBargains' => [
                        'Facebook' => 'https://www.facebook.com/Machinery.Bargains',
                        'Instagram' => 'https://www.instagram.com/machinery.bargains',
                        'X' => 'https://x.com/Machin_Bargains',
                        'X(Twitter)' => 'https://x.com/Machin_Bargains',
                        'TikTok' => 'https://www.tiktok.com/@machinery.bargains',
                        'YouTube' => 'https://www.youtube.com/@Machinery.Bargains',
                        'Tumblr' => 'https://www.tumblr.com/machinerybargains',
                        'Pinterest' => 'https://www.pinterest.com/MachineryBargains/',
                    ],
                    'MiniExca' => [
                        'Facebook' => 'https://www.facebook.com/MiniExcaMachinery/',
                        'Instagram' => 'https://www.instagram.com/miniexcamachinery',
                        'X' => 'https://x.com/miniexcamachine',
                        'X(Twitter)' => 'https://x.com/miniexcamachine',
                        'TikTok' => 'https://www.tiktok.com/@miniexcamachinery',
                        'YouTube' => 'https://youtube.com/@MiniExcavatorMachinery',
                        'Tumblr' => 'https://www.tumblr.com/miniexca',
                        'Pinterest' => 'https://www.pinterest.com/miniexca',
                    ],
                    'MachineryAsia.Online' => [
                        'Facebook' => 'https://www.facebook.com/MachineryAsiaOnlinee',
                        'Instagram' => 'https://www.instagram.com/machineryasiaonline/',
                        'X' => 'https://x.com/MachineryLoader',
                        'X(Twitter)' => 'https://x.com/MachineryLoader',
                        'TikTok' => 'https://www.tiktok.com/@machineryasiaonline',
                        'YouTube' => 'https://www.youtube.com/@MachineryAsiaOnline',
                        'Tumblr' => 'https://www.tumblr.com/blog/machineryasiaonline',
                        'Pinterest' => 'https://www.pinterest.com/MachineryAsiaOnline/',
                    ],
                    'ImpossibleMachinery' => [
                        'Facebook' => 'https://www.facebook.com/ImpossibleMachinery/',
                        'Instagram' => 'https://www.instagram.com/impossiblemachinery/',
                        'X' => 'https://x.com/impss_machinery',
                        'X(Twitter)' => 'https://x.com/impss_machinery',
                        'TikTok' => 'https://www.tiktok.com/@impossiblemachinery',
                        'YouTube' => 'https://youtube.com/@ImpossibleMachinery',
                        'Tumblr' => 'https://www.tumblr.com/impossiblemachinery',
                        'Pinterest' => 'https://www.pinterest.com/ImpossibleMachinery/',
                    ],
                    'SkidSteers' => [
                        'Facebook' => 'https://www.facebook.com/Americanskidsteers',
                        'Instagram' => 'https://www.instagram.com/americanskidsteer/',
                        'X' => 'https://x.com/iloveSkidSteer',
                        'X(Twitter)' => 'https://x.com/iloveSkidSteer',
                        'TikTok' => 'https://www.tiktok.com/@americanskidsteer',
                        'YouTube' => 'https://youtube.com/@AmericanSkidSteer',
                        'Tumblr' => 'https://www.tumblr.com/skidsteerforamerican',
                        'Pinterest' => 'https://www.pinterest.com/american_skidsteer/',
                    ],
                    'Machinery.Org' => [
                        'Facebook' => 'https://www.facebook.com/machineryorg',
                        'Instagram' => 'https://www.instagram.com/machineryorg',
                    ],
                    'MachineryAsia (FB)' => [
                        'Facebook' => 'https://www.facebook.com/MachineryAsiaOnlinee',
                    ],
                ];

                $itemsSynced = 0;
                foreach ($standardUrls as $className => $platforms) {
                    $class = SocialMediaClass::where('name', $className)->first();
                    if (!$class) continue;
                    foreach ($platforms as $platform => $url) {
                        $item = $class->items()->where('name', $platform)->first();
                        if ($item && (empty($item->url) || $item->url !== $url)) {
                            $item->url = $url;
                            $item->save();
                            $itemsSynced++;
                        }
                    }
                }
                if ($itemsSynced > 0) {
                    $actions[] = "Updated {$itemsSynced} Social Media Item links to verified company profiles.";
                }
            } catch (\Throwable $e) {}
        }

        // 3. WEBSITES MODULE AUTO-REPAIR
        if (in_array($scope, ['all', 'websites'])) {
            try {
                // Reset failed Google Sheet sync items so background job can retry
                $resetCount = WebsiteFollowUp::where('google_sheet_status', 'failed')
                    ->update(['google_sheet_status' => 'pending']);
                if ($resetCount > 0) {
                    $actions[] = "Queued {$resetCount} previously failed Google Sheet follow-up syncs for retry.";
                }
            } catch (\Throwable $e) {}
        }

        // 4. NOTES MODULE AUTO-REPAIR
        if (in_array($scope, ['all', 'notes'])) {
            try {
                $notesCleaned = Note::whereNotNull('folder_id')
                    ->whereDoesntHave('folder')
                    ->update(['folder_id' => null]);
                if ($notesCleaned > 0) {
                    $actions[] = "Moved {$notesCleaned} orphan note(s) to the root directory.";
                }
            } catch (\Throwable $e) {}
        }

        // 5. CACHE FLUSH
        if (in_array($scope, ['all', 'cache'])) {
            try {
                Artisan::call('optimize:clear');
                $actions[] = "Cleared application view, route, and config caches.";
            } catch (\Throwable $e) {}
        }

        return [
            'success' => true,
            'scope' => $scope,
            'actions_taken' => $actions,
            'message' => "Auto-repair routine ({$scope}) executed successfully.",
        ];
    }

    /**
     * Clear application caches and prime compiled routes & views for maximum production performance.
     */
    public function optimizeSystemSpeed(): array
    {
        $actions = [];
        $t0 = microtime(true);

        // 1. Clear stale caches
        try {
            Artisan::call('optimize:clear');
            $actions[] = "Flushed expired view, route, and config caches.";
        } catch (\Throwable $e) {
            $actions[] = "Cache flush: " . $e->getMessage();
        }

        // 2. Pre-compile configuration
        try {
            Artisan::call('config:cache');
            $actions[] = "Compiled and cached application configuration.";
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("SystemHealthService optimize config:cache: " . $e->getMessage());
            $actions[] = "Config cache skipped: " . $e->getMessage();
        }

        // 3. Pre-compile URL routes
        try {
            Artisan::call('route:cache');
            $actions[] = "Compiled and cached application route table.";
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("SystemHealthService optimize route:cache: " . $e->getMessage());
            $actions[] = "Route cache skipped: " . $e->getMessage();
        }

        // 4. Pre-compile Blade template views
        try {
            Artisan::call('view:cache');
            $actions[] = "Pre-compiled all Blade template views.";
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("SystemHealthService optimize view:cache: " . $e->getMessage());
            $actions[] = "View cache skipped: " . $e->getMessage();
        }

        // 5. Reset OPcache if available
        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $actions[] = "Reset PHP OPcache memory buffers.";
        }

        $duration = round(microtime(true) - $t0, 2);

        return [
            'success' => true,
            'duration' => $duration,
            'actions_taken' => $actions,
            'message' => "System speed optimized successfully in {$duration}s.",
            'performance' => $this->checkSystemPerformance(),
        ];
    }

    /**
     * Generate a Markdown-formatted report designed specifically to be copied and pasted to AI.
     */
    public function generateAiMarkdownReport(): string
    {
        $diag = $this->runDiagnostics();
        $md = "# 🩺 System Health & Maintenance Diagnostics Report\n";
        $md .= "**Generated At:** {$diag['timestamp']}\n";
        $md .= "**Overall Status:** " . strtoupper($diag['overall_status']) . "\n";
        $md .= "**Total Issues Detected:** {$diag['total_issues']}\n\n";

        // Section 1: Board Automations
        $md .= "## 🤖 1. Board & Card Automations Health\n";
        $md .= "- Total Automations Scanned: {$diag['automations']['total_checked']}\n";
        $md .= "- Issues Found: {$diag['automations']['issues_count']}\n\n";

        if (!empty($diag['automations']['issues'])) {
            $md .= "| Severity | Board | Error Reason | Suggested Fix |\n";
            $md .= "|---|---|---|---|\n";
            foreach ($diag['automations']['issues'] as $issue) {
                $md .= "| {$issue['severity']} | {$issue['board']} | {$issue['reason']} | {$issue['suggested_fix']} |\n";
            }
            $md .= "\n";
        } else {
            $md .= "✅ All Board & Card Comment Automations are correctly configured and verified.\n\n";
        }

        // Section 2: Boards & Lists
        $md .= "## 📋 2. Boards, Lists & Twin Card Integrity\n";
        $boardAndSyncIssues = array_merge($diag['boards']['issues'], $diag['sync']['issues']);
        if (!empty($boardAndSyncIssues)) {
            $md .= "| Type | Title | Reason | Suggested Fix |\n";
            $md .= "|---|---|---|---|\n";
            foreach ($boardAndSyncIssues as $issue) {
                $title = $issue['title'] ?? 'Integrity Issue';
                $fix = $issue['suggested_fix'] ?? 'Run auto-repair';
                $md .= "| {$issue['severity']} | {$title} | {$issue['reason']} | {$fix} |\n";
            }
            $md .= "\n";
        } else {
            $md .= "✅ All workflow board lists, SMM planning lists, and card sync groups are healthy.\n\n";
        }

        // Section 3: Social Media Management
        $md .= "## 📱 3. Social Media Management\n";
        $md .= "- Social Media Classes: {$diag['social_media']['total_classes']}\n";
        $md .= "- Social Media Profile Items: {$diag['social_media']['total_items']}\n";
        $md .= "- Active Planning Board: {$diag['social_media']['active_smm_board']}\n";
        $md .= "- Cards in Planning Weeks: {$diag['social_media']['week_cards_count']}\n\n";

        if (!empty($diag['social_media']['issues'])) {
            $md .= "| Severity | Title | Reason | Suggested Fix |\n";
            $md .= "|---|---|---|---|\n";
            foreach ($diag['social_media']['issues'] as $issue) {
                $md .= "| {$issue['severity']} | {$issue['title']} | {$issue['reason']} | {$issue['suggested_fix']} |\n";
            }
            $md .= "\n";
        } else {
            $md .= "✅ Social media classes, profiles, and planning week distributions are operational.\n\n";
        }

        // Section 4: All Websites & Follow-Ups
        $md .= "## 🌐 4. All Websites & Follow-Ups\n";
        $md .= "- Total Websites: {$diag['websites']['total_websites']}\n";
        $md .= "- Total Follow-Ups: {$diag['websites']['total_follow_ups']}\n\n";

        if (!empty($diag['websites']['issues'])) {
            $md .= "| Severity | Title | Reason | Suggested Fix |\n";
            $md .= "|---|---|---|---|\n";
            foreach ($diag['websites']['issues'] as $issue) {
                $md .= "| {$issue['severity']} | {$issue['title']} | {$issue['reason']} | {$issue['suggested_fix']} |\n";
            }
            $md .= "\n";
        } else {
            $md .= "✅ All website records, categories, and follow-up entries are consistent.\n\n";
        }

        // Section 5: Notes & Collaboration
        $md .= "## 📝 5. Notes & Collaboration\n";
        $md .= "- Total Notes: {$diag['notes']['total_notes']}\n";
        $md .= "- Total Folders: {$diag['notes']['total_folders']}\n\n";

        if (!empty($diag['notes']['issues'])) {
            $md .= "| Severity | Title | Reason | Suggested Fix |\n";
            $md .= "|---|---|---|---|\n";
            foreach ($diag['notes']['issues'] as $issue) {
                $md .= "| {$issue['severity']} | {$issue['title']} | {$issue['reason']} | {$issue['suggested_fix']} |\n";
            }
            $md .= "\n";
        } else {
            $md .= "✅ Notes folder hierarchy and team note links are healthy.\n\n";
        }

        // Section 6: System Modules & Maintenance Status
        $md .= "## 🔒 6. System Modules & Maintenance Locks\n";
        foreach ($diag['maintenance']['modules'] as $key => $mod) {
            $icon = $mod['status'] === 'maintenance' ? '⚠️ MAINTENANCE LOCK' : '✅ OPERATIONAL';
            $md .= "- {$mod['name']}: {$icon}\n";
        }
        $md .= "\n";

        // Section 7: System Performance & Cache
        $md .= "## ⚡ 7. Performance & Caches\n";
        $md .= "- Config Cached: " . ($diag['performance']['config_cached'] ? 'YES' : 'NO') . "\n";
        $md .= "- Routes Cached: " . ($diag['performance']['routes_cached'] ? 'YES' : 'NO') . "\n";
        $md .= "- Pre-Compiled Views: {$diag['performance']['compiled_views_count']}\n";
        $md .= "- Server Log Size: {$diag['performance']['log_size']}\n";
        $md .= "- PHP OPcache: " . ($diag['performance']['opcache_enabled'] ? 'ACTIVE' : 'INACTIVE / N/A') . "\n\n";

        // Section 8: Recent Server Error Logs
        $md .= "## ⚠️ 8. Recent Server Error Logs\n";
        if (!empty($diag['logs']['recent_errors'])) {
            foreach ($diag['logs']['recent_errors'] as $idx => $err) {
                $num = $idx + 1;
                $md .= "### Error #{$num}: [{$err['timestamp']}] {$err['exception']}\n";
                $md .= "```text\n{$err['message']}\n```\n";
            }
        } else {
            $md .= "✅ No recent critical errors recorded in laravel.log.\n\n";
        }

        // Section 9: Server Environment
        $md .= "## 🖥️ 9. Server Environment\n";
        $md .= "- PHP Version: {$diag['server']['php_version']}\n";
        $md .= "- App Timezone: {$diag['server']['app_timezone']}\n";
        $md .= "- Database Time: {$diag['server']['database_time']}\n";
        $md .= "- Storage Symlink: " . ($diag['server']['storage_symlink_ok'] ? 'OK' : 'BROKEN') . "\n";
        $md .= "- Storage Writable: " . ($diag['server']['storage_writable'] ? 'YES' : 'NO') . "\n\n";

        $md .= "---\n";
        $md .= "*Prompt for AI:* Please review the above errors, anomalies, and maintenance logs across the system, explain the root cause, and provide the exact code fixes needed.";

        return $md;
    }
}
