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

        // 1. Periods & Filters
        $periods = KpiPeriod::orderBy('start_date', 'desc')->get();
        $selectedMonth = $request->get('month');
        $selectedYear = $request->get('year');
        $periodId = $request->get('period_id');

        if ($selectedMonth && $selectedYear) {
            $periodName = date('F Y', mktime(0, 0, 0, (int)$selectedMonth, 1, (int)$selectedYear));
            $startDate = date('Y-m-01', mktime(0, 0, 0, (int)$selectedMonth, 1, (int)$selectedYear));
            $endDate = date('Y-m-t', mktime(0, 0, 0, (int)$selectedMonth, 1, (int)$selectedYear));
            $currentPeriod = KpiPeriod::firstOrCreate(
                ['name' => $periodName],
                [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'Open',
                    'description' => "Monthly KPI Evaluation Cycle for {$periodName}."
                ]
            );
        } elseif ($periodId) {
            $currentPeriod = KpiPeriod::find($periodId);
        } else {
            $currentPeriod = $periods->firstWhere('status', 'Open') ?? $periods->first();
        }

        // 2. Squads query
        $squadsQuery = KpiSquad::with(['lead', 'members']);
        if (!$isSupervisor && $userSquadId) {
            $squadsQuery->where('id', $userSquadId);
        }
        $squads = $squadsQuery->get();

        $selectedSquadId = $request->get('squad_id');
        if (!$isSupervisor) {
            $selectedSquadId = $userSquadId ?: 1;
        }

        $currentSquad = $selectedSquadId
            ? $squads->firstWhere('id', $selectedSquadId)
            : $squads->first();

        // 3. Staff members (with Search & Member filter)
        $rawMembers = $currentSquad ? $currentSquad->members : collect();

        $search = trim($request->get('search', ''));
        $memberId = $request->get('member_id');

        $staffMembers = $rawMembers;
        if (!empty($search)) {
            $staffMembers = $staffMembers->filter(function($m) use ($search) {
                return str_contains(strtolower($m->name), strtolower($search))
                    || str_contains(strtolower($m->username ?? ''), strtolower($search))
                    || str_contains(strtolower($m->pivot->role_title ?? ''), strtolower($search));
            });
        }
        if (!empty($memberId)) {
            $staffMembers = $staffMembers->where('id', (int)$memberId);
        }

        // 4. Staff Reviews for selected period
        $reviewsQuery = KpiReview::with(['user', 'reviewer', 'squad'])
            ->when($currentPeriod, fn($q) => $q->where('kpi_period_id', $currentPeriod->id))
            ->when($selectedSquadId, fn($q) => $q->where('squad_id', $selectedSquadId))
            ->when(!$isSupervisor && $userSquadId, fn($q) => $q->where('squad_id', $userSquadId));
        $reviews = $reviewsQuery->get()->keyBy('user_id');

        // 5. Complete KPI History for all squad members
        $allUserReviews = KpiReview::with(['period', 'reviewer', 'squad'])
            ->whereIn('user_id', $rawMembers->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('user_id');

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
            'rawMembers',
            'staffMembers',
            'reviews',
            'allUserReviews',
            'reports',
            'isSupervisor',
            'userSquadId',
            'totalStaffCount',
            'evaluatedCount',
            'avgKpiScore',
            'outstandingCount',
            'search',
            'memberId',
            'selectedMonth',
            'selectedYear'
        ));
    }
}
