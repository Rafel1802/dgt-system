<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\ReportShare;
use App\Models\Shipment;
use App\Models\TechSupportCase;
use App\Models\User;
use App\Services\CrmReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmStaffReportController extends Controller
{
    /** Roles allowed to view any staff member's report, the Team Report, and the General Report — everyone else only ever sees their own staff report. */
    private const REPORT_ADMIN_ROLES = ['super-admin', 'admin-crm', 'boss'];

    public function __construct(private readonly CrmReportService $reports)
    {
    }

    /**
     * Team Report — company-wide totals only (no individual staff data), one
     * profile-style tab per domain plus a General Report combining
     * everything. Separated from the Staff Report page (below) because
     * mixing per-person profiles and company-wide aggregates on one page
     * made it unclear which number belonged to which.
     */
    public function index(Request $request): View|RedirectResponse
    {
        // The team roll-up isn't meaningful for an individual contributor —
        // send them straight to their own report instead.
        if (! auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES)) {
            return redirect()->route('crm.reports.show', auth()->user());
        }

        [$since, $until, $periodLabel, $granularity] = $this->reports->resolvePeriodFromFilters($request->only(['date_from', 'date_to', 'period']));
        [$domainReports, $totalSales, $trend] = $this->reports->teamReportData($since, $until);

        // Each domain tab can be filtered to its own period, independent of
        // the General Report's period above and of each other — falls back
        // to the General Report's period when the tab has no override of its
        // own yet, so a domain tab isn't just blank on first visit.
        $activeTab = in_array($request->get('tab'), CrmReportService::DOMAIN_KEYS) ? $request->get('tab') : 'general';
        $domainPeriods = [];
        $domainTabReports = [];
        $domainTabTrends = [];
        foreach (CrmReportService::DOMAIN_KEYS as $key) {
            $hasOwnFilter = $request->filled("{$key}_period") || $request->filled("{$key}_date_from") || $request->filled("{$key}_date_to");
            $filters = $hasOwnFilter
                ? ['period' => $request->get("{$key}_period"), 'date_from' => $request->get("{$key}_date_from"), 'date_to' => $request->get("{$key}_date_to")]
                : $request->only(['date_from', 'date_to', 'period']);

            [$dSince, $dUntil, $dLabel, $dGranularity] = $this->reports->resolvePeriodFromFilters($filters);
            $domainPeriods[$key] = ['label' => $dLabel, 'granularity' => $dGranularity];
            $domainTabReports[$key] = $this->reports->buildDomainReport($key, $dSince, $dUntil);
            $domainTabTrends[$key] = $this->reports->buildDomainDailyTrend($key, $dSince, $dUntil);
        }

        return view('crm.reports.index', compact(
            'granularity', 'periodLabel', 'domainReports', 'totalSales', 'trend',
            'activeTab', 'domainPeriods', 'domainTabReports', 'domainTabTrends'
        ));
    }

    /** Export the Team Report (same period filter as the page) as a PDF. */
    public function exportTeamPdf(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES), 403);

        [$since, $until, $periodLabel, $granularity] = $this->reports->resolvePeriodFromFilters($request->only(['date_from', 'date_to', 'period']));
        [$domainReports, $totalSales] = $this->reports->teamReportData($since, $until);

        $filename = 'team-report-' . ($granularity ?? 'custom') . '-' . now()->format('Ymd_His') . '.pdf';

        return Pdf::loadView('reports.team_report_export', compact('domainReports', 'totalSales', 'periodLabel'))
            ->download($filename);
    }

    /** Export the Team Report (same period filter as the page) as a CSV. */
    public function exportTeamCsv(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES), 403);

        [$since, $until, $periodLabel, $granularity] = $this->reports->resolvePeriodFromFilters($request->only(['date_from', 'date_to', 'period']));
        [$domainReports, $totalSales] = $this->reports->teamReportData($since, $until);

        $filename = 'team-report-' . ($granularity ?? 'custom') . '-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($domainReports, $totalSales, $periodLabel) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Team Report', $periodLabel]);
            fputcsv($out, ['Total Sales (eBay + Website)', number_format($totalSales, 2)]);
            fputcsv($out, []);
            foreach ($domainReports as $domain) {
                fputcsv($out, [$domain['label']]);
                fputcsv($out, ['Metric', 'Value']);
                foreach ($domain['metrics'] as $metricLabel => $value) {
                    fputcsv($out, [$metricLabel, in_array($metricLabel, $domain['money_keys']) ? number_format($value, 2) : $value]);
                }
                fputcsv($out, []);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Create a no-login share link for the Team Report with the current
     * period filter baked in — the public page re-runs the same query live,
     * so it always reflects current data for whoever holds the link.
     */
    public function shareTeam(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES), 403);

        $filters = $request->only(['date_from', 'date_to', 'period']);
        $share = ReportShare::createForTeam($filters, auth()->id());

        return redirect()->route('crm.reports.index', $filters)
            ->with('share_url', route('public.team-report.show', $share->token));
    }

    /** Staff Report — one flat list, each staff member appears exactly once regardless of how many CRM domains they're active in. */
    public function staff(Request $request): View|RedirectResponse
    {
        if (! auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES)) {
            return redirect()->route('crm.reports.show', auth()->user());
        }

        [$since, $until, $periodLabel, $granularity] = $this->reports->resolvePeriodFromFilters($request->only(['date_from', 'date_to', 'period']));

        // A domain counts as "active" for this person when they handled/were
        // assigned something there *within the selected period* — scoped to
        // the period (not a lifetime check) so switching to a quiet period
        // doesn't leave an all-zero card cluttering an otherwise-empty list.
        $members = $this->reports->staffPool()->map(function (User $u) use ($since, $until) {
            $activeDomains = $this->reports->activeDomains($u, $since, $until);

            $headline = [
                'website'      => $this->reports->distinctLeadsHandled($u->id, $since, $until),
                'ebay'         => $this->reports->distinctEbayHandled($u->id, $since, $until),
                'tech_support' => TechSupportCase::where('assigned_to', $u->id)->whereBetween('created_at', [$since, $until])->count(),
                'logistic'     => Shipment::where('assigned_to', $u->id)->whereBetween('created_at', [$since, $until])->count(),
            ];

            return [
                'user'          => $u,
                'activeDomains' => $activeDomains,
                'headline'      => $headline,
                'totalHandled'  => collect($activeDomains)->sum(fn ($d) => $headline[$d]),
            ];
        })->filter(fn ($row) => count($row['activeDomains']) > 0)->values();

        return view('crm.reports.staff', compact('members', 'granularity', 'periodLabel'));
    }

    /** Per-staff profile: KPI summary + a day/week/month activity chart across all 4 CRM domains */
    public function show(User $user, Request $request): View
    {
        abort_unless(
            auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES) || auth()->id() === $user->id,
            403, 'You can only view your own staff report.'
        );

        [$since, $until, $periodLabel, $granularity] = $this->reports->resolvePeriodFromFilters($request->only(['date_from', 'date_to', 'period']), 'week');

        $data = $this->reports->staffReportData($user, $since, $until, $periodLabel);

        return view('crm.reports.show', array_merge($data, compact('user', 'granularity', 'periodLabel')));
    }

    /** Export the same day/week/month activity report as CSV */
    public function export(User $user, Request $request): StreamedResponse
    {
        abort_unless(
            auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES) || auth()->id() === $user->id,
            403, 'You can only export your own staff report.'
        );

        [$since, $until, $periodLabel, $granularity] = $this->reports->resolvePeriodFromFilters($request->only(['date_from', 'date_to', 'period']), 'week');
        $chart = $this->reports->buildChart($user, $since, $until, $periodLabel);

        $filename = 'staff-report-' . Str::slug($user->name) . '-' . ($granularity ?? 'custom') . '.csv';

        return response()->streamDownload(function () use ($chart) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Period', 'CRM Website', 'eBay', 'Technical Support', 'Logistic', 'Total']);
            foreach ($chart['labels'] as $i => $label) {
                $row = [
                    $label,
                    $chart['datasets']['website'][$i],
                    $chart['datasets']['ebay'][$i],
                    $chart['datasets']['tech_support'][$i],
                    $chart['datasets']['logistic'][$i],
                ];
                $row[] = array_sum([$row[1], $row[2], $row[3], $row[4]]);
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Export the same staff profile report as a PDF. */
    public function exportPdf(User $user, Request $request)
    {
        abort_unless(
            auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES) || auth()->id() === $user->id,
            403, 'You can only export your own staff report.'
        );

        [$since, $until, $periodLabel, $granularity] = $this->reports->resolvePeriodFromFilters($request->only(['date_from', 'date_to', 'period']), 'week');
        $data = $this->reports->staffReportData($user, $since, $until, $periodLabel);

        $filename = 'staff-report-' . Str::slug($user->name) . '-' . ($granularity ?? 'custom') . '-' . now()->format('Ymd_His') . '.pdf';

        return Pdf::loadView('reports.staff_report_export', array_merge($data, compact('user', 'periodLabel')))
            ->download($filename);
    }

    /**
     * Create a no-login share link for this staff member's report with the
     * current period filter baked in — the public page re-runs the same
     * query live, so it always reflects current data for whoever holds the link.
     */
    public function shareStaff(User $user, Request $request): RedirectResponse
    {
        abort_unless(
            auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES) || auth()->id() === $user->id,
            403, 'You can only share your own staff report.'
        );

        $filters = $request->only(['date_from', 'date_to', 'period']);
        $share = ReportShare::createForStaff($user->id, $filters, auth()->id());

        return redirect()->route('crm.reports.show', ['user' => $user] + $filters)
            ->with('share_url', route('public.staff-report.show', $share->token));
    }
}
