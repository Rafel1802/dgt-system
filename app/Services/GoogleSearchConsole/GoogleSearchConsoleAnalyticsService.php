<?php

namespace App\Services\GoogleSearchConsole;

use App\Models\GoogleSearchConsole\GoogleSearchConsoleDailyMetric;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleSearchQuery;
use App\Models\GoogleSearchConsole\GoogleSearchConsolePage;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleCountry;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleDevice;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleSite;
use App\Models\Website;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GoogleSearchConsoleAnalyticsService
{
    /**
     * ISO 3166-1 alpha-3 country code to display names
     */
    const COUNTRY_NAMES = [
        'USA' => 'United States',
        'CAN' => 'Canada',
        'MEX' => 'Mexico',
        'GBR' => 'United Kingdom',
        'AUS' => 'Australia',
        'DEU' => 'Germany',
        'FRA' => 'France',
        'KHM' => 'Cambodia',
        'VNM' => 'Vietnam',
        'THA' => 'Thailand',
        'SGP' => 'Singapore',
        'MYS' => 'Malaysia',
        'PHL' => 'Philippines',
        'IND' => 'India',
        'CHN' => 'China',
        'JPN' => 'Japan',
        'KOR' => 'South Korea',
        'NZL' => 'New Zealand',
        'BRA' => 'Brazil',
        'ZAF' => 'South Africa',
        'UNKNOWN' => 'Other / Unknown',
    ];

    /**
     * Resolve effective authorized website IDs based on user permissions, member filters, class filter, and website filters
     *
     * @param User $user Current logged in user
     * @param array|int|null $selectedWebsiteIds
     * @param array|int|null $selectedMemberIds
     * @param string|null $selectedClass
     * @return array List of unique website IDs
     */
    public function resolveWebsiteIds(User $user, $selectedWebsiteIds = null, $selectedMemberIds = null, ?string $selectedClass = null): array
    {
        // Only include websites that currently have an active Search Console property mapping or recorded data
        $mappedWebsiteIds = GoogleSearchConsoleSite::pluck('website_id')
            ->merge(DB::table('google_search_console_daily_metrics')->distinct()->pluck('website_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($mappedWebsiteIds)) {
            return [];
        }

        $query = Website::where('is_archived', false)->whereIn('id', $mappedWebsiteIds);

        // Authorization check
        if (!$user->canViewAllWebsiteReports()) {
            // Member can only view websites assigned to them
            $query->assignedToMember($user->id);
        }

        // If specific member filter is provided
        if (!empty($selectedMemberIds)) {
            $memberIds = is_array($selectedMemberIds) ? $selectedMemberIds : [$selectedMemberIds];
            $memberIds = array_filter(array_map('intval', $memberIds));
            if (!empty($memberIds)) {
                $query->assignedToMember($memberIds);
            }
        }

        // If specific class filter is provided (strictly match category, case and whitespace-insensitive)
        if (!empty($selectedClass) && strtolower(trim($selectedClass)) !== 'all') {
            $trimmedClass = trim($selectedClass);
            if (strtolower($trimmedClass) === 'general') {
                $query->where(function ($q) {
                    $q->whereNull('category')
                      ->orWhere('category', '')
                      ->orWhereRaw('LOWER(TRIM(category)) = ?', ['general']);
                });
            } else {
                $query->whereRaw('LOWER(TRIM(COALESCE(category, ""))) = ?', [strtolower($trimmedClass)]);
            }
        }

        // If specific website filter is provided
        if (!empty($selectedWebsiteIds)) {
            $websiteIds = is_array($selectedWebsiteIds) ? $selectedWebsiteIds : [$selectedWebsiteIds];
            $websiteIds = array_filter(array_map('intval', $websiteIds));
            if (!empty($websiteIds)) {
                $query->whereIn('id', $websiteIds);
            }
        }

        // Ensure distinct website IDs (prevents double-counting if assigned to multiple selected members)
        return $query->pluck('id')->unique()->values()->all();
    }

    /**
     * Resolve date ranges from preset or custom inputs with period-over-period comparison dates
     */
    public function resolveDateRange(?string $preset = '28d', ?string $customStart = null, ?string $customEnd = null, ?string $latestAvailableDate = null): array
    {
        // Reference end date: latest available date in DB or 2 days ago
        $refDate = $latestAvailableDate ? Carbon::parse($latestAvailableDate) : now()->subDays(2);

        // If explicit custom start and end dates are provided, strictly honor them only for custom or unspecified presets!
        $startDate = null;
        $endDate = null;
        if (($preset === 'custom' || empty($preset)) && !empty($customStart) && !empty($customEnd)) {
            try {
                $startDate = Carbon::parse($customStart);
                $endDate = Carbon::parse($customEnd);
                if ($startDate->gt($endDate)) {
                    [$startDate, $endDate] = [$endDate, $startDate];
                }
            } catch (\Throwable $e) {
                $startDate = null;
                $endDate = null;
            }
        }

        $label = 'Last 28 Days';

        if (empty($startDate) || empty($endDate)) {
            switch ($preset) {
                case 'this_week':
                    $startDate = $refDate->copy()->startOfWeek(Carbon::MONDAY);
                    $endDate = $refDate->copy();
                    $dayCount = $startDate->diffInDays($endDate) + 1;
                    $prevStartDate = $startDate->copy()->subWeek();
                    $prevEndDate = $prevStartDate->copy()->addDays($dayCount - 1);
                    $label = 'This Week (vs Last Week)';
                    break;

                case 'last_week':
                    $startDate = $refDate->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
                    $endDate = $refDate->copy()->subWeek()->endOfWeek(Carbon::SUNDAY);
                    $dayCount = 7;
                    $prevStartDate = $startDate->copy()->subWeek();
                    $prevEndDate = $endDate->copy()->subWeek();
                    $label = 'Last Week (vs Prior Week)';
                    break;

                case '7d':
                    $endDate = $refDate->copy();
                    $startDate = $refDate->copy()->subDays(6);
                    $dayCount = 7;
                    $prevEndDate = $startDate->copy()->subDay();
                    $prevStartDate = $prevEndDate->copy()->subDays(6);
                    $label = 'Last 7 Days (vs Prior 7 Days)';
                    break;

                case 'this_month':
                    $startDate = $refDate->copy()->startOfMonth();
                    $endDate = $refDate->copy();
                    $dayCount = $startDate->diffInDays($endDate) + 1;
                    $prevStartDate = $startDate->copy()->subMonthNoOverflow();
                    $prevEndDate = $prevStartDate->copy()->addDays($dayCount - 1);
                    if ($prevEndDate->gt($prevStartDate->copy()->endOfMonth())) {
                        $prevEndDate = $prevStartDate->copy()->endOfMonth();
                    }
                    $label = 'This Month (vs Prior Month)';
                    break;

                case 'prev_month':
                    $startDate = $refDate->copy()->subMonthNoOverflow()->startOfMonth();
                    $endDate = $refDate->copy()->subMonthNoOverflow()->endOfMonth();
                    $dayCount = $startDate->diffInDays($endDate) + 1;
                    $prevStartDate = $startDate->copy()->subMonthNoOverflow()->startOfMonth();
                    $prevEndDate = $startDate->copy()->subMonthNoOverflow()->endOfMonth();
                    $label = 'Previous Month (vs 2 Months Ago)';
                    break;

                case 'quarter':
                case '90d':
                    $endDate = $refDate->copy();
                    $startDate = $refDate->copy()->subDays(89);
                    $dayCount = 90;
                    $prevEndDate = $startDate->copy()->subDay();
                    $prevStartDate = $prevEndDate->copy()->subDays(89);
                    $label = 'Last 90 Days / Quarter (vs Prior 90 Days)';
                    break;

                case '28d':
                default:
                    $endDate = $refDate->copy();
                    $startDate = $refDate->copy()->subDays(27);
                    $dayCount = 28;
                    $prevEndDate = $startDate->copy()->subDay();
                    $prevStartDate = $prevEndDate->copy()->subDays(27);
                    $label = 'Last 28 Days (vs Prior 28 Days)';
                    $preset = '28d';
                    break;
            }
        } else {
            // Explicit custom range provided
            $dayCount = $startDate->diffInDays($endDate) + 1;
            $prevEndDate = $startDate->copy()->subDay();
            $prevStartDate = $prevEndDate->copy()->subDays($dayCount - 1);
            $label = "Custom Range ({$dayCount} Days vs Preceding Period)";
            $preset = 'custom';
        }

        return [
            'start'      => $startDate->format('Y-m-d'),
            'end'        => $endDate->format('Y-m-d'),
            'prev_start' => $prevStartDate->format('Y-m-d'),
            'prev_end'   => $prevEndDate->format('Y-m-d'),
            'days'       => $dayCount,
            'preset'     => $preset,
            'label'      => $label,
        ];
    }

    /**
     * Compute KPI Summary Metrics (Clicks, Impressions, CTR, Avg Position) with period comparison
     */
    public function getKpiSummary(array $websiteIds, string $startDate, string $endDate, string $prevStartDate, string $prevEndDate): array
    {
        if (empty($websiteIds)) {
            return $this->emptyKpiMetrics();
        }

        // Current period metrics
        $curr = DB::table('google_search_console_daily_metrics')
            ->whereIn('website_id', $websiteIds)
            ->whereDate('data_date', '>=', $startDate)
            ->whereDate('data_date', '<=', $endDate)
            ->selectRaw('
                COALESCE(SUM(clicks), 0) as total_clicks,
                COALESCE(SUM(impressions), 0) as total_impressions,
                COALESCE(SUM(position * impressions), 0) as sum_weighted_pos
            ')
            ->first();

        // Previous period metrics
        $prev = DB::table('google_search_console_daily_metrics')
            ->whereIn('website_id', $websiteIds)
            ->whereDate('data_date', '>=', $prevStartDate)
            ->whereDate('data_date', '<=', $prevEndDate)
            ->selectRaw('
                COALESCE(SUM(clicks), 0) as total_clicks,
                COALESCE(SUM(impressions), 0) as total_impressions,
                COALESCE(SUM(position * impressions), 0) as sum_weighted_pos
            ')
            ->first();

        $currClicks = (int) ($curr->total_clicks ?? 0);
        $currImp = (int) ($curr->total_impressions ?? 0);
        $currCtr = $currImp > 0 ? round(($currClicks / $currImp) * 100, 2) : 0.0;
        $currPos = $currImp > 0 ? round($curr->sum_weighted_pos / $currImp, 1) : 0.0;

        $prevClicks = (int) ($prev->total_clicks ?? 0);
        $prevImp = (int) ($prev->total_impressions ?? 0);
        $prevCtr = $prevImp > 0 ? round(($prevClicks / $prevImp) * 100, 2) : 0.0;
        $prevPos = $prevImp > 0 ? round($prev->sum_weighted_pos / $prevImp, 1) : 0.0;

        return [
            'clicks' => [
                'current'     => $currClicks,
                'previous'    => $prevClicks,
                'diff'        => $currClicks - $prevClicks,
                'percent'     => $this->calcPercentChange($currClicks, $prevClicks),
                'trend'       => ($currClicks >= $prevClicks) ? 'up' : 'down',
            ],
            'impressions' => [
                'current'     => $currImp,
                'previous'    => $prevImp,
                'diff'        => $currImp - $prevImp,
                'percent'     => $this->calcPercentChange($currImp, $prevImp),
                'trend'       => ($currImp >= $prevImp) ? 'up' : 'down',
            ],
            'ctr' => [
                'current'     => $currCtr,
                'previous'    => $prevCtr,
                'diff'        => round($currCtr - $prevCtr, 2),
                'percent'     => $this->calcPercentChange($currCtr, $prevCtr),
                'trend'       => ($currCtr >= $prevCtr) ? 'up' : 'down',
            ],
            'position' => [
                'current'     => $currPos,
                'previous'    => $prevPos,
                'diff'        => round($currPos - $prevPos, 1),
                'percent'     => $this->calcPercentChange($currPos, $prevPos),
                // For Google position, a LOWER number means ranked higher (better)!
                'trend'       => ($currPos <= $prevPos && $currPos > 0) ? 'up' : 'down',
            ],
        ];
    }

    /**
     * Compute Daily Performance Trend series for Chart.js
     */
    public function getDailyTrend(array $websiteIds, string $startDate, string $endDate, ?string $prevStartDate = null, ?string $prevEndDate = null): array
    {
        if (empty($websiteIds)) {
            return [
                'labels'           => [],
                'clicks'           => [],
                'impressions'      => [],
                'ctr'              => [],
                'position'         => [],
                'prev_clicks'      => [],
                'prev_impressions' => [],
            ];
        }

        // Current period daily grouped
        $daily = DB::table('google_search_console_daily_metrics')
            ->whereIn('website_id', $websiteIds)
            ->whereDate('data_date', '>=', $startDate)
            ->whereDate('data_date', '<=', $endDate)
            ->groupBy('data_date')
            ->orderBy('data_date')
            ->selectRaw('
                data_date,
                COALESCE(SUM(clicks), 0) as clicks,
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(position * impressions), 0) as sum_weighted_pos
            ')
            ->get()
            ->keyBy('data_date');

        // Build continuous date range
        $periodDates = [];
        $clicks = [];
        $impressions = [];
        $ctr = [];
        $position = [];

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr = $d->format('Y-m-d');
            $periodDates[] = $d->format('M d');

            if (isset($daily[$dateStr])) {
                $row = $daily[$dateStr];
                $c = (int) $row->clicks;
                $i = (int) $row->impressions;
                $clicks[] = $c;
                $impressions[] = $i;
                $ctr[] = $i > 0 ? round(($c / $i) * 100, 2) : 0;
                $position[] = $i > 0 ? round($row->sum_weighted_pos / $i, 1) : null;
            } else {
                $clicks[] = 0;
                $impressions[] = 0;
                $ctr[] = 0;
                $position[] = null;
            }
        }

        // Optional previous period overlay
        $prevClicks = [];
        $prevImpressions = [];
        if ($prevStartDate && $prevEndDate) {
            $prevDaily = DB::table('google_search_console_daily_metrics')
                ->whereIn('website_id', $websiteIds)
                ->whereDate('data_date', '>=', $prevStartDate)
                ->whereDate('data_date', '<=', $prevEndDate)
                ->groupBy('data_date')
                ->orderBy('data_date')
                ->selectRaw('
                    data_date,
                    COALESCE(SUM(clicks), 0) as clicks,
                    COALESCE(SUM(impressions), 0) as impressions
                ')
                ->get()
                ->keyBy('data_date');

            $pStart = Carbon::parse($prevStartDate);
            $pEnd = Carbon::parse($prevEndDate);
            for ($pd = $pStart->copy(); $pd->lte($pEnd); $pd->addDay()) {
                $pDateStr = $pd->format('Y-m-d');
                $prevClicks[] = isset($prevDaily[$pDateStr]) ? (int) $prevDaily[$pDateStr]->clicks : 0;
                $prevImpressions[] = isset($prevDaily[$pDateStr]) ? (int) $prevDaily[$pDateStr]->impressions : 0;
            }
        }

        return [
            'labels'           => $periodDates,
            'clicks'           => $clicks,
            'impressions'      => $impressions,
            'ctr'              => $ctr,
            'position'         => $position,
            'prev_clicks'      => $prevClicks,
            'prev_impressions' => $prevImpressions,
        ];
    }

    /**
     * Search Keywords / Queries table with search, sorting, and pagination
     */
    public function getSearchKeywords(
        array $websiteIds,
        string $startDate,
        string $endDate,
        ?string $searchTerm = null,
        string $sortBy = 'clicks',
        string $sortDir = 'desc',
        int $page = 1,
        int $perPage = 25
    ): array {
        if (empty($websiteIds)) {
            return ['data' => [], 'total' => 0, 'pages' => 0, 'current_page' => 1];
        }

        $allowedSorts = ['clicks', 'impressions', 'ctr', 'position', 'query'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'clicks';
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('google_search_console_search_queries')
            ->whereIn('website_id', $websiteIds)
            ->whereDate('data_date', '>=', $startDate)
            ->whereDate('data_date', '<=', $endDate);

        if (!empty($searchTerm)) {
            $query->where('query', 'LIKE', '%' . $searchTerm . '%');
        }

        $query->groupBy('query')
            ->selectRaw('
                query,
                COALESCE(SUM(clicks), 0) as clicks,
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 0) as ctr,
                COALESCE(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 0) as position
            ');

        // Total count of distinct queries
        $total = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->count();

        $rows = $query->orderBy($sortBy, $sortDir)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $data = $rows->map(function ($r) {
            return [
                'query'       => $r->query,
                'clicks'      => (int) $r->clicks,
                'impressions' => (int) $r->impressions,
                'ctr'         => round((float) $r->ctr, 2),
                'position'    => round((float) $r->position, 1),
            ];
        })->all();

        return [
            'data'         => $data,
            'total'        => $total,
            'pages'        => (int) ceil($total / $perPage),
            'current_page' => $page,
            'per_page'     => $perPage,
        ];
    }

    /**
     * Inspect single keyword daily performance & related pages
     */
    public function getKeywordDetails(array $websiteIds, string $keyword, string $startDate, string $endDate): array
    {
        if (empty($websiteIds)) {
            return ['keyword' => $keyword, 'daily' => [], 'summary' => []];
        }

        $daily = DB::table('google_search_console_search_queries')
            ->whereIn('website_id', $websiteIds)
            ->where('query', $keyword)
            ->whereDate('data_date', '>=', $startDate)
            ->whereDate('data_date', '<=', $endDate)
            ->groupBy('data_date')
            ->orderBy('data_date')
            ->selectRaw('
                data_date,
                COALESCE(SUM(clicks), 0) as clicks,
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 0) as position
            ')
            ->get();

        $totalClicks = $daily->sum('clicks');
        $totalImp = $daily->sum('impressions');
        $sumWeightedPos = $daily->sum(fn($r) => $r->position * $r->impressions);

        return [
            'keyword' => $keyword,
            'summary' => [
                'clicks'      => $totalClicks,
                'impressions' => $totalImp,
                'ctr'         => $totalImp > 0 ? round(($totalClicks / $totalImp) * 100, 2) : 0,
                'position'    => $totalImp > 0 ? round($sumWeightedPos / $totalImp, 1) : 0,
            ],
            'daily' => $daily->map(fn($r) => [
                'date'        => $r->data_date,
                'clicks'      => (int) $r->clicks,
                'impressions' => (int) $r->impressions,
                'ctr'         => $r->impressions > 0 ? round(($r->clicks / $r->impressions) * 100, 2) : 0,
                'position'    => round((float) $r->position, 1),
            ])->all(),
        ];
    }

    /**
     * Top Landing Pages report
     */
    public function getTopPages(
        array $websiteIds,
        string $startDate,
        string $endDate,
        ?string $searchTerm = null,
        string $sortBy = 'clicks',
        string $sortDir = 'desc',
        int $page = 1,
        int $perPage = 25
    ): array {
        if (empty($websiteIds)) {
            return ['data' => [], 'total' => 0, 'pages' => 0, 'current_page' => 1];
        }

        $allowedSorts = ['clicks', 'impressions', 'ctr', 'position', 'page'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'clicks';
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('google_search_console_pages')
            ->whereIn('website_id', $websiteIds)
            ->whereDate('data_date', '>=', $startDate)
            ->whereDate('data_date', '<=', $endDate);

        if (!empty($searchTerm)) {
            $query->where('page', 'LIKE', '%' . $searchTerm . '%');
        }

        $query->groupBy('page')
            ->selectRaw('
                page,
                COALESCE(SUM(clicks), 0) as clicks,
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 0) as ctr,
                COALESCE(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 0) as position
            ');

        $total = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->count();

        $rows = $query->orderBy($sortBy, $sortDir)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $data = $rows->map(function ($r) {
            return [
                'page'        => $r->page,
                'clicks'      => (int) $r->clicks,
                'impressions' => (int) $r->impressions,
                'ctr'         => round((float) $r->ctr, 2),
                'position'    => round((float) $r->position, 1),
            ];
        })->all();

        return [
            'data'         => $data,
            'total'        => $total,
            'pages'        => (int) ceil($total / $perPage),
            'current_page' => $page,
            'per_page'     => $perPage,
        ];
    }

    /**
     * Countries breakdown
     */
    public function getCountries(array $websiteIds, string $startDate, string $endDate): array
    {
        if (empty($websiteIds)) {
            return [];
        }

        $rows = DB::table('google_search_console_countries')
            ->whereIn('website_id', $websiteIds)
            ->whereDate('data_date', '>=', $startDate)
            ->whereDate('data_date', '<=', $endDate)
            ->groupBy('country')
            ->orderByDesc('clicks')
            ->selectRaw('
                country,
                COALESCE(SUM(clicks), 0) as clicks,
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 0) as ctr,
                COALESCE(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 0) as position
            ')
            ->limit(50)
            ->get();

        return $rows->map(function ($r) {
            $code = strtoupper(trim($r->country));
            return [
                'country_code' => $code,
                'country_name' => self::COUNTRY_NAMES[$code] ?? $code,
                'clicks'       => (int) $r->clicks,
                'impressions'  => (int) $r->impressions,
                'ctr'          => round((float) $r->ctr, 2),
                'position'     => round((float) $r->position, 1),
            ];
        })->all();
    }

    /**
     * Devices breakdown (Desktop, Mobile, Tablet)
     */
    public function getDevices(array $websiteIds, string $startDate, string $endDate): array
    {
        if (empty($websiteIds)) {
            return [];
        }

        $rows = DB::table('google_search_console_devices')
            ->whereIn('website_id', $websiteIds)
            ->whereDate('data_date', '>=', $startDate)
            ->whereDate('data_date', '<=', $endDate)
            ->groupBy('device')
            ->orderByDesc('clicks')
            ->selectRaw('
                device,
                COALESCE(SUM(clicks), 0) as clicks,
                COALESCE(SUM(impressions), 0) as impressions,
                COALESCE(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 0) as ctr,
                COALESCE(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 0) as position
            ')
            ->get();

        $totalClicks = $rows->sum('clicks');
        $totalImp = $rows->sum('impressions');

        return $rows->map(function ($r) use ($totalClicks, $totalImp) {
            $device = strtoupper(trim($r->device));
            $clicks = (int) $r->clicks;
            $imp = (int) $r->impressions;
            return [
                'device'        => ucfirst(strtolower($device)),
                'clicks'        => $clicks,
                'impressions'   => $imp,
                'ctr'           => round((float) $r->ctr, 2),
                'position'      => round((float) $r->position, 1),
                'click_share'   => $totalClicks > 0 ? round(($clicks / $totalClicks) * 100, 1) : 0,
                'imp_share'     => $totalImp > 0 ? round(($imp / $totalImp) * 100, 1) : 0,
            ];
        })->all();
    }

    /**
     * Per-Website comparison breakdown for multi-website portfolio view with period-over-period growth
     */
    public function getWebsiteBreakdown(array $websiteIds, string $startDate, string $endDate, ?string $prevStartDate = null, ?string $prevEndDate = null): array
    {
        if (empty($websiteIds)) {
            return [];
        }

        $metrics = DB::table('websites')
            ->leftJoin('google_search_console_daily_metrics', function ($join) use ($startDate, $endDate) {
                $join->on('google_search_console_daily_metrics.website_id', '=', 'websites.id')
                    ->whereDate('google_search_console_daily_metrics.data_date', '>=', $startDate)
                    ->whereDate('google_search_console_daily_metrics.data_date', '<=', $endDate);
            })
            ->whereIn('websites.id', $websiteIds)
            ->groupBy('websites.id', 'websites.name', 'websites.url', 'websites.handled_by', 'websites.category')
            ->orderByDesc('clicks')
            ->selectRaw('
                websites.id as website_id,
                websites.name,
                websites.url,
                websites.category,
                websites.handled_by,
                COALESCE(SUM(google_search_console_daily_metrics.clicks), 0) as clicks,
                COALESCE(SUM(google_search_console_daily_metrics.impressions), 0) as impressions,
                COALESCE(SUM(google_search_console_daily_metrics.clicks) / NULLIF(SUM(google_search_console_daily_metrics.impressions), 0) * 100, 0) as ctr,
                COALESCE(SUM(google_search_console_daily_metrics.position * google_search_console_daily_metrics.impressions) / NULLIF(SUM(google_search_console_daily_metrics.impressions), 0), 0) as position
            ')
            ->get();

        $prevMetrics = collect();
        if (!empty($prevStartDate) && !empty($prevEndDate)) {
            $prevMetrics = DB::table('google_search_console_daily_metrics')
                ->whereIn('website_id', $websiteIds)
                ->whereDate('data_date', '>=', $prevStartDate)
                ->whereDate('data_date', '<=', $prevEndDate)
                ->groupBy('website_id')
                ->selectRaw('
                    website_id,
                    COALESCE(SUM(clicks), 0) as prev_clicks,
                    COALESCE(SUM(impressions), 0) as prev_impressions,
                    COALESCE(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 0) as prev_ctr,
                    COALESCE(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 0) as prev_position
                ')
                ->get()
                ->keyBy('website_id');
        }

        $totalClicks = (int) $metrics->sum('clicks');
        $totalImpressions = (int) $metrics->sum('impressions');

        // Pre-fetch handlers in a single batch query for speed and zero N+1 queries
        $handlerIds = $metrics->pluck('handled_by')->filter()->unique()->all();
        $handlers = !empty($handlerIds) ? User::whereIn('id', $handlerIds)->get()->keyBy('id') : collect();

        return $metrics->map(function ($m) use ($totalClicks, $totalImpressions, $prevMetrics, $handlers) {
            $handler = $m->handled_by ? $handlers->get($m->handled_by) : null;
            $clicks = (int) $m->clicks;
            $impressions = (int) $m->impressions;
            $cleanDomain = str_replace(['http://', 'https://'], '', rtrim($m->url, '/'));

            $prev = $prevMetrics->get($m->website_id);
            $prevClicks = $prev ? (int) $prev->prev_clicks : 0;
            $prevImpressions = $prev ? (int) $prev->prev_impressions : 0;
            $prevCtr = $prev ? round((float) $prev->prev_ctr, 2) : 0.0;
            $prevPos = $prev ? round((float) $prev->prev_position, 1) : 0.0;

            $clicksDiff = $clicks - $prevClicks;
            $clicksGrowthPct = $this->calcPercentChange($clicks, $prevClicks);
            $impDiff = $impressions - $prevImpressions;
            $impGrowthPct = $this->calcPercentChange($impressions, $prevImpressions);

            $currPos = round((float) $m->position, 1);
            $posDiff = round($currPos - $prevPos, 1);

            return [
                'website_id'             => $m->website_id,
                'name'                   => $m->name,
                'url'                    => $m->url,
                'clean_domain'           => $cleanDomain,
                'class'                  => $m->category ?: 'General',
                'handler_name'           => $handler ? $handler->name : 'Unassigned',
                'handler_avatar'         => $handler ? $handler->avatar_url : null,
                'clicks'                 => $clicks,
                'prev_clicks'            => $prevClicks,
                'clicks_diff'            => $clicksDiff,
                'clicks_growth_pct'      => $clicksGrowthPct,
                'clicks_trend'           => ($clicks >= $prevClicks) ? 'up' : 'down',
                'impressions'            => $impressions,
                'prev_impressions'       => $prevImpressions,
                'impressions_diff'       => $impDiff,
                'impressions_growth_pct' => $impGrowthPct,
                'impressions_trend'      => ($impressions >= $prevImpressions) ? 'up' : 'down',
                'clicks_share'           => $totalClicks > 0 ? round(($clicks / $totalClicks) * 100, 1) : 0,
                'impressions_share'      => $totalImpressions > 0 ? round(($impressions / $totalImpressions) * 100, 1) : 0,
                'ctr'                    => round((float) $m->ctr, 2),
                'prev_ctr'               => $prevCtr,
                'ctr_diff'               => round(round((float) $m->ctr, 2) - $prevCtr, 2),
                'position'               => $currPos,
                'prev_position'          => $prevPos,
                'position_diff'          => $posDiff,
                'position_trend'         => ($currPos <= $prevPos && $currPos > 0) ? 'up' : 'down',
            ];
        })->all();
    }

    /**
     * Compute multi-website or head-to-head comparison
     */
    public function getWebsitesComparison(array $websiteIds, string $startDate, string $endDate): array
    {
        if (empty($websiteIds)) {
            return [
                'websites'    => [],
                'chart_dates' => [],
                'datasets'    => [],
            ];
        }

        $websites = Website::whereIn('id', $websiteIds)->get(['id', 'name', 'url', 'category', 'handled_by']);
        $comparison = [];

        // Build continuous date range for chart
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $periodDates = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $periodDates[] = $d->format('Y-m-d');
        }

        $chartLabels = array_map(fn($d) => Carbon::parse($d)->format('M d'), $periodDates);
        $palette = ['#2563eb', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4', '#f97316'];

        $chartDatasetsClicks = [];
        $chartDatasetsImpressions = [];

        foreach ($websites as $idx => $w) {
            $metrics = DB::table('google_search_console_daily_metrics')
                ->where('website_id', $w->id)
                ->whereDate('data_date', '>=', $startDate)
                ->whereDate('data_date', '<=', $endDate)
                ->selectRaw('
                    COALESCE(SUM(clicks), 0) as clicks,
                    COALESCE(SUM(impressions), 0) as impressions,
                    COALESCE(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 0) as ctr,
                    COALESCE(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 0) as position
                ')
                ->first();

            $daily = DB::table('google_search_console_daily_metrics')
                ->where('website_id', $w->id)
                ->whereDate('data_date', '>=', $startDate)
                ->whereDate('data_date', '<=', $endDate)
                ->groupBy('data_date')
                ->pluck(DB::raw('COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(impressions), 0) as impressions'), 'data_date');

            $dailyRecords = DB::table('google_search_console_daily_metrics')
                ->where('website_id', $w->id)
                ->whereDate('data_date', '>=', $startDate)
                ->whereDate('data_date', '<=', $endDate)
                ->groupBy('data_date')
                ->selectRaw('data_date, COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(impressions), 0) as impressions')
                ->get()
                ->keyBy('data_date');

            $dailyClicks = [];
            $dailyImpressions = [];
            foreach ($periodDates as $pDate) {
                $rec = $dailyRecords->get($pDate);
                $dailyClicks[] = $rec ? (int) $rec->clicks : 0;
                $dailyImpressions[] = $rec ? (int) $rec->impressions : 0;
            }

            $color = $palette[$idx % count($palette)];

            $chartDatasetsClicks[] = [
                'label'           => $w->name . ' (' . $w->clean_domain . ')',
                'data'            => $dailyClicks,
                'borderColor'     => $color,
                'backgroundColor' => $color . '20',
                'borderWidth'     => 2,
                'fill'            => false,
                'tension'         => 0.35,
            ];

            $chartDatasetsImpressions[] = [
                'label'           => $w->name . ' (' . $w->clean_domain . ')',
                'data'            => $dailyImpressions,
                'borderColor'     => $color,
                'backgroundColor' => $color . '20',
                'borderWidth'     => 2,
                'fill'            => false,
                'tension'         => 0.35,
            ];

            $topQueries = DB::table('google_search_console_search_queries')
                ->where('website_id', $w->id)
                ->whereDate('data_date', '>=', $startDate)
                ->whereDate('data_date', '<=', $endDate)
                ->groupBy('query')
                ->orderByDesc('clicks')
                ->limit(8)
                ->selectRaw('query, COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(impressions), 0) as impressions, COALESCE(SUM(clicks) / NULLIF(SUM(impressions), 0) * 100, 0) as ctr, COALESCE(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 0) as position')
                ->get()
                ->map(fn($r) => [
                    'query'       => $r->query,
                    'clicks'      => (int) $r->clicks,
                    'impressions' => (int) $r->impressions,
                    'ctr'         => round((float) $r->ctr, 2),
                    'position'    => round((float) $r->position, 1),
                ])
                ->all();

            $handler = $w->handler;

            $comparison[] = [
                'website_id'        => $w->id,
                'name'              => $w->name,
                'domain'            => $w->clean_domain,
                'class'             => $w->category ?: 'General',
                'handler_name'      => $handler ? $handler->name : 'Unassigned',
                'color'             => $color,
                'clicks'            => (int) ($metrics->clicks ?? 0),
                'impressions'       => (int) ($metrics->impressions ?? 0),
                'ctr'               => round((float) ($metrics->ctr ?? 0), 2),
                'position'          => round((float) ($metrics->position ?? 0), 1),
                'daily_clicks'      => $dailyClicks,
                'daily_impressions' => $dailyImpressions,
                'top_queries'       => $topQueries,
            ];
        }

        return [
            'websites'              => $comparison,
            'chart_dates'           => $chartLabels,
            'datasets_clicks'       => $chartDatasetsClicks,
            'datasets_impressions'  => $chartDatasetsImpressions,
        ];
    }

    /**
     * Compute safe percentage change
     */
    public function calcPercentChange($curr, $prev): ?float
    {
        if ($prev == 0) {
            return $curr > 0 ? 100.0 : 0.0;
        }
        return round((($curr - $prev) / abs($prev)) * 100, 1);
    }

    /**
     * Default empty KPI array
     */
    protected function emptyKpiMetrics(): array
    {
        return [
            'clicks'      => ['current' => 0, 'previous' => 0, 'diff' => 0, 'percent' => 0, 'trend' => 'up'],
            'impressions' => ['current' => 0, 'previous' => 0, 'diff' => 0, 'percent' => 0, 'trend' => 'up'],
            'ctr'         => ['current' => 0.0, 'previous' => 0.0, 'diff' => 0, 'percent' => 0, 'trend' => 'up'],
            'position'    => ['current' => 0.0, 'previous' => 0.0, 'diff' => 0, 'percent' => 0, 'trend' => 'up'],
        ];
    }
}
