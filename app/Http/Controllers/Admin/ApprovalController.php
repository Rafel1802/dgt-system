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
use Illuminate\Http\RedirectResponse;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly KanbanService $kanbanService
    ) {}

    // ── Team classification: checks the card's `label` field, Labels pivot, and SMM team label ──
    private function matchesTeam(Card $card, string $keyword): bool
    {
        return $this->kanbanService->matchesTeam($card, $keyword);
    }

    /**
     * Boss / Supervisor approval queue — data from selected boards (all by default).
     */
    public function index(Request $request): View|RedirectResponse
    {
        $user = auth()->user();
        abort_unless(
            $user->hasAnyRole(['super-admin', 'admin-digital', 'admin-crm', 'boss']) || $user->isQc(),
            403,
            'Access restricted to supervisors, admins and boss.'
        );

        // For QC users (e.g. Mr. Dara), the Approval Queue is consolidated directly inside the Dashboard
        if ($user->isQc() || str_contains(strtolower($user->name ?? ''), 'dara') || str_contains(strtolower($user->team_role ?? ''), 'qc')) {
            return redirect()->route('dashboard');
        }

        // ── Period for the "Completed Tasks" section ─────────────────────────
        $period = $request->input('period', 'today'); // today | week | month
        $selectedBoardIds = $request->input('board_ids');

        $data = $this->kanbanService->getApprovalQueueData($selectedBoardIds, $period, $user);

        return view('supervisor.approvals', $data);
    }

    /**
     * Fetch approval counts for a custom date range — selected boards.
     */
    public function customRange(Request $request): JsonResponse
    {
        $user = auth()->user();
        abort_unless(
            $user->hasAnyRole(['super-admin', 'admin-digital', 'admin-crm', 'boss']) || $user->isQc(),
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

        $approvedCards = Card::with(['boardList', 'labels', 'board.workspace'])
            ->whereIn('board_id', $boardIds)
            ->whereHas('boardList', fn($q) => $q->where('name', 'like', '%Approved%'))
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('approved_at', [$start, $end])
                      ->orWhere(function ($sub) use ($start, $end) {
                          $sub->whereNull('approved_at')
                              ->whereBetween('updated_at', [$start, $end]);
                      });
            })
            ->get();

        $smmCards     = $approvedCards->filter(fn($c) => $this->matchesTeam($c, 'SMM'));
        $nonSmmCards  = $approvedCards->reject(fn($c) => $this->matchesTeam($c, 'SMM'));
        $smmCount     = $smmCards->count();
        $graphicCount = $nonSmmCards->filter(fn($c) => $this->matchesTeam($c, 'Graphic'))->count();
        $videoCount   = $nonSmmCards->filter(fn($c) => $this->matchesTeam($c, 'Video'))->count();
        $listingCount = $nonSmmCards->filter(fn($c) => $this->matchesTeam($c, 'Listing'))->count();
        $contentCount = $nonSmmCards->filter(fn($c) => $this->matchesTeam($c, 'Content'))->count();
        $qcCount      = $nonSmmCards->filter(fn($c) =>
            $this->matchesTeam($c, 'QC') || $this->matchesTeam($c, 'Text')
        )->count();

        return response()->json([
            'total'   => $graphicCount + $videoCount + $listingCount + $contentCount + $qcCount + $smmCount,
            'graphic' => $graphicCount,
            'video'   => $videoCount,
            'listing' => $listingCount,
            'content' => $contentCount,
            'qc'      => $qcCount,
            'smm'     => $smmCount,
        ]);
    }
}
