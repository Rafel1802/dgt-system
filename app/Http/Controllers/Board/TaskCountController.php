<?php

namespace App\Http\Controllers\Board;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Card;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskCountController extends Controller
{
    /**
     * Display personal tasks count dashboard for planning boards,
     * including 1-day-before due date detection and warning alerts.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $warningData = $user->getDeadlineWarningData();

        $boards = $warningData['boards'];
        $userCards = $warningData['userCards'];
        $warningTasks = $warningData['warningTasks'];
        $dueTomorrowCount = $warningData['dueTomorrowCount'];
        $dueTodayCount = $warningData['dueTodayCount'];
        $overdueCount = $warningData['overdueCount'];
        $totalWarningCount = $warningData['totalWarningCount'];
        $approvedSyncGroupIds = $warningData['approvedSyncGroupIds'] ?? [];

        $selectedBoardId = $request->input('board_id');
        if ($selectedBoardId && ! $boards->contains('id', (int) $selectedBoardId)) {
            $selectedBoardId = null;
        }
        $boardBoxes = [];

        // ── Board Boxes Preparation ──
        foreach ($boards as $board) {
            $wsName = $board->workspace?->name ?? 'Team Board';

            // Extract month and year from board name using UTF-8 aware regex
            $monthYear = '';
            if (preg_match('/([A-Za-z]+ \d{4})/u', $board->name, $matches)) {
                $monthYear = $matches[1];
            } else {
                $monthYear = $board->created_at ? $board->created_at->format('F Y') : Carbon::now()->format('F Y');
            }

            // Clean title without redundant month/year, properly handling UTF-8 dashes
            $cleanName = trim(preg_replace('/\s*[-–—]\s*[A-Za-z]+ \d{4}/u', '', $board->name));
            $cleanName = trim(preg_replace('/[\x{FFFD}\x{00A0}]+/u', ' ', $cleanName));
            $cleanName = trim(preg_replace('/\s+/', ' ', $cleanName));
            if (empty($cleanName)) {
                $cleanName = $board->name;
            }

            // Determine team key for styling / colors
            $combined = strtolower($wsName . ' ' . $board->name);
            $teamKey = match (true) {
                str_contains($combined, 'video')   => 'video',
                str_contains($combined, 'graphic') => 'graphic',
                str_contains($combined, 'listing') => 'listing',
                str_contains($combined, 'content') => 'content',
                str_contains($combined, 'qc')      => 'qc',
                str_contains($combined, 'smm') || str_contains($combined, 'social') => 'smm',
                default => 'default',
            };

            // Cards in this board assigned to user
            $boardCards = $userCards->where('board_id', $board->id);
            if ($boardCards->isEmpty() && $board->user_tasks_count > 0) {
                // Fallback if cards on this board are unassigned
                $boardCards = Card::with(['boardList', 'labels', 'checklists.items'])
                    ->where('board_id', $board->id)
                    ->where('is_archived', false)
                    ->get();
            }

            // Breakdown by list / week
            $weeks = [];
            $boardLists = $board->lists ?? collect();
            foreach ($boardLists as $list) {
                if (stripos($list->name, 'Week') !== false || stripos($list->name, 'Todo') !== false || stripos($list->name, 'Progress') !== false || stripos($list->name, 'Urgent') !== false) {
                    $cnt = $boardCards->filter(fn($c) => $c->board_list_id == $list->id)->count();
                    $weeks[$list->name] = $cnt;
                }
            }

            if (empty($weeks)) {
                foreach ($boardLists->take(4) as $list) {
                    $weeks[$list->name] = $boardCards->filter(fn($c) => $c->board_list_id == $list->id)->count();
                }
            }

            $boardBoxes[] = [
                'board'          => $board,
                'workspace_name' => $wsName,
                'board_name'     => $board->name,
                'clean_name'     => $cleanName,
                'month_year'     => $monthYear,
                'team_key'       => $teamKey,
                'task_count'     => $board->user_tasks_count,
                'weeks'          => $weeks,
            ];
        }

        // Apply board filter to the tasks list
        $filteredCards = $selectedBoardId
            ? $userCards->filter(fn($c) => $c->board_id == $selectedBoardId)
            : $userCards;

        $totalTasksCount = $boards->sum('user_tasks_count');

        return view('boards.tasks-count', compact(
            'boards',
            'boardBoxes',
            'filteredCards',
            'selectedBoardId',
            'totalTasksCount',
            'warningTasks',
            'dueTomorrowCount',
            'dueTodayCount',
            'overdueCount',
            'totalWarningCount',
            'approvedSyncGroupIds'
        ));
    }
}
