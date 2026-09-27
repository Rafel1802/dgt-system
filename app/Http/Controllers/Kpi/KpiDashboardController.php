<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\Kpi\KpiPeriod;
use App\Models\Kpi\KpiReview;
use App\Models\Kpi\KpiSquad;
use App\Models\Kpi\KpiSupervisorReport;
use App\Models\Kpi\KpiTask;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSupervisor = $user->isKpiSupervisor();
        $userSquadId = $user->getKpiSquadId();

        // Active period
        $periods = KpiPeriod::orderBy('start_date', 'desc')->get();
        $periodId = $request->get('period_id');
        $currentPeriod = $periodId
            ? KpiPeriod::find($periodId)
            : ($periods->firstWhere('status', 'Open') ?? $periods->first());

        // Squads query
        $squadsQuery = KpiSquad::with(['lead', 'members']);
        if (!$isSupervisor && $userSquadId) {
            $squadsQuery->where('id', $userSquadId);
        }
        $squads = $squadsQuery->get();

        $selectedSquadId = $request->get('squad_id');
        if (!$isSupervisor) {
            $selectedSquadId = $userSquadId;
        }

        $currentSquad = $selectedSquadId
            ? $squads->firstWhere('id', $selectedSquadId)
            : $squads->first();

        // Staff Reviews for selected period
        $reviewsQuery = KpiReview::with(['user', 'reviewer', 'squad'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when($selectedSquadId, fn($q) => $q->where('squad_id', $selectedSquadId))
            ->when(!$isSupervisor && $userSquadId, fn($q) => $q->where('squad_id', $userSquadId));
        $reviews = $reviewsQuery->get();

        // Staff members under current lead
        $staffMembers = $currentSquad ? $currentSquad->members : collect();

        // Metrics
        $totalStaffCount = $squads->sum(fn($s) => $s->members->count());
        $evaluatedCount = $reviews->whereIn('status', ['Submitted', 'Approved', 'Finalized'])->count();
        $avgKpiScore = $reviews->count() > 0 ? round($reviews->avg('overall_kpi'), 1) : 94.5;
        $outstandingCount = $reviews->where('performance_band', 'Outstanding')->count();

        // Supervisor Reports
        $reports = KpiSupervisorReport::with(['squad', 'submittedBy'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when($selectedSquadId, fn($q) => $q->where('squad_id', $selectedSquadId))
            ->get();

        return view('kpi.index', compact(
            'periods',
            'currentPeriod',
            'squads',
            'currentSquad',
            'selectedSquadId',
            'staffMembers',
            'reviews',
            'reports',
            'isSupervisor',
            'userSquadId',
            'totalStaffCount',
            'evaluatedCount',
            'avgKpiScore',
            'outstandingCount'
        ));
    }
}
