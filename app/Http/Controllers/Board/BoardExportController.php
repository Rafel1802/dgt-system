<?php

namespace App\Http\Controllers\Board;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\Board;
use App\Models\Card;
use App\Enums\CardStatus;
use App\Enums\CardPriority;
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

        $query = Card::whereIn('board_id', $boardIds)->with(['board', 'boardList', 'assignees', 'labels', 'files']);

        // Load comments and comment user if comments are included
        if ($request->boolean('include_comments', false)) {
            $query->with(['comments' => function($q) {
                $q->where('is_system', false)->orderBy('created_at', 'asc');
            }, 'comments.user']);
        }

        // 1. Date Range Filtering
        if ($request->filled('date_range') && $request->date_range !== 'all_time') {
            $now = Carbon::now();
            $startDate = null;
            $endDate = null;

            switch ($request->date_range) {
                case 'this_week':
                     $startDate = $now->copy()->startOfWeek();
                     $endDate = $now->copy()->endOfWeek();
                     break;
                case 'this_month':
                     $startDate = $now->copy()->startOfMonth();
                     $endDate = $now->copy()->endOfMonth();
                     break;
                case 'last_month':
                     $startDate = $now->copy()->subMonth()->startOfMonth();
                     $endDate = $now->copy()->subMonth()->endOfMonth();
                     break;
                case 'custom':
                case 'custom_period':
                     if ($request->filled('start_date')) {
                         $startDate = Carbon::parse($request->start_date)->startOfDay();
                     }
                     if ($request->filled('end_date')) {
                         $endDate = Carbon::parse($request->end_date)->endOfDay();
                     }
                     break;
            }

            if ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('created_at', '<=', $endDate);
            }
        }

        // 2. Members Filtering (Standard report filters by card assignees)
        if ($request->filled('member_id') && $request->member_id !== 'all') {
            $memberId = (int)$request->member_id;
            $query->whereHas('assignees', function($q) use ($memberId) {
                $q->where('users.id', $memberId);
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
        $isPersonalExport = $request->boolean('is_personal_report', false)
            || request()->routeIs('*.personal.export')
            || request()->routeIs('reports.personal.export')
            || request()->routeIs('boards.reports.personal.export');

        if ($isPersonalExport) {
            $user = auth()->user();

            if ($user->isQc()) {
                // QC Personal Report scope:
                //  • Cards assigned to this QC member
                //  • OR cards this QC member moved to Supervisor
                //  • OR cards where THIS QC user has commented "QC approved"
                $userId = $user->id;
                $query->where(function($q) use ($userId) {
                    $q->whereHas('assignees', function($qa) use ($userId) {
                        $qa->where('users.id', $userId);
                    })
                    ->orWhereHas('activityLogs', function($qal) use ($userId) {
                        $qal->where('user_id', $userId)
                            ->where('action', 'card.moved')
                            ->where('description', 'like', '%to **Supervisor%');
                    })
                    ->orWhereHas('comments', function($qc) use ($userId) {
                        $qc->where('user_id', $userId)
                           ->where('is_system', false)
                           ->whereRaw("LOWER(content) LIKE '%qc approved%'");
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
                $query->where(function($q) use ($userId) {
                    $q->where('approved_by', $userId)
                      ->orWhere('block_completed_by', $userId)
                      ->orWhereHas('activityLogs', function($qal) use ($userId) {
                          $qal->where('user_id', $userId)
                              ->where('action', 'card.moved')
                              ->where(function($qald) {
                                  $qald->where('description', 'like', '%to **Approved%')
                                       ->orWhere('description', 'like', '%to **Block%');
                              });
                      });
                });
            }
        }

        return $query;
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
        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'this_week': $period = 'This Week'; break;
                case 'this_month': $period = 'This Month'; break;
                case 'last_month': $period = 'Last Month'; break;
                case 'custom':
                case 'custom_period':
                    $start = $request->start_date ? Carbon::parse($request->start_date)->format('M d, Y') : 'Beginning';
                    $end = $request->end_date ? Carbon::parse($request->end_date)->format('M d, Y') : 'End';
                    $period = "$start - $end";
                    break;
            }
        }

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="board-report-' . now()->format('Y-m-d') . '.xls"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $response = response()->view('boards.export-xls', [
            'board' => $board,
            'cards' => $cards,
            'period' => $period,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'pendingTasks' => $pendingTasks,
            'overdueTasks' => $overdueTasks,
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

        // Get report period string
        $period = 'All Time';
        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'this_week': $period = 'This Week'; break;
                case 'this_month': $period = 'This Month'; break;
                case 'last_month': $period = 'Last Month'; break;
                case 'custom':
                case 'custom_period':
                    $start = $request->start_date ? Carbon::parse($request->start_date)->format('M d, Y') : 'Beginning';
                    $end = $request->end_date ? Carbon::parse($request->end_date)->format('M d, Y') : 'End';
                    $period = "$start - $end";
                    break;
            }
        }

        // PDF display option
        $includeDesc = $request->boolean('include_desc', false);
        $includeComments = $request->boolean('include_comments', false);

        return view('boards.export-pdf', [
            'board' => $board,
            'cards' => $cards,
            'period' => $period,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'pendingTasks' => $pendingTasks,
            'overdueTasks' => $overdueTasks,
            'archivedTasks' => $archivedTasks,
            'memberStats' => $memberStats,
            'includeDesc' => $includeDesc,
            'includeComments' => $includeComments,
            'exportDate' => now()->format('M d, Y g:i A')
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
        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'this_week': $period = 'This Week'; break;
                case 'this_month': $period = 'This Month'; break;
                case 'last_month': $period = 'Last Month'; break;
                case 'custom':
                case 'custom_period':
                    $start = $request->start_date ? Carbon::parse($request->start_date)->format('M d, Y') : 'Beginning';
                    $end = $request->end_date ? Carbon::parse($request->end_date)->format('M d, Y') : 'End';
                    $period = "$start - $end";
                    break;
            }
        }

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
            'includeDesc'   => $includeDesc,
            'includeComments'=> $includeComments,
            'exportDate'    => now()->format('M d, Y g:i A'),
            // QC-specific: show revision count column
            'isQcReport'    => auth()->user()->isQc(),
        ]);

    }
}
