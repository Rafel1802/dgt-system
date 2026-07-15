<?php

namespace App\Services;

use App\Enums\WebsiteLeadStatus;
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
use Illuminate\Support\Carbon;

/**
 * All the data-building logic behind the CRM Staff/Team reports — shared
 * between the authenticated report pages (CrmStaffReportController) and the
 * public, no-login share links (Public\ReportShareController), so both
 * always compute the exact same numbers from the exact same queries.
 */
class CrmReportService
{
    /** Everyone eligible to appear on the staff report: any CRM-facing role, including Tech Support. */
    public function staffPool()
    {
        return User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin', 'admin-crm', 'sales-crm', 'boss', 'tech-support']))
            ->orderBy('name')
            ->get();
    }

    public function websiteSummary(User $user, Carbon $since, Carbon $until): array
    {
        return [
            'user'           => $user,
            'crm_handled'    => $this->distinctLeadsHandled($user->id, $since, $until),
            'crm_sales'      => Lead::where('handled_by', $user->id)->where('status', WebsiteLeadStatus::Successful)->whereBetween('updated_at', [$since, $until])->count(),
            'calls_answered' => CallReport::where('answered_by', $user->id)->whereBetween('occurred_at', [$since, $until])->count(),
        ];
    }

    // Negative feedback resolution isn't attributed to an individual staff
    // member here — it's a team effort, not one person's outcome — so
    // ebaySummary() only reports what this specific person handled.
    public function ebaySummary(User $user, Carbon $since, Carbon $until): array
    {
        return [
            'user'         => $user,
            'ebay_handled' => $this->distinctEbayHandled($user->id, $since, $until),
        ];
    }

    /**
     * "Handled" counts distinct customers, not raw rows — a staff member who
     * handled the same customer more than once in the period (repeat
     * inquiry, reassigned back to them, etc.) still only handled one
     * customer, not two. Falls back to the row's own id when there's no
     * linked customer_id yet, since two customer-less rows aren't provably
     * the same person.
     */
    public function distinctLeadsHandled(int $userId, Carbon $since, Carbon $until): int
    {
        return (int) Lead::where('handled_by', $userId)
            ->whereBetween('created_at', [$since, $until])
            ->selectRaw('COUNT(DISTINCT COALESCE(customer_id, id)) as cnt')
            ->value('cnt');
    }

    /** Same "distinct customer" rule as distinctLeadsHandled(), via the eBay record each handler-history row belongs to. */
    public function distinctEbayHandled(int $userId, Carbon $since, Carbon $until): int
    {
        return (int) EbayCustomerHandlerHistory::where('user_id', $userId)
            ->whereBetween('started_at', [$since, $until])
            ->join('ebay_customer_records', 'ebay_customer_records.id', '=', 'ebay_customer_handler_history.ebay_customer_record_id')
            ->selectRaw('COUNT(DISTINCT COALESCE(ebay_customer_records.customer_id, ebay_customer_records.id)) as cnt')
            ->value('cnt');
    }

    public function techSupportSummary(User $user, Carbon $since, Carbon $until): array
    {
        return [
            'user'      => $user,
            'assigned'  => TechSupportCase::where('assigned_to', $user->id)->whereBetween('created_at', [$since, $until])->count(),
            'resolved'  => TechSupportCase::where('assigned_to', $user->id)->where('status', TechSupportCase::STATUS_RESOLVED)->whereBetween('resolved_at', [$since, $until])->count(),
        ];
    }

    public function logisticSummary(User $user, Carbon $since, Carbon $until): array
    {
        return [
            'user'     => $user,
            'assigned' => Shipment::where('assigned_to', $user->id)->whereBetween('created_at', [$since, $until])->count(),
            'complete' => Shipment::where('assigned_to', $user->id)->where('status', Shipment::STATUS_COMPLETE)->whereBetween('updated_at', [$since, $until])->count(),
        ];
    }

    /** Which of the 4 CRM domains this user has activity in within the given window. */
    public function activeDomains(User $user, Carbon $since, Carbon $until): array
    {
        return array_keys(array_filter([
            'website'      => Lead::where('handled_by', $user->id)->whereBetween('created_at', [$since, $until])->exists(),
            'ebay'         => EbayCustomerHandlerHistory::where('user_id', $user->id)->whereBetween('started_at', [$since, $until])->exists(),
            'tech_support' => TechSupportCase::where('assigned_to', $user->id)->whereBetween('created_at', [$since, $until])->exists(),
            'logistic'     => Shipment::where('assigned_to', $user->id)->whereBetween('created_at', [$since, $until])->exists(),
        ]));
    }

    /**
     * Real eBay sales: sum of order-item prices logged through the "New
     * Order" flow (EbayCustomerOrder/EbayCustomerOrderItem) — the only eBay
     * money-tracking path that's actually populated by real usage (confirmed
     * against live data; EbayStore.total_sales is a manually-typed field
     * nobody keeps updated, and the Offer→Order pipeline has zero rows).
     */
    public function ebaySalesTotal(Carbon $since, Carbon $until): float
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
    public function websiteSalesTotal(Carbon $since, Carbon $until): float
    {
        return (float) LeadProduct::whereBetween('created_at', [$since, $until])
            ->get()
            ->sum(fn ($p) => $p->price * $p->quantity);
    }

    /** All 4 domain keys the Team Report page has one profile-style card/tab for, in display order. */
    public const DOMAIN_KEYS = ['logistic', 'ebay', 'website', 'tech_support'];

    /**
     * One domain's own team-wide totals for the given period — the building
     * block behind buildDomainReports() (all 4 domains, one shared period)
     * and the per-domain tabs on the Team Report page, which can each be
     * filtered to their own independent period. `money_keys` tells the view
     * which metrics to render with a $ prefix.
     */
    public function buildDomainReport(string $domainKey, Carbon $since, Carbon $until): array
    {
        return match ($domainKey) {
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
        };
    }

    /** All 4 domains for the same shared period — used by the General Report tab, PDF/CSV exports, and the share views. */
    public function buildDomainReports(Carbon $since, Carbon $until): array
    {
        return collect(self::DOMAIN_KEYS)
            ->mapWithKeys(fn ($key) => [$key => $this->buildDomainReport($key, $since, $until)])
            ->all();
    }

    /** Combined [domainReports, totalSales, trend] used by the Team Report page and its PDF/CSV/share views. */
    public function teamReportData(Carbon $since, Carbon $until): array
    {
        $domainReports = $this->buildDomainReports($since, $until);
        $totalSales = $domainReports['ebay']['metrics']['Sales'] + $domainReports['website']['metrics']['Sales'];
        $trend = $this->buildCompanyDailyTrend($since, $until);

        return [$domainReports, $totalSales, $trend];
    }

    /** Consecutive day buckets from $since to $until, capped at 62 (~2 months) so a wide custom range can't blow up a chart's dataset. */
    private function dayBuckets(Carbon $since, Carbon $until)
    {
        $days = collect();
        $cursor = $since->copy()->startOfDay();
        $end = $until->copy()->startOfDay();
        while ($cursor->lte($end) && $days->count() < 62) {
            $days->push($cursor->copy());
            $cursor->addDay();
        }

        return $days;
    }

    /** One domain's row counts grouped by the day they were created, for building a daily trend chart. */
    private function dailyCountsByDay(string $domainKey, Carbon $since, Carbon $until)
    {
        $byDay = fn ($query, string $column) => $query->get([$column])
            ->groupBy(fn ($row) => Carbon::parse($row->{$column})->toDateString())
            ->map->count();

        return match ($domainKey) {
            'website'      => $byDay(Lead::whereBetween('created_at', [$since, $until]), 'created_at'),
            'ebay'         => $byDay(EbayCustomerRecord::whereBetween('created_at', [$since, $until]), 'created_at'),
            'tech_support' => $byDay(TechSupportCase::whereBetween('created_at', [$since, $until]), 'created_at'),
            'logistic'     => $byDay(Shipment::whereBetween('created_at', [$since, $until]), 'created_at'),
        };
    }

    /**
     * Day-by-day activity for one domain over its own period — the trend
     * chart on that domain's own tab, which (unlike the General Report's
     * combined trend below) can be filtered to a period independent of the
     * other domains.
     */
    public function buildDomainDailyTrend(string $domainKey, Carbon $since, Carbon $until): array
    {
        $days = $this->dayBuckets($since, $until);
        $byDayMap = $this->dailyCountsByDay($domainKey, $since, $until);
        $multiDay = $days->count() > 15;

        return [
            'labels' => $days->map(fn ($d) => $d->format($multiDay ? 'd M' : 'D'))->values(),
            'data'   => $days->map(fn ($d) => $byDayMap[$d->toDateString()] ?? 0)->values(),
        ];
    }

    /**
     * Day-by-day total company-wide activity (all 4 domains, every staff
     * member combined) across the selected period, for the General
     * Report's trend chart — real daily counts, not a decorative curve.
     * Same "created" events buildDomainReports() already counts as each
     * domain's own "Total Customer"/"Cases Assigned"/"Shipments Assigned"
     * headline, just broken out per day instead of one period total.
     *
     * Also returns each domain's own day-by-day series under `series` (same
     * underlying per-day counts `data` sums together) — used by callers that
     * want every domain's trend for this SAME shared period in one query
     * pass; buildDomainDailyTrend() above is for a domain filtered to its
     * own, independent period instead.
     */
    public function buildCompanyDailyTrend(Carbon $since, Carbon $until): array
    {
        $days = $this->dayBuckets($since, $until);
        $byDayMaps = collect(self::DOMAIN_KEYS)->mapWithKeys(fn ($key) => [$key => $this->dailyCountsByDay($key, $since, $until)]);
        $multiDay = $days->count() > 15;
        $labels = $days->map(fn ($d) => $d->format($multiDay ? 'd M' : 'D'))->values();

        return [
            'labels' => $labels,
            'data'   => $days->map(fn ($d) => $byDayMaps->sum(fn ($map) => $map[$d->toDateString()] ?? 0))->values(),
            'series' => $byDayMaps->map(fn ($map) => $days->map(fn ($d) => $map[$d->toDateString()] ?? 0)->values())->all(),
        ];
    }

    /**
     * Resolve a Day/Week/Month tab into a concrete [since, until, label]
     * window for exactly ONE current period (Today / This Week / This
     * Month) — not a rolling N-period lookback — so switching tabs actually
     * changes every number on the page instead of all three windows happening
     * to contain the same (recent) activity.
     */
    public function periodBounds(string $granularity): array
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
     *
     * Takes a plain array (not a Request) so both the authenticated
     * controller (from $request->only([...])) and the public share
     * controller (from the share's saved `filters` column) can resolve the
     * exact same period from the exact same inputs.
     */
    public function resolvePeriodFromFilters(array $filters, string $defaultGranularity = 'month'): array
    {
        if (filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null)) {
            $from = filled($filters['date_from'] ?? null) ? Carbon::parse($filters['date_from'])->startOfDay() : Carbon::createFromDate(2000, 1, 1);
            $to = filled($filters['date_to'] ?? null) ? Carbon::parse($filters['date_to'])->endOfDay() : now()->endOfDay();
            $label = $from->format('d M Y') . ' – ' . $to->format('d M Y');

            return [$from, $to, $label, null];
        }

        $granularity = in_array($filters['period'] ?? null, ['day', 'week', 'month']) ? $filters['period'] : $defaultGranularity;
        [$since, $until, $label] = $this->periodBounds($granularity);

        return [$since, $until, $label, $granularity];
    }

    /**
     * A user's activity across all 4 CRM domains for the selected period,
     * shaped for the pie chart + CSV export. Website/eBay use the same
     * distinct-customer counting as the summary cards above the chart on
     * the same page, so the two never disagree on the same number.
     */
    public function buildChart(User $user, Carbon $since, Carbon $until, string $label): array
    {
        $countBetween = fn ($query, string $column) => $query->whereBetween($column, [$since, $until])->count();

        return [
            'labels' => [$label],
            'datasets' => [
                'website'      => [$this->distinctLeadsHandled($user->id, $since, $until)],
                'ebay'         => [$this->distinctEbayHandled($user->id, $since, $until)],
                'tech_support' => [$countBetween(TechSupportCase::where('assigned_to', $user->id), 'created_at')],
                'logistic'     => [$countBetween(Shipment::where('assigned_to', $user->id), 'created_at')],
            ],
        ];
    }

    /**
     * Day-by-day total activity (all 4 domains combined) across the selected
     * period, for the profile page's trend chart — real daily counts, not a
     * decorative curve. Capped at 62 buckets (~2 months) so a wide custom
     * date range can't blow up the dataset.
     */
    public function buildDailyTrend(User $user, Carbon $since, Carbon $until): array
    {
        $days = collect();
        $cursor = $since->copy()->startOfDay();
        $end = $until->copy()->startOfDay();
        while ($cursor->lte($end) && $days->count() < 62) {
            $days->push($cursor->copy());
            $cursor->addDay();
        }

        $byDay = function ($query, string $column) {
            return $query->get([$column])
                ->groupBy(fn ($row) => Carbon::parse($row->{$column})->toDateString())
                ->map->count();
        };

        $websiteByDay = $byDay(Lead::where('handled_by', $user->id)->whereBetween('created_at', [$since, $until]), 'created_at');
        $ebayByDay = $byDay(EbayCustomerHandlerHistory::where('user_id', $user->id)->whereBetween('started_at', [$since, $until]), 'started_at');
        $techByDay = $byDay(TechSupportCase::where('assigned_to', $user->id)->whereBetween('created_at', [$since, $until]), 'created_at');
        $logisticByDay = $byDay(Shipment::where('assigned_to', $user->id)->whereBetween('created_at', [$since, $until]), 'created_at');

        $multiDay = $days->count() > 15;

        return [
            'labels' => $days->map(fn ($d) => $d->format($multiDay ? 'd M' : 'D'))->values(),
            'data'   => $days->map(function ($d) use ($websiteByDay, $ebayByDay, $techByDay, $logisticByDay) {
                $key = $d->toDateString();

                return ($websiteByDay[$key] ?? 0) + ($ebayByDay[$key] ?? 0) + ($techByDay[$key] ?? 0) + ($logisticByDay[$key] ?? 0);
            })->values(),
        ];
    }

    /** Everything the individual staff profile page (and its PDF/share views) need for one user + period. */
    public function staffReportData(User $user, Carbon $since, Carbon $until, string $periodLabel): array
    {
        $chart = $this->buildChart($user, $since, $until, $periodLabel);
        $trend = $this->buildDailyTrend($user, $since, $until);

        $summary = [
            'website'      => $this->websiteSummary($user, $since, $until),
            'ebay'         => $this->ebaySummary($user, $since, $until),
            'tech_support' => $this->techSupportSummary($user, $since, $until),
            'logistic'     => $this->logisticSummary($user, $since, $until),
        ];

        // Lifetime activity, not period-scoped — a person's own profile page
        // still shows a domain's card (currently at zero) if they've ever
        // worked there, unlike the flat staff list which hides fully-quiet
        // people for the period entirely.
        $activeDomains = array_keys(array_filter([
            'website'      => Lead::where('handled_by', $user->id)->exists(),
            'ebay'         => EbayCustomerHandlerHistory::where('user_id', $user->id)->exists(),
            'tech_support' => TechSupportCase::where('assigned_to', $user->id)->exists(),
            'logistic'     => Shipment::where('assigned_to', $user->id)->exists(),
        ]));

        return compact('chart', 'trend', 'summary', 'activeDomains');
    }
}
