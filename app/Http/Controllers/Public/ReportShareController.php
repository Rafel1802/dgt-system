<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ReportShare;
use App\Services\CrmReportService;
use Illuminate\View\View;

class ReportShareController extends Controller
{
    public function __construct(private readonly CrmReportService $reports)
    {
    }

    /**
     * Public, unauthenticated view of one staff member's report. Access
     * control is the token itself (unguessable, 40 chars) rather than a
     * login — the query re-runs live so the page always reflects current
     * data for whoever holds the link.
     */
    public function showStaff(string $token): View
    {
        $share = ReportShare::where('token', $token)->where('report_type', 'staff')->with('user')->firstOrFail();

        [$since, $until, $periodLabel] = $this->reports->resolvePeriodFromFilters($share->filters ?? [], 'week');
        $data = $this->reports->staffReportData($share->user, $since, $until, $periodLabel);

        return view('public.staff-report', array_merge($data, [
            'user'        => $share->user,
            'periodLabel' => $periodLabel,
        ]));
    }

    /** Public, unauthenticated view of the company-wide Team Report — same live-data convention as showStaff() above. */
    public function showTeam(string $token): View
    {
        $share = ReportShare::where('token', $token)->where('report_type', 'team')->firstOrFail();

        [$since, $until, $periodLabel] = $this->reports->resolvePeriodFromFilters($share->filters ?? []);
        [$domainReports, $totalSales, $trend] = $this->reports->teamReportData($since, $until);

        return view('public.team-report', compact('domainReports', 'totalSales', 'periodLabel', 'trend'));
    }
}
