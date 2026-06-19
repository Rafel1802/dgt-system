<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Card;
use App\Services\KanbanService;
use Carbon\Carbon;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly KanbanService $kanbanService
    ) {}

    // ── Team classification: checks the card's `label` field AND Labels pivot ──
    private function matchesTeam(Card $card, string $keyword): bool
    {
        // Primary: card->label string field (e.g. "video", "Graphic", "listing", "CRM")
        if (stripos($card->label ?? '', $keyword) !== false) {
            return true;
        }
        // Secondary: Labels pivot (tag names like "Graphic Team", "Video Team", etc.)
        if ($card->relationLoaded('labels') &&
            $card->labels->contains(fn($l) => stripos($l->name ?? '', $keyword) !== false)) {
            return true;
        }
        return false;
    }

    /**
     * Boss / Supervisor approval queue — data from selected boards (all by default).
     */
    public function index(Request $request): View
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'admin-crm', 'boss']),
            403,
            'Access restricted to supervisors, admins and boss.'
        );

        // ── Period for the "Completed Tasks" section ─────────────────────────
        $period = $request->input('period', 'today'); // today | week | month

        // ── All boards that have an "Approved" list (workflow boards) ─────────
        $availableBoards = Board::with('workspace')
            ->where('is_archived', false)
            ->whereHas('lists', fn($q) => $q->where('name', 'like', '%Approved%'))
            ->orderBy('name')
            ->get();

        // ── Selected board IDs (admin can filter; default = all workflow boards) ─
        $selectedBoardIds = $request->input('board_ids');
        if ($selectedBoardIds && count($selectedBoardIds) > 0) {
            $selectedBoardIds = array_map('intval', $selectedBoardIds);
        } else {
            $selectedBoardIds = $availableBoards->pluck('id')->toArray();
        }

        // ── Pending review cards (in selected boards) ─────────────────────────
        $pendingCards = Card::with([
            'boardList',
            'board',
            'creator:id,name,avatar',
            'assignees:id,name,avatar',
            'checklists.items',
            'files',
            'labels',
            'comments' => fn($q) => $q->where('is_system', false)->latest()->limit(1),
            'comments.user:id,name,avatar',
        ])
        ->whereIn('board_id', $selectedBoardIds)
        ->whereHas('board')
        ->where('status', CardStatus::Review->value)
        ->where('is_archived', false)
        ->orderByRaw("FIELD(priority, 'urgent','high','medium','low')")
        ->orderBy('deadline')
        ->orderBy('created_at')
        ->get();

        // ── All active cards in selected boards (for pipeline stats) ──────────
        // Requires board_id NOT NULL and board still exists (not soft-deleted)
        $activeCards = Card::with(['boardList', 'labels'])
            ->whereIn('board_id', $selectedBoardIds)
            ->whereNotNull('board_id')
            ->whereHas('board')  // exclude orphaned cards whose board was deleted
            ->where('is_archived', false)
            ->get();

        // ── Helper: breakdown by team (checks card->label field first) ────────
        $getBreakdownForCards = function($cards) {
            return [
                'total'   => $cards->count(),
                'graphic' => $cards->filter(fn($c) => $this->matchesTeam($c, 'Graphic'))->count(),
                'video'   => $cards->filter(fn($c) => $this->matchesTeam($c, 'Video'))->count(),
                'listing' => $cards->filter(fn($c) =>
                    $this->matchesTeam($c, 'Listing') || $this->matchesTeam($c, 'SM')
                )->count(),
                'content' => $cards->filter(fn($c) => $this->matchesTeam($c, 'Content'))->count(),
                'qc'      => $cards->filter(fn($c) =>
                    $this->matchesTeam($c, 'QC') || $this->matchesTeam($c, 'Text')
                )->count(),
            ];
        };

        // ── Pipeline stage breakdown (by list name keyword) ───────────────────
        $getBreakdown = function($keyword) use ($activeCards, $getBreakdownForCards) {
            $cards = $activeCards->filter(fn($c) => stripos($c->boardList?->name ?? '', $keyword) !== false);
            return $getBreakdownForCards($cards);
        };

        // ── Urgent: ONLY cards physically in a list named "Urgent" ────────────
        // Do NOT use priority field — priority is a display badge, not a pipeline stage.
        // A card with priority='urgent' sitting in 'Drafting' is NOT an urgent pipeline item.
        $urgentCards = $activeCards->filter(fn($c) =>
            stripos($c->boardList?->name ?? '', 'Urgent') !== false
        );
        $urgent  = $getBreakdownForCards($urgentCards);
        $overdue = $activeCards->filter(fn($c) => $c->isOverdue())->count();

        // ── Date window for selected period ───────────────────────────────────
        [$rangeStart, $rangeEnd] = match($period) {
            'week'  => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            default => [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()],
        };

        // ── Helper: query approved cards for a given date window ──────────────
        $queryApproved = function(?Carbon $start = null, ?Carbon $end = null) use ($selectedBoardIds) {
            $q = Card::with(['boardList', 'labels'])
                ->whereIn('board_id', $selectedBoardIds)
                ->whereHas('boardList', fn($bl) => $bl->where('name', 'like', '%Approved%'));

            if ($start && $end) {
                // Cards physically sitting in "Approved" list, updated/approved in window
                $q->where(function($sub) use ($start, $end) {
                    $sub->whereBetween('approved_at', [$start, $end])
                        ->orWhereBetween('updated_at', [$start, $end]);
                });
            }
            return $q->get();
        };

        // ── Completed tasks for selected period ───────────────────────────────
        $approvedCards = $queryApproved($rangeStart, $rangeEnd);

        // ── Preset quick-comparison tabs ──────────────────────────────────────
        $todayStart = Carbon::now()->startOfDay();
        $todayEnd   = Carbon::now()->endOfDay();
        $todayCards = $queryApproved($todayStart, $todayEnd);

        $weekCards = $queryApproved(Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek());
        $monthCards = $queryApproved(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

        // Total ever completed (all time) for reference
        $allTimeCards = $queryApproved(); // no date filter

        $stats = [
            'drafting'          => $getBreakdown('Drafting'),
            'head_review'       => $getBreakdown('Head Review'),
            'qc_review'         => $getBreakdown('QC'),
            'supervisor_review' => $getBreakdown('Supervisor Review'),
            'urgent'            => $urgent,
            'overdue'           => $overdue,
            // period-selected
            'approved'          => $getBreakdownForCards($approvedCards),
            // preset tab totals
            'approved_today'    => $getBreakdownForCards($todayCards),
            'approved_week'     => $getBreakdownForCards($weekCards),
            'approved_month'    => $getBreakdownForCards($monthCards),
            'approved_all'      => $getBreakdownForCards($allTimeCards),
        ];

        return view('supervisor.approvals', compact(
            'pendingCards', 'stats', 'period', 'availableBoards', 'selectedBoardIds'
        ));
    }

    /**
     * Fetch approval counts for a custom date range — selected boards.
     */
    public function customRange(Request $request): JsonResponse
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'admin-crm', 'boss']),
            403
        );

        $request->validate([
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'board_ids'   => 'nullable|array',
            'board_ids.*' => 'integer',
        ]);

        $start = Carbon::parse($request->start_date)->startOfDay();
        $end   = Carbon::parse($request->end_date)->endOfDay();

        // Use provided board IDs or all workflow boards
        $boardIds = $request->board_ids;
        if (empty($boardIds)) {
            $boardIds = Board::where('is_archived', false)
                ->whereHas('lists', fn($q) => $q->where('name', 'like', '%Approved%'))
                ->pluck('id')
                ->toArray();
        }

        $approvedCards = Card::with(['boardList', 'labels'])
            ->whereIn('board_id', $boardIds)
            ->whereHas('boardList', fn($q) => $q->where('name', 'like', '%Approved%'))
            ->where(function($q) use ($start, $end) {
                $q->whereBetween('approved_at', [$start, $end])
                  ->orWhereBetween('updated_at', [$start, $end]);
            })
            ->get();

        return response()->json([
            'total'   => $approvedCards->count(),
            'graphic' => $approvedCards->filter(fn($c) => $this->matchesTeam($c, 'Graphic'))->count(),
            'video'   => $approvedCards->filter(fn($c) => $this->matchesTeam($c, 'Video'))->count(),
            'listing' => $approvedCards->filter(fn($c) =>
                $this->matchesTeam($c, 'Listing') || $this->matchesTeam($c, 'SM')
            )->count(),
            'content' => $approvedCards->filter(fn($c) => $this->matchesTeam($c, 'Content'))->count(),
            'qc'      => $approvedCards->filter(fn($c) =>
                $this->matchesTeam($c, 'QC') || $this->matchesTeam($c, 'Text')
            )->count(),
        ]);
    }
}
