<?php

namespace App\Http\Controllers\CRM;

use App\Enums\WebsiteLeadStatus;
use App\Http\Controllers\Controller;
use App\Models\CallReport;
use App\Models\EbayCustomerHandlerHistory;
use App\Models\EbayCustomerOrder;
use App\Models\EbayCustomerOrderItem;
use App\Models\EbayCustomerRecord;
use App\Models\Lead;
use App\Models\LeadProduct;
use App\Models\Shipment;
use App\Models\TechSupportCase;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmStaffReportController extends Controller
{
    /** Roles allowed to view any staff member's report — everyone else only ever sees their own. */
    private const REPORT_ADMIN_ROLES = ['super-admin', 'admin-crm', 'boss'];

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

        [$granularity, $periodLabel, $domainReports, $totalSales] = $this->teamReportData($request);

        return view('crm.reports.index', compact('granularity', 'periodLabel', 'domainReports', 'totalSales'));
    }

    /** Export the Team Report (same period filter as the page) as a PDF. */
    public function exportTeamPdf(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES), 403);

        [$granularity, $periodLabel, $domainReports, $totalSales] = $this->teamReportData($request);

        $filename = 'team-report-' . ($granularity ?? 'custom') . '-' . now()->format('Ymd_His') . '.pdf';

        return Pdf::loadView('reports.team_report_export', compact('domainReports', 'totalSales', 'periodLabel'))
            ->download($filename);
    }

    /** Export the Team Report (same period filter as the page) as a CSV. */
    public function exportTeamCsv(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES), 403);

        [$granularity, $periodLabel, $domainReports, $totalSales] = $this->teamReportData($request);

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

    /** Shared data-build for the Team Report page and its PDF/CSV exports. */
    private function teamReportData(Request $request): array
    {
        [$since, $until, $periodLabel, $granularity] = $this->resolvePeriod($request);

        $domainReports = $this->buildDomainReports($since, $until);
        $totalSales = $domainReports['ebay']['metrics']['Sales'] + $domainReports['website']['metrics']['Sales'];

        return [$granularity, $periodLabel, $domainReports, $totalSales];
    }

    /** Staff Report — team-grouped, clickable individual staff profiles (no company-wide totals). */
    public function staff(Request $request): View|RedirectResponse
    {
        if (! auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES)) {
            return redirect()->route('crm.reports.show', auth()->user());
        }

        [$since, $until, $periodLabel, $granularity] = $this->resolvePeriod($request);

        $staffPool = $this->staffPool();

        // A user shows up under a team if they have ever handled/been assigned
        // anything in that domain — there's no separate department field, so
        // team membership is inferred from activity, same as the rest of this
        // report already does per-metric.
        $teams = [
            'website' => [
                'label' => 'CRM-Website',
                'members' => $staffPool->filter(fn ($u) => Lead::where('handled_by', $u->id)->exists())
                    ->map(fn ($u) => $this->websiteSummary($u, $since, $until))->values(),
            ],
            'ebay' => [
                'label' => 'eBay',
                'members' => $staffPool->filter(fn ($u) => EbayCustomerHandlerHistory::where('user_id', $u->id)->exists())
                    ->map(fn ($u) => $this->ebaySummary($u, $since, $until))->values(),
            ],
            'tech_support' => [
                'label' => 'Technical Support',
                'members' => $staffPool->filter(fn ($u) => TechSupportCase::where('assigned_to', $u->id)->exists())
                    ->map(fn ($u) => $this->techSupportSummary($u, $since, $until))->values(),
            ],
            'logistic' => [
                'label' => 'Logistic',
                'members' => $staffPool->filter(fn ($u) => Shipment::where('assigned_to', $u->id)->exists())
                    ->map(fn ($u) => $this->logisticSummary($u, $since, $until))->values(),
            ],
        ];

        return view('crm.reports.staff', compact('teams', 'granularity', 'periodLabel'));
    }

    /** Per-staff profile: KPI summary + a day/week/month activity chart across all 4 CRM domains */
    public function show(User $user, Request $request): View
    {
        abort_unless(
            auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES) || auth()->id() === $user->id,
            403, 'You can only view your own staff report.'
        );

        [$since, $until, $periodLabel, $granularity] = $this->resolvePeriod($request, 'week');

        $chart = $this->buildChart($user, $since, $until, $periodLabel);

        $summary = [
            'website'      => $this->websiteSummary($user, $since, $until),
            'ebay'         => $this->ebaySummary($user, $since, $until),
            'tech_support' => $this->techSupportSummary($user, $since, $until),
            'logistic'     => $this->logisticSummary($user, $since, $until),
        ];

        // Only show a domain's card when this user actually has activity
        // there — same "team membership by activity" rule the team index
        // page already uses to decide who's listed under each team.
        $activeDomains = array_keys(array_filter([
            'website'      => Lead::where('handled_by', $user->id)->exists(),
            'ebay'         => EbayCustomerHandlerHistory::where('user_id', $user->id)->exists(),
            'tech_support' => TechSupportCase::where('assigned_to', $user->id)->exists(),
            'logistic'     => Shipment::where('assigned_to', $user->id)->exists(),
        ]));

        return view('crm.reports.show', compact('user', 'chart', 'granularity', 'periodLabel', 'summary', 'activeDomains'));
    }

    /** Export the same day/week/month activity report as CSV */
    public function export(User $user, Request $request): StreamedResponse
    {
        abort_unless(
            auth()->user()->hasAnyRole(self::REPORT_ADMIN_ROLES) || auth()->id() === $user->id,
            403, 'You can only export your own staff report.'
        );

        [$since, $until, $periodLabel, $granularity] = $this->resolvePeriod($request, 'week');
        $chart = $this->buildChart($user, $since, $until, $periodLabel);

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

    /** Everyone eligible to appear on this report: any CRM-facing role, including Tech Support */
    private function staffPool()
    {
        return User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin', 'admin-crm', 'sales-crm', 'boss', 'tech-support']))
            ->orderBy('name')
            ->get();
    }

    private function websiteSummary(User $user, Carbon $since, Carbon $until): array
    {
        return [
            'user'           => $user,
            'crm_handled'    => Lead::where('handled_by', $user->id)->whereBetween('created_at', [$since, $until])->count(),
            'crm_sales'      => Lead::where('handled_by', $user->id)->where('status', WebsiteLeadStatus::Successful)->whereBetween('updated_at', [$since, $until])->count(),
            'calls_answered' => CallReport::where('answered_by', $user->id)->whereBetween('occurred_at', [$since, $until])->count(),
        ];
    }

    private function ebaySummary(User $user, Carbon $since, Carbon $until): array
    {
        return [
            'user'         => $user,
            'ebay_handled' => EbayCustomerHandlerHistory::where('user_id', $user->id)->whereBetween('started_at', [$since, $until])->count(),
            'neg_solved'   => EbayCustomerHandlerHistory::where('user_id', $user->id)
                ->whereHas('record', fn ($q) => $q->where('negative_feedback_resolved', true)->whereBetween('negative_feedback_resolved_at', [$since, $until]))
                ->count(),
        ];
    }

    private function techSupportSummary(User $user, Carbon $since, Carbon $until): array
    {
        return [
            'user'      => $user,
            'assigned'  => TechSupportCase::where('assigned_to', $user->id)->whereBetween('created_at', [$since, $until])->count(),
            'resolved'  => TechSupportCase::where('assigned_to', $user->id)->where('status', TechSupportCase::STATUS_RESOLVED)->whereBetween('resolved_at', [$since, $until])->count(),
        ];
    }

    private function logisticSummary(User $user, Carbon $since, Carbon $until): array
    {
        return [
            'user'     => $user,
            'assigned' => Shipment::where('assigned_to', $user->id)->whereBetween('created_at', [$since, $until])->count(),
            'complete' => Shipment::where('assigned_to', $user->id)->where('status', Shipment::STATUS_COMPLETE)->whereBetween('updated_at', [$since, $until])->count(),
        ];
    }

    /**
     * Real eBay sales: sum of order-item prices logged through the "New
     * Order" flow (EbayCustomerOrder/EbayCustomerOrderItem) — the only eBay
     * money-tracking path that's actually populated by real usage (confirmed
     * against live data; EbayStore.total_sales is a manually-typed field
     * nobody keeps updated, and the Offer→Order pipeline has zero rows).
     */
    private function ebaySalesTotal(Carbon $since, Carbon $until): float
    {
        return (float) EbayCustomerOrderItem::whereHas('order', fn ($q) => $q->whereBetween('created_at', [$since, $until]))
            ->sum('price');
    }

    /**
     * Real Website CRM sales: sum of every logged order's line items
     * (LeadProduct, via WebsiteCrmController::createLeadOrder()) — counted
     * unconditionally, not gated by the lead's current status. A lead can
     * now log repeat orders independent of "Successful" (see
     * WebsiteCrmController::storeOrder()), same as eBay sales aren't gated
     * by tab_type — a logged order is a real sale regardless of pipeline state.
     */
    private function websiteSalesTotal(Carbon $since, Carbon $until): float
    {
        return (float) LeadProduct::whereBetween('created_at', [$since, $until])
            ->get()
            ->sum(fn ($p) => $p->price * $p->quantity);
    }

    /**
     * Team-wide totals for the "Team Reports" section, one profile-style
     * card per domain (Logistic / eBay / Website / Technical Support) —
     * mirrors the per-user summary cards above it, just aggregated across
     * the whole team instead of one person. `money_keys` tells the view
     * which metrics to render with a $ prefix.
     */
    private function buildDomainReports(Carbon $since, Carbon $until): array
    {
        return [
            'logistic' => [
                'label' => 'Logistic', 'color' => '#10b981', 'icon' => '🚚',
                'metrics' => [
                    'Shipments Assigned' => Shipment::whereBetween('created_at', [$since, $until])->count(),
                    'Complete'           => Shipment::where('status', Shipment::STATUS_COMPLETE)->whereBetween('updated_at', [$since, $until])->count(),
                ],
                'money_keys' => [],
            ],
            'ebay' => [
                'label' => 'eBay', 'color' => '#f59e0b', 'icon' => '🛒',
                'metrics' => [
                    'Total Customer'    => EbayCustomerRecord::whereBetween('created_at', [$since, $until])->count(),
                    'Negative Feedback' => EbayCustomerRecord::where('tab_type', EbayCustomerRecord::TAB_NEGATIVES)->whereBetween('created_at', [$since, $until])->count(),
                    'Solved'            => EbayCustomerRecord::where('negative_feedback_resolved', true)->whereBetween('negative_feedback_resolved_at', [$since, $until])->count(),
                    'Total Order'       => EbayCustomerOrder::whereBetween('ordered_at', [$since, $until])->count(),
                    'Sales'             => $this->ebaySalesTotal($since, $until),
                ],
                'money_keys' => ['Sales'],
            ],
            'website' => [
                'label' => 'Website', 'color' => '#6366f1', 'icon' => '🌐',
                'metrics' => [
                    'Total Customer'    => Lead::whereBetween('created_at', [$since, $until])->count(),
                    'Successful Leads'  => Lead::where('status', WebsiteLeadStatus::Successful)->whereBetween('updated_at', [$since, $until])->count(),
                    'Calls Answered'    => CallReport::whereBetween('occurred_at', [$since, $until])->count(),
                    'Sales'             => $this->websiteSalesTotal($since, $until),
                ],
                'money_keys' => ['Sales'],
            ],
            'tech_support' => [
                'label' => 'Technical Support', 'color' => '#ef4444', 'icon' => '🛠️',
                'metrics' => [
                    'Cases Assigned' => TechSupportCase::whereBetween('created_at', [$since, $until])->count(),
                    'Cases Resolved' => TechSupportCase::where('status', TechSupportCase::STATUS_RESOLVED)->whereBetween('resolved_at', [$since, $until])->count(),
                    'Issues Reported' => Lead::where('status', WebsiteLeadStatus::TechnicalSupport)->whereBetween('created_at', [$since, $until])->count()
                        + EbayCustomerRecord::where('tab_type', EbayCustomerRecord::TAB_TECHNICAL)->whereBetween('created_at', [$since, $until])->count(),
                ],
                'money_keys' => [],
            ],
        ];
    }

    /**
     * Resolve a Day/Week/Month tab into a concrete [since, until, label]
     * window for exactly ONE current period (Today / This Week / This
     * Month) — not a rolling N-period lookback — so switching tabs actually
     * changes every number on the page instead of all three windows happening
     * to contain the same (recent) activity.
     */
    private function periodBounds(string $granularity): array
    {
        return match ($granularity) {
            'day'   => [now()->startOfDay(), now()->endOfDay(), 'Today'],
            'week'  => [now()->startOfWeek(), now()->endOfWeek(), 'This Week'],
            'month' => [now()->startOfMonth(), now()->endOfMonth(), 'This Month'],
        };
    }

    /**
     * A custom date range (date_from/date_to) always wins over the
     * day/week/month tabs — same "explicit filter wins" convention already
     * used by the eBay Report and Call Reports pages. Returns granularity
     * as null when a custom range is active, so the view knows not to
     * highlight any of the day/week/month tab buttons.
     */
    private function resolvePeriod(Request $request, string $defaultGranularity = 'month'): array
    {
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $request->filled('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : Carbon::createFromDate(2000, 1, 1);
            $to = $request->filled('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : now()->endOfDay();
            $label = $from->format('d M Y') . ' – ' . $to->format('d M Y');

            return [$from, $to, $label, null];
        }

        $granularity = in_array($request->get('period'), ['day', 'week', 'month']) ? $request->get('period') : $defaultGranularity;
        [$since, $until, $label] = $this->periodBounds($granularity);

        return [$since, $until, $label, $granularity];
    }

    /** A user's activity across all 4 CRM domains for the selected period, shaped for the pie chart + CSV export. */
    private function buildChart(User $user, Carbon $since, Carbon $until, string $label): array
    {
        $countBetween = fn ($query, string $column) => $query->whereBetween($column, [$since, $until])->count();

        return [
            'labels' => [$label],
            'datasets' => [
                'website'      => [$countBetween(Lead::where('handled_by', $user->id), 'created_at')],
                'ebay'         => [$countBetween(EbayCustomerHandlerHistory::where('user_id', $user->id), 'started_at')],
                'tech_support' => [$countBetween(TechSupportCase::where('assigned_to', $user->id), 'created_at')],
                'logistic'     => [$countBetween(Shipment::where('assigned_to', $user->id), 'created_at')],
            ],
        ];
    }
}
