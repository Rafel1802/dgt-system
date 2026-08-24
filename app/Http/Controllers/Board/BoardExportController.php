<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\Board;
use App\Models\Card;
use App\Enums\CardStatus;
use App\Enums\CardPriority;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BoardExportController extends Controller
{
    /**
     * Extracts markdown image tags (both base64 and URLs) from text.
     * Returns an array with 'text' (cleaned) and 'screenshots' (array of image sources).
     */
    public static function extractScreenshotsAndClean(?string $text): array
    {
        if (empty($text)) {
            return [
                'text' => '',
                'screenshots' => []
            ];
        }

        $screenshots = [];
        // Match standard markdown image syntax: ![alt](src)
        // Group 1 catches base64 data URIs or standard web URLs.
        $pattern = '/!\[.*?\]\((data:image\/[a-zA-Z0-9\+\-\.]+;base64,[A-Za-z0-9\+\/=\s]+|https?:\/\/[^\s\)]+)\)/i';

        if (preg_match_all($pattern, $text, $matches)) {
            $screenshots = $matches[1];
            // Clean the text by removing the image markdown tags
            $cleanedText = preg_replace($pattern, '', $text);
        } else {
            $cleanedText = $text;
        }

        $cleanedText = str_replace('**', '', $cleanedText);

        return [
            'text' => trim($cleanedText),
            'screenshots' => $screenshots
        ];
    }

    /**
     * Helper to get all workspaces and boards a user can access.
     */
    private function getAuthorizedWorkspaces(\App\Models\User $user)
    {
        if ($user->hasAnyRole(['super-admin', 'admin-digital'])) {
            $workspaces = Workspace::with([
                'boards' => fn($q) => $q->where('is_archived', false)->where('is_hidden', false)->orderBy('position'),
            ])
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        } else {
            $allActiveWorkspaces = Workspace::with([
                'boards' => fn($q) => $q->where('is_archived', false)->where('is_hidden', false)->orderBy('position'),
            ])
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
            $workspaces = $allActiveWorkspaces->filter(function ($ws) use ($user) {
                if ($ws->hasMember($user->id)) return true;
                foreach ($ws->boards as $board) {
                    if ($board->hasMember($user->id)) return true;
                }
                return false;
            });
        }

        foreach ($workspaces as $workspace) {
            $workspace->setRelation('boards', $workspace->boards->filter(function ($board) use ($user) {
                $isQc = str_contains(strtolower($user->team_role ?? ''), 'qc');
                $isBypassed = $user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']) || $isQc;

                if ($isBypassed) {
                    return true;
                }

                if ($user->hasAnyRole(['digital-team', 'sales-crm'])) {
                    return $board->hasMember($user->id);
                }
                return true;
            }));
        }

        $isQc = str_contains(strtolower($user->team_role ?? ''), 'qc');
        $isBypassed = $user->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']) || $isQc;
        if (!$isBypassed && $user->hasRole('digital-team')) {
            $workspaces = $workspaces->filter(function ($ws) {
                return $ws->boards->isNotEmpty();
            });
        }

        return $workspaces;
    }

    /**
     * Apply request filters and return a card query.
     */
    private function getFilteredCardsQuery(Request $request, ?Board $board = null)
    {
        $boardIds = [];
        if ($board) {
            $boardIds = [$board->id];
        }
        
        if ($request->has('board_ids') && is_array($request->board_ids)) {
            $requestedIds = array_map('intval', $request->board_ids);
            $user = auth()->user();
            
            $boardsToCheck = Board::whereIn('id', $requestedIds)->with(['workspace', 'members'])->get();
            
            $allowedBoards = $boardsToCheck->filter(function($b) use ($user) {
                if ($user->hasRole('super-admin')) {
                    return true;
                }
                
                if ($b->hasMember($user->id)) {
                    return true;
                }
                
                if (!$b->workspace || !$b->workspace->hasMember($user->id)) {
                    return false;
                }
                
                if ($b->visibility === 'workspace' || $b->visibility === 'public') {
                    return true;
                }
                
                return $b->workspace->owner_id === $user->id;
            });
            
            $boardIds = $allowedBoards->pluck('id')->toArray();
        }

        if (empty($boardIds)) {
            return Card::whereRaw('1 = 0');
        }

        $query = Card::whereIn('board_id', $boardIds)->with(['board', 'boardList', 'assignees', 'labels', 'files', 'activities', 'comments']);

        // Load comments and comment user if comments are included
        if ($request->boolean('include_comments', false)) {
            $query->with(['comments' => function($q) {
                $q->where('is_system', false)->orderBy('created_at', 'asc');
            }, 'comments.user']);
        }

        $isPersonalExport = $request->boolean('is_personal_report', false)
            || request()->routeIs('*.personal.export')
            || request()->routeIs('reports.personal.export')
            || request()->routeIs('boards.reports.personal.export');

        // 1. Date Range Filtering
        if ($request->filled('date_range') && $request->date_range !== 'all_time') {
            $now = Carbon::now('Asia/Phnom_Penh');
            $startDate = null;
            $endDate = null;

            switch ($request->date_range) {
                case 'today':
                     $startDate = $now->copy()->startOfDay()->setTimezone('UTC');
                     $endDate = $now->copy()->endOfDay()->setTimezone('UTC');
                     break;
                case 'this_week':
                     $startDate = $now->copy()->startOfWeek()->setTimezone('UTC');
                     $endDate = $now->copy()->endOfWeek()->setTimezone('UTC');
                     break;
                case 'this_month':
                     $startDate = $now->copy()->startOfMonth()->setTimezone('UTC');
                     $endDate = $now->copy()->endOfMonth()->setTimezone('UTC');
                     break;
                case 'last_month':
                     $startDate = $now->copy()->subMonth()->startOfMonth()->setTimezone('UTC');
                     $endDate = $now->copy()->subMonth()->endOfMonth()->setTimezone('UTC');
                     break;
                case 'custom':
                case 'custom_period':
                     if ($request->filled('start_date')) {
                         $startDate = Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->startOfDay()->setTimezone('UTC');
                     }
                     if ($request->filled('end_date')) {
                         $endDate = Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->endOfDay()->setTimezone('UTC');
                     }
                     break;
            }

            if ($isPersonalExport) {
                // Personal exports handle their own date filtering in the role-based section
            } else if ($startDate || $endDate) {
                $query->where(function($q) use ($startDate, $endDate) {
                    $q->whereHas('activities', function($qa) use ($startDate, $endDate) {
                        $qa->where('action', '!=', 'created');
                        if ($startDate) $qa->where('created_at', '>=', $startDate);
                        if ($endDate) $qa->where('created_at', '<=', $endDate);
                    })->orWhereHas('comments', function($qc) use ($startDate, $endDate) {
                        $qc->where('is_system', false);
                        if ($startDate) $qc->where('created_at', '>=', $startDate);
                        if ($endDate) $qc->where('created_at', '<=', $endDate);
                    });
                });
            }
        }

        // 2. Members Filtering (Standard report filters by card assignees)
        if ($request->filled('member_id') && $request->member_id !== 'all') {
            $memberId = (int)$request->member_id;
            $query->whereHas('assignees', function($q) use ($memberId) {
                $q->where('users.id', $memberId);
            });
        }
        
        // 2b. Assign By Filtering
        if ($request->filled('assign_by_id') && $request->assign_by_id !== 'all') {
            $assignById = (int)$request->assign_by_id;
            $query->where('user_id', $assignById);
        }

        // 2c. Label Filtering
        if ($request->filled('label_id') && $request->label_id !== 'all') {
            $labelId = (int)$request->label_id;
            $query->whereHas('labels', function($q) use ($labelId) {
                $q->where('labels.id', $labelId);
            });
        }

        // 3. Status Filtering
        if ($request->has('statuses') && is_array($request->statuses)) {
            $statuses = $request->statuses;
            $query->where(function($q) use ($statuses) {
                $hasCond = false;

                // Archived tasks status condition
                if (in_array('archived', $statuses)) {
                    $q->orWhere('is_archived', true);
                    $hasCond = true;
                }

                // Check other non-archived statuses
                $dbStatuses = [];
                if (in_array('draft', $statuses)) {
                    $dbStatuses[] = CardStatus::Todo->value;
                    $dbStatuses[] = CardStatus::Rejected->value;
                }
                if (in_array('in_progress', $statuses)) {
                    $dbStatuses[] = CardStatus::InProgress->value;
                }
                if (in_array('review', $statuses)) {
                    $dbStatuses[] = CardStatus::Review->value;
                    $dbStatuses[] = CardStatus::Approved->value;
                }
                if (in_array('completed', $statuses)) {
                    $dbStatuses[] = CardStatus::Done->value;
                }

                if (!empty($dbStatuses)) {
                    $q->orWhere(function($sq) use ($dbStatuses) {
                        $sq->whereIn('status', $dbStatuses)->where('is_archived', false);
                    });
                    $hasCond = true;
                }

                if (!$hasCond) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        // 4. Role-based QC / Supervisor Personal Report Filtering
        // 4. Role-based QC / Supervisor Personal Report filtering
        if ($isPersonalExport) {
            $user = auth()->user();

            if ($user->isQc()) {
                // QC Personal Report scope:
                //  • ONLY cards where THIS QC user has commented "QC approved" within the selected date range
                $userId = $user->id;
                $query->where(function($q) use ($userId, $startDate, $endDate) {
                    $q->whereHas('comments', function($qc) use ($userId, $startDate, $endDate) {
                        $qc->where('user_id', $userId)
                           ->whereRaw("LOWER(content) LIKE '%qc approved%'");
                        if (isset($startDate)) $qc->where('created_at', '>=', $startDate);
                        if (isset($endDate)) $qc->where('created_at', '<=', $endDate);
                    });
                });

                // Eager-load QC approved comments by this user to support revision counting
                $query->with(['qcApprovalComments' => function($q) use ($userId) {
                    $q->where('user_id', $userId)
                      ->where('is_system', false)
                      ->whereRaw("LOWER(content) LIKE '%qc approved%'")
                      ->orderBy('created_at');
                }]);

            } elseif ($user->isSupervisorRole()) {
                // Supervisor Personal Report scope:
                //  • Cards moved from Supervisor to Approved list
                //  • Cards moved from Supervisor to Blocked list
                //  • Cards approved by Supervisor
                //  • Cards marked as errors by Supervisor
                $userId = $user->id;
                $query->where(function($q) use ($userId, $startDate, $endDate) {
                    $q->whereHas('activities', function($qal) use ($userId, $startDate, $endDate) {
                        $qal->where('user_id', $userId);
                        if (isset($startDate)) $qal->where('created_at', '>=', $startDate);
                        if (isset($endDate)) $qal->where('created_at', '<=', $endDate);
                    })->orWhereHas('comments', function($qc) use ($userId, $startDate, $endDate) {
                        $qc->where('user_id', $userId);
                        if (isset($startDate)) $qc->where('created_at', '>=', $startDate);
                        if (isset($endDate)) $qc->where('created_at', '<=', $endDate);
                    });
                });
            }
        }

        return $query;
    }

    private function assignActivityDates($cards, $startDate, $endDate, $isPersonalExport, $isQc)
    {
        foreach ($cards as $card) {
            $activityDate = null;
            
            // Collect all timestamps
            $timestamps = collect();

            if ($isPersonalExport && $isQc) {
                // For QC Personal Report, ONLY look at "QC approved" comments by this user
                $userId = auth()->id();
                foreach ($card->comments as $comment) {
                    if ($comment->user_id === $userId && stripos($comment->content ?? $comment->body, 'qc approved') !== false) {
                        $timestamps->push($comment->created_at);
                    }
                }
            } else {
                // Regular report or other personal reports
                foreach ($card->activities as $activity) {
                    if ($activity->action !== 'created') {
                        $timestamps->push($activity->created_at);
                    }
                }
                foreach ($card->comments as $comment) {
                    if (!$comment->is_system) {
                        $timestamps->push($comment->created_at);
                    }
                }
                // If there are no activities or comments, fallback to updated_at
                if ($timestamps->isEmpty()) {
                    $timestamps->push($card->updated_at);
                }
            }

            // Filter timestamps within the requested date range
            if ($startDate || $endDate) {
                $filtered = $timestamps->filter(function($ts) use ($startDate, $endDate) {
                    $valid = true;
                    if ($startDate && $ts < $startDate) $valid = false;
                    if ($endDate && $ts > $endDate) $valid = false;
                    return $valid;
                });
                
                // If we found activities in the range, use the most recent one in that range
                if ($filtered->isNotEmpty()) {
                    $activityDate = $filtered->max();
                } else {
                    // Fallback to the absolute latest activity (though this card shouldn't be here if strictly filtered)
                    $activityDate = $timestamps->max();
                }
            } else {
                // No date filter, just use the latest activity overall
                $activityDate = $timestamps->max();
            }

            // Set the computed date as a virtual attribute on the card
            // Use Cambodia timezone for display since that's what the user expects
            if ($activityDate) {
                $card->computed_activity_date = \Carbon\Carbon::parse($activityDate)->setTimezone('Asia/Phnom_Penh');
            } else {
                $card->computed_activity_date = $card->created_at ? \Carbon\Carbon::parse($card->created_at)->setTimezone('Asia/Phnom_Penh') : null;
            }
        }
        return $cards;
    }

    private function prepareSmmExportData($cards, $board)
    {
        $errorTasks = 0;
        $weeks = [];

        foreach ($cards as $c) {
            // Determine the week
            $weekName = 'Other';
            $listName = $c->boardList?->name ?? '';
            
            // Check if it's currently in a Week list
            if (stripos($listName, 'Week') !== false || stripos($listName, 'Final') !== false) {
                $weekName = $listName;
            } else if ($c->sync_group_id && $board) {
                // Find the synced card in the SMM board
                $smmCard = \App\Models\Card::with('boardList')->where('sync_group_id', $c->sync_group_id)->where('board_id', $board->id)->first();
                if ($smmCard && $smmCard->boardList) {
                    $smmListName = $smmCard->boardList->name;
                    if (stripos($smmListName, 'Week') !== false || stripos($smmListName, 'Final') !== false) {
                        $weekName = $smmListName;
                    }
                }
            }
            
            // Calculate Error
            $isError = false;
            if ($c->status === \App\Enums\CardStatus::Rejected || !empty($c->rejection_reason)) {
                $isError = true;
            } else if ($c->sync_group_id) {
                // Check if any synced card is in a Blocked list
                $syncedCards = \App\Models\Card::with('boardList')->where('sync_group_id', $c->sync_group_id)->get();
                foreach ($syncedCards as $sc) {
                    if (stripos($sc->boardList?->name ?? '', 'Block') !== false) {
                        $isError = true;
                        break;
                    }
                }
            }
            $c->is_error = $isError;
            if ($isError) {
                $errorTasks++;
            }
            
            // Completed date from ActivityLog
            $completedDate = null;
            $isApproved = $c->status === \App\Enums\CardStatus::Approved || $c->status === \App\Enums\CardStatus::Done || stripos($listName, 'Approved') !== false;
            if ($isApproved) {
                $log = $c->activities()->where('action', 'moved')->where(function($q) {
                    $q->where('description', 'like', '%Approved%')->orWhere('description', 'like', '%Done%');
                })->orderByDesc('created_at')->first();
                if ($log) {
                    $completedDate = $log->created_at;
                } else {
                     $logStatus = $c->activities()->where('action', 'updated')->where('description', 'like', '%status to Approved%')->orderByDesc('created_at')->first();
                     if ($logStatus) {
                         $completedDate = $logStatus->created_at;
                     } else {
                         $completedDate = $c->approved_at ?? $c->updated_at;
                     }
                }
            }
            $c->exact_completed_date = $completedDate ? \Carbon\Carbon::parse($completedDate)->setTimezone('Asia/Phnom_Penh')->format('Y-m-d') : '-';

            $weeks[$weekName][] = $c;
        }

        // Sort weeks logically (Week 1, Week 2, ..., Final Captions, Other)
        uksort($weeks, function($a, $b) {
            if ($a === 'Other') return 1;
            if ($b === 'Other') return -1;
            return strcmp($a, $b);
        });

        // Sort inside each week by Label Priority: Video, Graphic, Content, Listing
        foreach ($weeks as $weekName => &$weekCards) {
            usort($weekCards, function($a, $b) {
                $labelA = $a->labels->first()?->name ?? '';
                $labelB = $b->labels->first()?->name ?? '';

                $priority = function($l) {
                    $l = strtolower($l);
                    if (str_contains($l, 'video')) return 1;
                    if (str_contains($l, 'graphic')) return 2;
                    if (str_contains($l, 'content')) return 3;
                    if (str_contains($l, 'listing')) return 4;
                    return 5;
                };

                return $priority($labelA) <=> $priority($labelB);
            });
        }

        return [
            'groupedCards' => $weeks,
            'errorTasks' => $errorTasks
        ];
    }

    /**
     * Export board tasks to CSV.
     */
    public function exportCsv(Request $request, Board $board)
    {
        $includeDesc = $request->boolean('include_desc', false);
        $includeComments = $request->boolean('include_comments', false);
        $cards = $this->getFilteredCardsQuery($request, $board)->get();

        // Calculate statistics for the summary sections
        $totalTasks = $cards->count();
        $completedTasks = $cards->filter(fn($c) => ($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived)->count();
        $archivedTasks = $cards->filter(fn($c) => $c->is_archived)->count();
        $pendingTasks = $totalTasks - $completedTasks - $archivedTasks;
        
        $overdueTasks = $cards->filter(function($c) {
            return $c->due_at 
                && $c->due_at->isPast() 
                && $c->status !== CardStatus::Done 
                && $c->status !== CardStatus::Approved 
                && !$c->is_archived;
        })->count();

        $memberStats = [];
        foreach ($cards as $c) {
            if ($c->is_archived) {
                continue;
            }
            $assignees = $c->assignees;
            if ($assignees->isEmpty()) {
                if (!isset($memberStats['Unassigned'])) {
                    $memberStats['Unassigned'] = ['completed' => 0, 'pending' => 0, 'total' => 0];
                }
                if (($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived) {
                    $memberStats['Unassigned']['completed']++;
                } else {
                    $memberStats['Unassigned']['pending']++;
                }
                $memberStats['Unassigned']['total']++;
            } else {
                foreach ($assignees as $u) {
                    if (!isset($memberStats[$u->name])) {
                        $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0];
                    }
                    if (($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived) {
                        $memberStats[$u->name]['completed']++;
                    } else {
                        $memberStats[$u->name]['pending']++;
                    }
                    $memberStats[$u->name]['total']++;
                }
            }
        }

        $period = 'All Time';
        $filterStartDate = null;
        $filterEndDate = null;
        $now = Carbon::now('Asia/Phnom_Penh');

        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'today': 
                    $period = 'Today'; 
                    $filterStartDate = $now->copy()->startOfDay()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->endOfDay()->setTimezone('UTC');
                    break;
                case 'this_week': 
                    $period = 'This Week';
                    $filterStartDate = $now->copy()->startOfWeek()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->endOfWeek()->setTimezone('UTC');
                    break;
                case 'this_month': 
                    $period = 'This Month';
                    $filterStartDate = $now->copy()->startOfMonth()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->endOfMonth()->setTimezone('UTC');
                    break;
                case 'last_month': 
                    $period = 'Last Month';
                    $filterStartDate = $now->copy()->subMonth()->startOfMonth()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->subMonth()->endOfMonth()->setTimezone('UTC');
                    break;
                case 'custom':
                case 'custom_period':
                    $start = $request->start_date ? Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'Beginning';
                    $end = $request->end_date ? Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'End';
                    $period = "$start - $end";
                    if ($request->filled('start_date')) $filterStartDate = Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->startOfDay()->setTimezone('UTC');
                    if ($request->filled('end_date')) $filterEndDate = Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->endOfDay()->setTimezone('UTC');
                    break;
            }
        }

        $cards = $this->assignActivityDates($cards, $filterStartDate, $filterEndDate, false, false);
        $smmData = $this->prepareSmmExportData($cards, $board);

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="board-report-' . now()->format('Y-m-d') . '.xls"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $response = response()->view('boards.export-xls', [
            'board' => $board,
            'groupedCards' => $smmData['groupedCards'],
            'period' => $period,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'pendingTasks' => $pendingTasks,
            'overdueTasks' => $overdueTasks,
            'errorTasks' => $smmData['errorTasks'],
            'archivedTasks' => $archivedTasks,
            'memberStats' => $memberStats,
            'includeDesc' => $includeDesc,
            'includeComments' => $includeComments,
            'exportDate' => now()->format('M d, Y g:i A')
        ]);

        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }

    /**
     * Render the print-optimized PDF view.
     */
    public function exportPdf(Request $request, Board $board)
    {
        $cards = $this->getFilteredCardsQuery($request, $board)->get();

        // Calculate statistics - Completed tasks includes Done and Approved
        $totalTasks = $cards->count();
        $completedTasks = $cards->filter(fn($c) => ($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived)->count();
        $archivedTasks = $cards->filter(fn($c) => $c->is_archived)->count();
        $pendingTasks = $totalTasks - $completedTasks - $archivedTasks;
        
        $overdueTasks = $cards->filter(function($c) {
            return $c->due_at 
                && $c->due_at->isPast() 
                && $c->status !== CardStatus::Done 
                && $c->status !== CardStatus::Approved 
                && !$c->is_archived;
        })->count();

        // Team productivity summary: tasks completed & pending per member
        $memberStats = [];
        foreach ($cards as $c) {
            if ($c->is_archived) {
                continue; // Do not list members for archived cards in productivity summary
            }

            $assignees = $c->assignees;
            if ($assignees->isEmpty()) {
                if (!isset($memberStats['Unassigned'])) {
                    $memberStats['Unassigned'] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'team_role' => 'ZZ_Unassigned'];
                }
                if (($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived) {
                    $memberStats['Unassigned']['completed']++;
                } else {
                    $memberStats['Unassigned']['pending']++;
                }
                $memberStats['Unassigned']['total']++;
            } else {
                foreach ($assignees as $u) {
                    if (!isset($memberStats[$u->name])) {
                        $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'team_role' => $u->team_role ?? 'ZZ_Other'];
                    }
                    if (($c->status === CardStatus::Done || $c->status === CardStatus::Approved) && !$c->is_archived) {
                        $memberStats[$u->name]['completed']++;
                    } else {
                        $memberStats[$u->name]['pending']++;
                    }
                    $memberStats[$u->name]['total']++;
                }
            }
        }
        
        uksort($memberStats, function($a, $b) use ($memberStats) {
            $teamA = $memberStats[$a]['team_role'] ?? '';
            $teamB = $memberStats[$b]['team_role'] ?? '';
            if ($teamA === $teamB) {
                return strcmp($a, $b);
            }
            return strcmp($teamA, $teamB);
        });

        // Get report period string
        $period = 'All Time';
        $filterStartDate = null;
        $filterEndDate = null;
        $now = Carbon::now('Asia/Phnom_Penh');
        
        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'today': 
                    $period = 'Today'; 
                    $filterStartDate = $now->copy()->startOfDay()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->endOfDay()->setTimezone('UTC');
                    break;
                case 'this_week': 
                    $period = 'This Week'; 
                    $filterStartDate = $now->copy()->startOfWeek()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->endOfWeek()->setTimezone('UTC');
                    break;
                case 'this_month': 
                    $period = 'This Month'; 
                    $filterStartDate = $now->copy()->startOfMonth()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->endOfMonth()->setTimezone('UTC');
                    break;
                case 'last_month': 
                    $period = 'Last Month'; 
                    $filterStartDate = $now->copy()->subMonth()->startOfMonth()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->subMonth()->endOfMonth()->setTimezone('UTC');
                    break;
                case 'custom':
                case 'custom_period':
                    $start = $request->start_date ? Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'Beginning';
                    $end = $request->end_date ? Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'End';
                    $period = "$start - $end";
                    if ($request->filled('start_date')) $filterStartDate = Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->startOfDay()->setTimezone('UTC');
                    if ($request->filled('end_date')) $filterEndDate = Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->endOfDay()->setTimezone('UTC');
                    break;
            }
        }

        $cards = $this->assignActivityDates($cards, $filterStartDate, $filterEndDate, false, false);

        $labelStats = [];
        foreach ($cards as $c) {
            if ($c->is_archived) continue;
            
            $validLabels = $c->labels->filter(fn($l) => strtoupper($l->name) !== 'SMM');
            
            if ($validLabels->isEmpty()) {
                $labelStats['No Label'] = ($labelStats['No Label'] ?? 0) + 1;
            } else {
                foreach ($validLabels as $label) {
                    $name = $label->name;
                    $labelStats[$name] = ($labelStats[$name] ?? 0) + 1;
                }
            }
        }

        $copyText = '';
        if (auth()->user()->isQc()) {
            $groupedByLabel = [];
            foreach ($cards as $c) {
                if ($c->is_archived) continue;
                
                $validLabels = $c->labels->filter(fn($l) => strtoupper($l->name) !== 'SMM');
                
                if ($validLabels->isEmpty()) {
                    $groupedByLabel['No Label'][] = $c->title;
                } else {
                    foreach ($validLabels as $label) {
                        $groupedByLabel[$label->name][] = $c->title;
                    }
                }
            }
            foreach ($groupedByLabel as $labelName => $titles) {
                $copyText .= $labelName . ' (total ' . count($titles) . ")\n";
                foreach ($titles as $idx => $title) {
                    $copyText .= ($idx + 1) . '.' . $title . "\n";
                }
                $copyText .= "\n";
            }
        } else {
            $count = 0;
            foreach ($cards as $c) {
                if ($c->is_archived) continue;
                $count++;
                $copyText .= $count . '.' . $c->title . "\n";
            }
            $copyText .= "Total: " . $count . "\n";
        }

        // PDF display option
        $includeDesc = $request->boolean('include_desc', false);
        $includeComments = $request->boolean('include_comments', false);

        $smmData = $this->prepareSmmExportData($cards, $board);

        return view('boards.export-pdf', [
            'board' => $board,
            'cards' => $cards,
            'groupedCards' => $smmData['groupedCards'],
            'period' => $period,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'pendingTasks' => $pendingTasks,
            'overdueTasks' => $overdueTasks,
            'errorTasks' => $smmData['errorTasks'],
            'archivedTasks' => $archivedTasks,
            'memberStats' => $memberStats,
            'labelStats' => $labelStats,
            'copyText' => $copyText,
            'includeDesc' => $includeDesc,
            'includeComments' => $includeComments,
            'exportDate' => now()->format('M d, Y g:i A'),
            'reportUrl' => request()->fullUrl(),
            'isQcReport' => true,
            'startDate' => $filterStartDate,
            'endDate' => $filterEndDate,
        ]);
    }

    /**
     * Render the setup page for compiling a consolidated Personal Report.
     */
    public function personalReport(Request $request)
    {
        abort_unless(auth()->user()->isQcOrSupervisor(), 403, 'Unauthorized access to personal reports.');

        $workspaces = $this->getAuthorizedWorkspaces(auth()->user());
        $users = \App\Models\User::where('is_active', true)->orderBy('name')->get();

        return view('reports.personal', compact('workspaces', 'users'));
    }

    /**
     * Export consolidated Personal Report to CSV or PDF.
     */
    public function exportPersonalReport(Request $request)
    {
        abort_unless(auth()->user()->isQcOrSupervisor(), 403, 'Unauthorized access to personal reports.');

        $cards = $this->getFilteredCardsQuery($request, null)->get();
        $format = $request->input('format', 'pdf');
        $includeDesc = $request->boolean('include_desc', false);
        $includeComments = $request->boolean('include_comments', false);

        $period = 'All Time';
        $filterStartDate = null;
        $filterEndDate = null;
        $now = Carbon::now('Asia/Phnom_Penh');
        
        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'today': 
                    $period = 'Today'; 
                    $filterStartDate = $now->copy()->startOfDay()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->endOfDay()->setTimezone('UTC');
                    break;
                case 'this_week': 
                    $period = 'This Week'; 
                    $filterStartDate = $now->copy()->startOfWeek()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->endOfWeek()->setTimezone('UTC');
                    break;
                case 'this_month': 
                    $period = 'This Month'; 
                    $filterStartDate = $now->copy()->startOfMonth()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->endOfMonth()->setTimezone('UTC');
                    break;
                case 'last_month': 
                    $period = 'Last Month'; 
                    $filterStartDate = $now->copy()->subMonth()->startOfMonth()->setTimezone('UTC');
                    $filterEndDate = $now->copy()->subMonth()->endOfMonth()->setTimezone('UTC');
                    break;
                case 'custom':
                case 'custom_period':
                    $start = $request->start_date ? Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'Beginning';
                    $end = $request->end_date ? Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->format('M d, Y') : 'End';
                    $period = "$start - $end";
                    if ($request->filled('start_date')) $filterStartDate = Carbon::parse($request->start_date, 'Asia/Phnom_Penh')->startOfDay()->setTimezone('UTC');
                    if ($request->filled('end_date')) $filterEndDate = Carbon::parse($request->end_date, 'Asia/Phnom_Penh')->endOfDay()->setTimezone('UTC');
                    break;
            }
        }

        $cards = $this->assignActivityDates($cards, $filterStartDate, $filterEndDate, true, auth()->user()->isQc());

        if ($format === 'csv') {
            // A card is "completed" when physically in a list named "Approved" (Supervisor approved it).
            // The `status` field is NOT reliable — all cards keep status='todo' even after being moved.
            $isCompleted = fn($c) => !$c->is_archived
                && stripos($c->boardList?->name ?? '', 'Approved') !== false;

            $totalTasks    = $cards->count();
            $completedTasks = $cards->filter($isCompleted)->count();
            $archivedTasks  = $cards->filter(fn($c) => $c->is_archived)->count();
            $pendingTasks   = $totalTasks - $completedTasks - $archivedTasks;
            $errorTasks     = $cards->filter(fn($c) => $c->status === \App\Enums\CardStatus::Rejected || !empty($c->rejection_reason))->count();
            
            // Overdue = has a past deadline AND is NOT completed (not in Approved list) AND not archived
            $overdueTasks = $cards->filter(function($c) use ($isCompleted) {
                $deadline = $c->deadline ?? $c->due_at;
                return $deadline
                    && \Carbon\Carbon::parse($deadline)->isPast()
                    && !$isCompleted($c)
                    && !$c->is_archived;
            })->count();

            $memberStats = [];
            foreach ($cards as $c) {
                if ($c->is_archived) continue;
                $done = $isCompleted($c);
                $assignees = $c->assignees;
                if ($assignees->isEmpty()) {
                    if (!isset($memberStats['Unassigned'])) {
                        $memberStats['Unassigned'] = ['completed' => 0, 'pending' => 0, 'total' => 0];
                    }
                    $done ? $memberStats['Unassigned']['completed']++ : $memberStats['Unassigned']['pending']++;
                    $memberStats['Unassigned']['total']++;
                } else {
                    foreach ($assignees as $u) {
                        if (!isset($memberStats[$u->name])) {
                            $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0];
                        }
                        $done ? $memberStats[$u->name]['completed']++ : $memberStats[$u->name]['pending']++;
                        $memberStats[$u->name]['total']++;
                    }
                }
            }

            $headers = [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="personal-report-' . now()->format('Y-m-d') . '.xls"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $response = response()->view('boards.export-xls', [
                'board' => null,
                'cards' => $cards,
                'period' => $period,
                'totalTasks' => $totalTasks,
                'completedTasks' => $completedTasks,
                'pendingTasks' => $pendingTasks,
                'overdueTasks' => $overdueTasks,
                'archivedTasks' => $archivedTasks,
                'errorTasks' => $errorTasks,
                'memberStats' => $memberStats,
                'includeDesc' => $includeDesc,
                'includeComments' => $includeComments,
                'exportDate' => now()->format('M d, Y g:i A')
            ]);

            foreach ($headers as $key => $value) {
                $response->header($key, $value);
            }

            return $response;
        }

        // PDF Consolidated Report
        // A card is "completed" when physically in a list named "Approved" (Supervisor approved it).
        // The `status` field is NOT reliable — all cards keep status='todo' even after being moved.
        $isCompleted = fn($c) => !$c->is_archived
            && stripos($c->boardList?->name ?? '', 'Approved') !== false;

        $totalTasks    = $cards->count();
        $completedTasks = $cards->filter($isCompleted)->count();
        $archivedTasks  = $cards->filter(fn($c) => $c->is_archived)->count();
        $pendingTasks   = $totalTasks - $completedTasks - $archivedTasks;
        $errorTasks     = $cards->filter(fn($c) => $c->status === \App\Enums\CardStatus::Rejected || !empty($c->rejection_reason))->count();
        
        // Overdue = has a past deadline AND is NOT completed AND not archived
        $overdueTasks = $cards->filter(function($c) use ($isCompleted) {
            $deadline = $c->deadline ?? $c->due_at;
            return $deadline
                && \Carbon\Carbon::parse($deadline)->isPast()
                && !$isCompleted($c)
                && !$c->is_archived;
        })->count();

        $memberStats = [];
        foreach ($cards as $c) {
            if ($c->is_archived) continue; // Do not list archived cards in productivity summary

            $done      = $isCompleted($c);
            $assignees = $c->assignees;
            if ($assignees->isEmpty()) {
                if (!isset($memberStats['Unassigned'])) {
                    $memberStats['Unassigned'] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'team_role' => 'ZZ_Unassigned'];
                }
                $done ? $memberStats['Unassigned']['completed']++ : $memberStats['Unassigned']['pending']++;
                $memberStats['Unassigned']['total']++;
            } else {
                foreach ($assignees as $u) {
                    if (!isset($memberStats[$u->name])) {
                        $memberStats[$u->name] = ['completed' => 0, 'pending' => 0, 'total' => 0, 'team_role' => $u->team_role ?? 'ZZ_Other'];
                    }
                    $done ? $memberStats[$u->name]['completed']++ : $memberStats[$u->name]['pending']++;
                    $memberStats[$u->name]['total']++;
                }
            }
        }
        
        uksort($memberStats, function($a, $b) use ($memberStats) {
            $teamA = $memberStats[$a]['team_role'] ?? '';
            $teamB = $memberStats[$b]['team_role'] ?? '';
            if ($teamA === $teamB) {
                return strcmp($a, $b);
            }
            return strcmp($teamA, $teamB);
        });

        $labelStats = [];
        foreach ($cards as $c) {
            if ($c->is_archived) continue;
            
            $validLabels = $c->labels->filter(fn($l) => strtoupper($l->name) !== 'SMM');
            
            if ($validLabels->isEmpty()) {
                $labelStats['No Label'] = ($labelStats['No Label'] ?? 0) + 1;
            } else {
                foreach ($validLabels as $label) {
                    $name = $label->name;
                    $labelStats[$name] = ($labelStats[$name] ?? 0) + 1;
                }
            }
        }

        $copyText = '';
        if (auth()->user()->isQc()) {
            $groupedByLabel = [];
            foreach ($cards as $c) {
                if ($c->is_archived) continue;
                
                $validLabels = $c->labels->filter(fn($l) => strtoupper($l->name) !== 'SMM');
                
                if ($validLabels->isEmpty()) {
                    $groupedByLabel['No Label'][] = $c->title;
                } else {
                    foreach ($validLabels as $label) {
                        $groupedByLabel[$label->name][] = $c->title;
                    }
                }
            }
            foreach ($groupedByLabel as $labelName => $titles) {
                $copyText .= $labelName . ' (total ' . count($titles) . ")\n";
                foreach ($titles as $idx => $title) {
                    $copyText .= ($idx + 1) . '.' . $title . "\n";
                }
                $copyText .= "\n";
            }
        } else {
            $count = 0;
            foreach ($cards as $c) {
                if ($c->is_archived) continue;
                $count++;
                $copyText .= $count . '.' . $c->title . "\n";
            }
            $copyText .= "Total: " . $count . "\n";
        }

        return view('boards.export-pdf', [
            'board'         => null, // Consolidated report has no single board context
            'cards'         => $cards,
            'period'        => $period,
            'totalTasks'    => $totalTasks,
            'completedTasks'=> $completedTasks,
            'pendingTasks'  => $pendingTasks,
            'overdueTasks'  => $overdueTasks,
            'archivedTasks' => $archivedTasks,
            'errorTasks'    => $errorTasks,
            'memberStats'   => $memberStats,
            'labelStats'    => $labelStats,
            'copyText'      => $copyText,
            'includeDesc'   => $includeDesc,
            'includeComments'=> $includeComments,
            'exportDate'    => now()->format('M d, Y g:i A'),
            // QC-specific: show revision count column
            'isQcReport'    => auth()->user()->isQc(),
            'reportUrl'     => request()->fullUrl(),
            'startDate'     => $filterStartDate ?? null,
            'endDate'       => $filterEndDate ?? null,
        ]);
    }
}
