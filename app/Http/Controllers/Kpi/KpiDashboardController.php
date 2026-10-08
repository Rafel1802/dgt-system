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
        $user = $request->user() ?: auth()->user();
        $isSupervisor = $user ? $user->isKpiSupervisor() : false;
        $userSquadId = $user ? $user->getKpiSquadId() : null;

                // 1. Periods & Filters
        $periods = KpiPeriod::orderBy('start_date', 'desc')->get();
        $selectedMonth = $request->get('month');
        $selectedYear = $request->get('year');
        $periodId = $request->get('period_id');
        $filterApplied = $request->has('filter_applied') || $request->filled('month') || $request->filled('year') || $request->filled('search') || $request->filled('member_id');

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
        } elseif ($selectedYear && !$selectedMonth) {
            // Filter by full year (All Months in selected year)
            $currentPeriod = $periods->first(fn($p) => str_contains($p->name, (string)$selectedYear));
            if (!$currentPeriod) {
                $currentPeriod = new KpiPeriod([
                    'name' => "Year {$selectedYear}",
                    'start_date' => "{$selectedYear}-01-01",
                    'end_date' => "{$selectedYear}-12-31",
                    'status' => 'Open',
                    'description' => "Annual KPI Evaluation Cycle for {$selectedYear}."
                ]);
            }
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

                // 4. Staff Reviews for selected date / period
        $reviewsQuery = KpiReview::with(['user', 'reviewer', 'squad', 'period'])
            ->when($selectedSquadId, fn($q) => $q->where('squad_id', $selectedSquadId))
            ->when(!$isSupervisor && $userSquadId, fn($q) => $q->where('squad_id', $userSquadId));

        if ($selectedMonth && $selectedYear) {
            $reviewsQuery->where(function($q) use ($selectedMonth, $selectedYear, $currentPeriod) {
                $q->where(function($sub) use ($selectedMonth, $selectedYear) {
                    $sub->whereMonth('evaluation_date', (int)$selectedMonth)
                        ->whereYear('evaluation_date', (int)$selectedYear);
                })
                ->orWhere(function($sub) use ($selectedMonth, $selectedYear) {
                    $sub->whereMonth('created_at', (int)$selectedMonth)
                        ->whereYear('created_at', (int)$selectedYear);
                })
                ->orWhere('kpi_period_id', $currentPeriod?->id);
            });
        } elseif ($selectedYear) {
            $reviewsQuery->where(function($q) use ($selectedYear) {
                $q->whereYear('evaluation_date', (int)$selectedYear)
                  ->orWhereYear('created_at', (int)$selectedYear)
                  ->orWhereHas('period', fn($p) => $p->where('name', 'like', "%{$selectedYear}%"));
            });
        } elseif ($selectedMonth) {
            $reviewsQuery->where(function($q) use ($selectedMonth) {
                $q->whereMonth('evaluation_date', (int)$selectedMonth)
                  ->orWhereMonth('created_at', (int)$selectedMonth);
            });
        } elseif ($currentPeriod && $currentPeriod->id) {
            $reviewsQuery->where('kpi_period_id', $currentPeriod->id);
        }

        $reviews = $reviewsQuery->orderBy('evaluation_date', 'desc')->orderBy('created_at', 'desc')->get()->keyBy('user_id');

        // Detect all ratings in date: if filtering is applied, hide staff members who do NOT have a rate in that date
        if ($filterApplied && ($selectedYear || $selectedMonth)) {
            $ratedUserIds = $reviews->pluck('user_id')->unique()->toArray();
            $staffMembers = $staffMembers->whereIn('id', $ratedUserIds);
        }

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
