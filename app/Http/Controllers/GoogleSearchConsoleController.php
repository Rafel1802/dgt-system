<?php

namespace App\Http\Controllers;

use App\Models\GoogleSearchConsole\GoogleSearchConsoleAccount;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleSite;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleSyncLog;
use App\Models\Website;
use App\Models\User;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleAnalyticsService;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleClientService;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleSyncService;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleSitemapService;
use App\Services\GoogleSearchConsole\XlsxExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;

class GoogleSearchConsoleController extends Controller
{
    protected GoogleSearchConsoleClientService $clientService;
    protected GoogleSearchConsoleSyncService $syncService;
    protected GoogleSearchConsoleAnalyticsService $analyticsService;
    protected XlsxExportService $xlsxService;
    protected GoogleSearchConsoleSitemapService $sitemapService;

    public function __construct(
        GoogleSearchConsoleClientService $clientService,
        GoogleSearchConsoleSyncService $syncService,
        GoogleSearchConsoleAnalyticsService $analyticsService,
        XlsxExportService $xlsxService,
        GoogleSearchConsoleSitemapService $sitemapService
    ) {
        $this->clientService    = $clientService;
        $this->syncService      = $syncService;
        $this->analyticsService = $analyticsService;
        $this->xlsxService      = $xlsxService;
        $this->sitemapService   = $sitemapService;
    }

    /**
     * Main SEO Dashboard View
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403, 'Unauthorized access to website management.');

        $isConfigured = $this->clientService->isConfigured();
        $account = GoogleSearchConsoleAccount::where('is_active', true)->latest()->first();

        // All active DGT websites authorized for the user, with handler, assignees, and class
        $authorizedWebsites = Website::where('is_archived', false)
            ->when(!$user->canViewAllWebsiteReports(), fn($q) => $q->assignedToMember($user->id))
            ->with([
                'handler:id,name,email,avatar',
                'assignees:id,name,email,avatar',
                'searchConsoleSite:id,website_id,site_url,is_sync_enabled',
            ])
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'url', 'category', 'status', 'handled_by']);

        // Structured website list for frontend multi-select filter
        $websitesList = $authorizedWebsites->map(function ($w) {
            $handlerName = $w->handler?->name;
            $assigneeNames = $w->assignees->pluck('name')->all();

            $members = [];
            if ($handlerName) {
                $members[] = $handlerName . ' (Handler)';
            }
            foreach ($assigneeNames as $an) {
                if ($an !== $handlerName) {
                    $members[] = $an;
                }
            }
            $handleAssignedText = !empty($members) ? implode(', ', $members) : 'Unassigned';

            return [
                'id'                 => $w->id,
                'name'               => $w->name,
                'domain'             => $w->clean_domain,
                'class'              => $w->category ?: 'General',
                'status'             => $w->status,
                'handler'            => $w->handler ? [
                    'id'         => $w->handler->id,
                    'name'       => $w->handler->name,
                    'avatar_url' => $w->handler->avatar_url,
                ] : null,
                'assignees'          => $w->assignees->map(fn($a) => [
                    'id'         => $a->id,
                    'name'       => $a->name,
                    'avatar_url' => $a->avatar_url,
                ])->all(),
                'handler_name'       => $handlerName,
                'assignee_names'     => $assigneeNames,
                'handle_assigned'    => $handleAssignedText,
                'is_mapped'          => $w->searchConsoleSite !== null,
                'site_url'           => $w->searchConsoleSite?->site_url,
            ];
        })->values()->all();

        // Extract available classes for filter tabs
        $availableClasses = collect($websitesList)->pluck('class')->unique()->filter()->values()->all();

        // All team members associated with websites
        $teamMembers = User::whereIn('id', function ($query) {
            $query->select('handled_by')->from('websites')->whereNotNull('handled_by')
                ->union(
                    \Illuminate\Support\Facades\DB::table('website_assignees')->select('user_id')
                );
        })->orderBy('name')->get(['id', 'name', 'email']);

        // All existing property mappings
        $mappedSites = GoogleSearchConsoleSite::with(['website.handler', 'website.assignees', 'account'])->get();

        // Latest available Google data date from mapped sites
        $latestGoogleDataDate = GoogleSearchConsoleSite::max('last_data_date');
        $lastSuccessfulSync = GoogleSearchConsoleSite::max('last_synced_at');

        // Parse filters from request
        $preset = $request->get('range', '28d');
        $customStart = $request->get('start_date');
        $customEnd = $request->get('end_date');
        $selectedClass = $request->get('class');
        $selectedWebsites = $this->parseFilterIds($request->get('websites'));
        $selectedMembers = $this->parseFilterIds($request->get('members'));

        $dateRange = $this->analyticsService->resolveDateRange($preset, $customStart, $customEnd, $latestGoogleDataDate);
        $resolvedWebsiteIds = $this->analyticsService->resolveWebsiteIds($user, $selectedWebsites, $selectedMembers, $selectedClass);

        // Fetch KPI Metrics
        $kpiSummary = $this->analyticsService->getKpiSummary(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['prev_start'],
            $dateRange['prev_end']
        );

        // Fetch Trend Series for Chart.js
        $dailyTrend = $this->analyticsService->getDailyTrend(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['prev_start'],
            $dateRange['prev_end']
        );

        // Fetch Initial Search Keywords Table (page 1, 25 items)
        $keywords = $this->analyticsService->getSearchKeywords(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $request->get('keyword_search'),
            $request->get('sort_by', 'clicks'),
            $request->get('sort_dir', 'desc'),
            (int) $request->get('page', 1),
            25
        );

        // Fetch Top Pages
        $topPages = $this->analyticsService->getTopPages(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $request->get('page_search'),
            'clicks',
            'desc',
            1,
            25
        );

        // Fetch Countries & Devices
        $countries = $this->analyticsService->getCountries($resolvedWebsiteIds, $dateRange['start'], $dateRange['end']);
        $devices = $this->analyticsService->getDevices($resolvedWebsiteIds, $dateRange['start'], $dateRange['end']);

        // Website comparison breakdown & rankings (sorted by impressions descending)
        $websiteBreakdown = !empty($resolvedWebsiteIds)
            ? $this->analyticsService->getWebsiteBreakdown($resolvedWebsiteIds, $dateRange['start'], $dateRange['end'], $dateRange['prev_start'], $dateRange['prev_end'])
            : [];

        $websiteRankings = $websiteBreakdown;
        usort($websiteRankings, fn($a, $b) => ($b['impressions'] ?? 0) <=> ($a['impressions'] ?? 0));

        // Initial comparison dataset (first 2 websites in scope)
        $compareIds = array_slice($resolvedWebsiteIds, 0, 2);
        $initialComparison = !empty($compareIds)
            ? $this->analyticsService->getWebsitesComparison($compareIds, $dateRange['start'], $dateRange['end'])
            : ['websites' => [], 'chart_dates' => [], 'datasets_clicks' => [], 'datasets_impressions' => []];

        $currentView = $request->get('view', 'overview');

        // Recent sync logs for administrator inspection
        $recentSyncLogs = GoogleSearchConsoleSyncLog::with('website')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        // Available properties and batch auto-mapper data from connected Google account
        $autoMatchData = $this->getAutoMatchData($account);
        $availableProperties = $autoMatchData['properties'];
        $initialBatchMatches = $autoMatchData['matches'];

        // Security authorization check: Only Mr. Dara can disconnect Google Search Console
        $userEmail = strtolower(trim($user?->email ?? ''));
        $canDisconnect = $user && (
            $user->isDara()
            || $userEmail === 'soporadara@kiuq.com'
            || str_contains($userEmail, 'soporadara')
        );

        return view('websites.search-console.index', compact(
            'isConfigured',
            'account',
            'authorizedWebsites',
            'websitesList',
            'availableClasses',
            'teamMembers',
            'mappedSites',
            'availableProperties',
            'initialBatchMatches',
            'latestGoogleDataDate',
            'lastSuccessfulSync',
            'dateRange',
            'selectedWebsites',
            'selectedMembers',
            'selectedClass',
            'resolvedWebsiteIds',
            'kpiSummary',
            'dailyTrend',
            'keywords',
            'topPages',
            'countries',
            'devices',
            'websiteBreakdown',
            'websiteRankings',
            'initialComparison',
            'currentView',
            'recentSyncLogs',
            'canDisconnect'
        ));
    }

    /**
     * AJAX endpoint returning updated metrics without full page reload
     */
    public function data(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $preset = $request->get('range', '28d');
        $customStart = $request->get('start_date');
        $customEnd = $request->get('end_date');
        $selectedClass = $request->get('class');
        $selectedWebsites = $this->parseFilterIds($request->get('websites'));
        $selectedMembers = $this->parseFilterIds($request->get('members'));

        $latestGoogleDataDate = GoogleSearchConsoleSite::max('last_data_date');
        $dateRange = $this->analyticsService->resolveDateRange($preset, $customStart, $customEnd, $latestGoogleDataDate);
        $resolvedWebsiteIds = $this->analyticsService->resolveWebsiteIds($user, $selectedWebsites, $selectedMembers, $selectedClass);

        $tab = $request->get('tab', 'overview');

        $kpiSummary = $this->analyticsService->getKpiSummary(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['prev_start'],
            $dateRange['prev_end']
        );

        $dailyTrend = $this->analyticsService->getDailyTrend(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['prev_start'],
            $dateRange['prev_end']
        );

        $keywords = $this->analyticsService->getSearchKeywords(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $request->get('keyword_search'),
            $request->get('sort_by', 'clicks'),
            $request->get('sort_dir', 'desc'),
            (int) $request->get('page', 1),
            (int) $request->get('per_page', 25)
        );

        $topPages = $this->analyticsService->getTopPages(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $request->get('page_search'),
            $request->get('page_sort_by', 'clicks'),
            $request->get('page_sort_dir', 'desc'),
            (int) $request->get('pages_page', 1),
            25
        );

        $countries = $this->analyticsService->getCountries($resolvedWebsiteIds, $dateRange['start'], $dateRange['end']);
        $devices = $this->analyticsService->getDevices($resolvedWebsiteIds, $dateRange['start'], $dateRange['end']);

        $websiteBreakdown = (!empty($resolvedWebsiteIds))
            ? $this->analyticsService->getWebsiteBreakdown($resolvedWebsiteIds, $dateRange['start'], $dateRange['end'], $dateRange['prev_start'], $dateRange['prev_end'])
            : [];

        return response()->json([
            'success'             => true,
            'date_range'          => $dateRange,
            'website_ids'         => $resolvedWebsiteIds,
            'kpi_summary'         => $kpiSummary,
            'daily_trend'         => $dailyTrend,
            'keywords'            => $keywords,
            'top_pages'           => $topPages,
            'countries'           => $countries,
            'devices'             => $devices,
            'website_breakdown'   => $websiteBreakdown,
        ]);
    }

    /**
     * Inspect single keyword daily performance
     */
    public function keywordDetails(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $keyword = $request->get('keyword');
        if (empty($keyword)) {
            return response()->json(['error' => 'Keyword parameter required'], 422);
        }

        $preset = $request->get('range', '28d');
        $dateRange = $this->analyticsService->resolveDateRange($preset, $request->get('start_date'), $request->get('end_date'));
        $resolvedWebsiteIds = $this->analyticsService->resolveWebsiteIds(
            $user,
            $this->parseFilterIds($request->get('websites')),
            $this->parseFilterIds($request->get('members'))
        );

        $details = $this->analyticsService->getKeywordDetails(
            $resolvedWebsiteIds,
            $keyword,
            $dateRange['start'],
            $dateRange['end']
        );

        return response()->json($details);
    }

    /**
     * Retrieve available Search Console properties from Google API for mapping
     */
    public function properties(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ($user->isSpecialManagerOrAdmin() || $user->hasAnyRole(['super-admin', 'admin-digital'])), 403);

        $account = GoogleSearchConsoleAccount::where('is_active', true)->latest()->first();
        if (!$account) {
            return response()->json(['error' => 'No active Google account connected'], 400);
        }

        try {
            $properties = $this->clientService->listProperties($account);
            $mappedSiteUrls = GoogleSearchConsoleSite::pluck('site_url')->toArray();

            $websites = Website::where('is_archived', false)->get(['id', 'name', 'url']);

            // Attach auto-match suggestions based on domain/URL
            $enhancedProperties = array_map(function ($prop) use ($mappedSiteUrls, $websites) {
                $siteUrl = $prop['site_url'];
                $cleanPropDomain = preg_replace('#^(sc-domain:|https?://(www\.)?)#i', '', rtrim($siteUrl, '/'));
                $isDomainProp = str_starts_with($siteUrl, 'sc-domain:');

                $suggestedWebsite = $websites->first(function ($w) use ($cleanPropDomain) {
                    $cleanWsDomain = preg_replace('#^(https?://(www\.)?)#i', '', rtrim($w->url, '/'));
                    return strcasecmp($cleanWsDomain, $cleanPropDomain) === 0
                        || str_contains(strtolower($cleanWsDomain), strtolower($cleanPropDomain))
                        || str_contains(strtolower($cleanPropDomain), strtolower($cleanWsDomain));
                });

                return [
                    'site_url'          => $siteUrl,
                    'clean_domain'      => $cleanPropDomain,
                    'type'              => $isDomainProp ? 'Domain' : 'URL Prefix',
                    'permission_level'  => $prop['permission_level'],
                    'is_already_mapped' => in_array($siteUrl, $mappedSiteUrls),
                    'suggested_ws_id'   => $suggestedWebsite?->id,
                    'suggested_ws_name' => $suggestedWebsite?->name,
                ];
            }, $properties);

            return response()->json([
                'success'    => true,
                'properties' => $enhancedProperties,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to list GSC properties', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch properties from Google: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Auto-match DGT websites with Google Search Console properties
     */
    /**
     * Shared helper to retrieve verified Google Search Console properties and match against DGT websites
     */
    public function getAutoMatchData(?GoogleSearchConsoleAccount $account): array
    {
        $formattedProperties = [];
        if ($account && $account->isValid()) {
            try {
                $rawProps = $this->clientService->listProperties($account);
                $formattedProperties = array_map(function ($p) {
                    $siteUrl = $p['site_url'];
                    $clean = preg_replace('#^(sc-domain:|https?://(www\.)?)#i', '', rtrim($siteUrl, '/'));
                    return [
                        'site_url'         => $siteUrl,
                        'clean_domain'     => strtolower($clean),
                        'display'          => $clean . (str_starts_with($siteUrl, 'sc-domain:') ? ' (Domain)' : ' (URL)'),
                        'type'             => str_starts_with($siteUrl, 'sc-domain:') ? 'Domain' : 'URL Prefix',
                        'permission_level' => $p['permission_level'] ?? 'siteFullUser',
                    ];
                }, $rawProps);
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch available properties: ' . $e->getMessage());
            }
        }

        $existingMappings = GoogleSearchConsoleSite::all()->keyBy('website_id');
        $websites = Website::where('is_archived', false)
            ->with(['handler:id,name,email,avatar'])
            ->orderBy('name')
            ->get();

        $matches = [];
        foreach ($websites as $w) {
            $existingSite = $existingMappings->get($w->id);
            $cleanWsDomain = strtolower($w->clean_domain ?: preg_replace('#^(https?://(www\.)?)#i', '', rtrim($w->url, '/')));

            $bestProp = null;
            $confidence = 'none';

            foreach ($formattedProperties as $fp) {
                if ($fp['clean_domain'] === $cleanWsDomain) {
                    $bestProp = $fp;
                    $confidence = 'exact';
                    break;
                }
            }

            if (!$bestProp) {
                foreach ($formattedProperties as $fp) {
                    if (str_contains($cleanWsDomain, $fp['clean_domain']) || str_contains($fp['clean_domain'], $cleanWsDomain)) {
                        $bestProp = $fp;
                        $confidence = 'partial';
                        break;
                    }
                }
            }

            $matches[] = [
                'website_id'         => $w->id,
                'website_name'       => $w->name,
                'website_domain'     => $cleanWsDomain,
                'category'           => $w->category ?: 'General',
                'handler_name'       => $w->handler?->name ?? 'Unassigned',
                'is_already_mapped'  => $existingSite !== null,
                'current_site_url'   => $existingSite?->site_url,
                'current_clean'      => $existingSite ? preg_replace('#^(sc-domain:|https?://(www\.)?)#i', '', rtrim($existingSite->site_url, '/')) : null,
                'suggested_site_url' => $bestProp ? $bestProp['site_url'] : ($existingSite?->site_url ?? ''),
                'suggested_clean'    => $bestProp ? $bestProp['clean_domain'] : ($existingSite ? preg_replace('#^(sc-domain:|https?://(www\.)?)#i', '', rtrim($existingSite->site_url, '/')) : ''),
                'confidence'         => $confidence,
            ];
        }

        return [
            'properties' => $formattedProperties,
            'matches'    => $matches,
        ];
    }

    /**
     * Auto-match DGT websites with verified Google Search Console properties
     */
    public function autoMatchMappings(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ($user->isSpecialManagerOrAdmin() || $user->hasAnyRole(['super-admin', 'admin-digital'])), 403);

        $account = GoogleSearchConsoleAccount::where('is_active', true)->latest()->first();
        if (!$account) {
            return response()->json(['error' => 'No active Google account connected'], 400);
        }

        try {
            $data = $this->getAutoMatchData($account);
            return response()->json([
                'success'    => true,
                'properties' => $data['properties'],
                'matches'    => $data['matches'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Auto-match failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to auto-match: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Batch save multiple website property mappings at once
     */
    public function batchSaveMappings(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ($user->isSpecialManagerOrAdmin() || $user->hasAnyRole(['super-admin', 'admin-digital'])), 403);

        $mappings = $request->input('mappings', []);
        if (empty($mappings) || !is_array($mappings)) {
            return response()->json(['error' => 'No mappings provided'], 422);
        }

        $account = GoogleSearchConsoleAccount::where('is_active', true)->latest()->first();
        $savedSites = [];

        foreach ($mappings as $m) {
            $wsId = $m['website_id'] ?? null;
            $siteUrl = trim($m['site_url'] ?? '');

            if (!$wsId || empty($siteUrl)) {
                continue;
            }

            $site = GoogleSearchConsoleSite::updateOrCreate(
                ['website_id' => $wsId],
                [
                    'account_id'       => $account?->id,
                    'site_url'         => $siteUrl,
                    'permission_level' => $m['permission_level'] ?? 'siteFullUser',
                    'is_verified'      => true,
                    'is_sync_enabled'  => true,
                ]
            );

            $savedSites[] = $site;
        }

        // Trigger immediate background sync for the saved sites
        if ($account && $account->isValid() && count($savedSites) > 0) {
            try {
                if (count($savedSites) > 5) {
                    $this->syncService->syncAll(30, 'batch_auto', $user->id);
                } else {
                    foreach ($savedSites as $s) {
                        $this->syncService->syncSite($s, 30, 'batch_auto', $user->id);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Batch sync error: ' . $e->getMessage());
            }
        }

        $count = count($savedSites);
        return response()->json([
            'success' => true,
            'count'   => $count,
            'message' => "Successfully saved and initiated synchronization for {$count} website mapping(s)!",
        ]);
    }

    /**
     * Compare performance between two or more websites
     */
    public function compareData(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $selectedWebsites = $this->parseFilterIds($request->get('websites'));
        if (empty($selectedWebsites)) {
            $selectedWebsites = GoogleSearchConsoleSite::where('is_sync_enabled', true)
                ->limit(2)
                ->pluck('website_id')
                ->all();
        }

        $preset = $request->get('range', '28d');
        $latestGoogleDataDate = GoogleSearchConsoleSite::max('last_data_date');
        $dateRange = $this->analyticsService->resolveDateRange($preset, $request->get('start_date'), $request->get('end_date'), $latestGoogleDataDate);

        $comparison = $this->analyticsService->getWebsitesComparison($selectedWebsites, $dateRange['start'], $dateRange['end']);

        return response()->json([
            'success'    => true,
            'date_range' => $dateRange,
            'comparison' => $comparison,
        ]);
    }

    /**
     * Save or update a website property mapping
     */
    public function saveMapping(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ($user->isSpecialManagerOrAdmin() || $user->hasAnyRole(['super-admin', 'admin-digital'])), 403);

        $validated = $request->validate([
            'website_id'       => 'required|exists:websites,id',
            'site_url'         => 'required|string',
            'permission_level' => 'nullable|string',
            'assigned_members' => 'nullable|array',
            'assigned_members.*' => 'exists:users,id',
        ]);

        $account = GoogleSearchConsoleAccount::where('is_active', true)->latest()->first();

        $site = GoogleSearchConsoleSite::updateOrCreate(
            ['website_id' => $validated['website_id']],
            [
                'account_id'       => $account?->id,
                'site_url'         => trim($validated['site_url']),
                'permission_level' => $validated['permission_level'] ?? 'siteFullUser',
                'is_verified'      => true,
                'is_sync_enabled'  => true,
            ]
        );

        // Update multi-member assignments if provided
        if (isset($validated['assigned_members'])) {
            $website = Website::find($validated['website_id']);
            if ($website) {
                $website->assignees()->sync($validated['assigned_members']);
            }
        }

        // Automatically sync data for this newly mapped website immediately in background
        if ($account && $account->isValid()) {
            try {
                $this->syncService->syncSite($site, 30, 'auto', $request->user()?->id);
            } catch (\Throwable $e) {
                Log::warning("Immediate auto-sync for {$site->site_url} failed: " . $e->getMessage());
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'site' => $site]);
        }

        return redirect()->route('websites.search-console.index')
            ->with('success', "Website mapping for {$site->site_url} saved and automatically synchronized!");
    }

    /**
     * Remove a website property mapping
     */
    public function deleteMapping(Request $request, $id)
    {
        $user = $request->user();
        abort_unless($user && ($user->isSpecialManagerOrAdmin() || $user->hasAnyRole(['super-admin', 'admin-digital'])), 403);

        $site = GoogleSearchConsoleSite::findOrFail($id);
        $websiteId = $site->website_id;

        // Clean up all synced data records associated with this unmapped site
        \App\Models\GoogleSearchConsole\GoogleSearchConsoleDailyMetric::where('website_id', $websiteId)->delete();
        \App\Models\GoogleSearchConsole\GoogleSearchConsoleSearchQuery::where('website_id', $websiteId)->delete();
        \App\Models\GoogleSearchConsole\GoogleSearchConsolePage::where('website_id', $websiteId)->delete();
        \App\Models\GoogleSearchConsole\GoogleSearchConsoleCountry::where('website_id', $websiteId)->delete();
        \App\Models\GoogleSearchConsole\GoogleSearchConsoleDevice::where('website_id', $websiteId)->delete();
        \App\Models\GoogleSearchConsole\GoogleSearchConsoleSyncLog::where('website_id', $websiteId)->delete();

        $site->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('websites.search-console.index')
            ->with('success', 'Search Console property mapping and synced data removed.');
    }

    /**
     * Trigger manual synchronization
     */
    public function syncNow(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ($user->isSpecialManagerOrAdmin() || $user->hasAnyRole(['super-admin', 'admin-digital'])), 403);

        $websiteId = $request->get('website_id');
        $days = (int) $request->get('days', 30);

        try {
            if ($websiteId) {
                $site = GoogleSearchConsoleSite::where('website_id', $websiteId)->firstOrFail();
                $res = $this->syncService->syncSite($site, $days, 'manual', $user->id);

                if ($res['success']) {
                    return response()->json([
                        'success' => true,
                        'message' => "Successfully synced {$res['records_synced']} records for {$site->site_url}!",
                        'latest_date' => $res['latest_date'],
                    ]);
                } else {
                    return response()->json(['success' => false, 'error' => $res['error']], 500);
                }
            } else {
                $res = $this->syncService->syncAll($days, 'manual', $user->id);
                return response()->json([
                    'success' => true,
                    'message' => "Synced {$res['total_records']} records across {$res['successful']} site(s)!",
                    'details' => $res,
                ]);
            }
        } catch (Exception $e) {
            Log::error('Manual sync failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Check if synchronization is needed and run it automatically in the background
     */
    public function autoSyncCheck(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasWebsiteAccess()) {
            return response()->json(['synced' => false, 'reason' => 'unauthorized'], 403);
        }

        if (!$this->clientService->isConfigured()) {
            return response()->json(['synced' => false, 'reason' => 'not_configured']);
        }

        $account = GoogleSearchConsoleAccount::where('is_active', true)->latest()->first();
        if (!$account || !$account->isValid()) {
            return response()->json(['synced' => false, 'reason' => 'no_valid_account']);
        }

        // Stale if never synced, or last sync was > 12 hours ago
        $staleSites = GoogleSearchConsoleSite::where('is_sync_enabled', true)
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                  ->orWhere('last_synced_at', '<', now()->subMinutes(30));
            })
            ->get();

        if ($staleSites->isEmpty()) {
            return response()->json([
                'synced'    => false,
                'reason'    => 'up_to_date',
                'last_sync' => GoogleSearchConsoleSite::max('last_synced_at'),
            ]);
        }

        // Prevent parallel duplicate auto-sync using cache lock
        if (!Cache::add('gsc_auto_sync_running', true, now()->addMinutes(10))) {
            return response()->json(['synced' => false, 'reason' => 'sync_in_progress']);
        }

        try {
            $totalSynced = 0;
            foreach ($staleSites as $site) {
                $res = $this->syncService->syncSite($site, 30, 'auto', $user->id);
                $totalSynced += ($res['records_synced'] ?? 0);
            }

            return response()->json([
                'synced'         => true,
                'sites_count'    => $staleSites->count(),
                'records_synced' => $totalSynced,
                'last_sync'      => GoogleSearchConsoleSite::max('last_synced_at'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Automatic Search Console sync check failed: ' . $e->getMessage());
            return response()->json(['synced' => false, 'error' => $e->getMessage()]);
        } finally {
            Cache::forget('gsc_auto_sync_running');
        }
    }

    /**
     * Export Printable PDF Report
     */
    public function exportPdf(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $reportType = $request->get('view', $request->get('report', 'overview'));
        $preset = $request->get('range', '28d');
        $customStart = $request->get('start_date');
        $customEnd = $request->get('end_date');
        $selectedClass = $request->get('class');
        $selectedWebsites = $this->parseFilterIds($request->get('websites'));
        $selectedMembers = $this->parseFilterIds($request->get('members'));

        $latestGoogleDataDate = GoogleSearchConsoleSite::max('last_data_date');
        $dateRange = $this->analyticsService->resolveDateRange($preset, $customStart, $customEnd, $latestGoogleDataDate);
        $resolvedWebsiteIds = $this->analyticsService->resolveWebsiteIds($user, $selectedWebsites, $selectedMembers, $selectedClass);

        $websites = Website::with(['handler:id,name,email,avatar'])
            ->whereIn('id', $resolvedWebsiteIds)
            ->get(['id', 'name', 'url', 'category', 'handled_by']);
        $members = User::whereIn('id', $selectedMembers ?: [])->get(['id', 'name']);
        $scopeTitle = $this->buildScopeTitle($websites, $selectedClass, $members);

        // 1. Website Rankings & Leaderboard Report
        if ($reportType === 'rankings') {
            $websiteBreakdown = $this->analyticsService->getWebsiteBreakdown(
                $resolvedWebsiteIds,
                $dateRange['start'],
                $dateRange['end'],
                $dateRange['prev_start'],
                $dateRange['prev_end']
            );

            usort($websiteBreakdown, fn($a, $b) => ($b['clicks'] ?? 0) <=> ($a['clicks'] ?? 0));

            // Extract top performers
            $topClicks = $websiteBreakdown[0] ?? null;

            $byImpr = $websiteBreakdown;
            usort($byImpr, fn($a, $b) => ($b['impressions'] ?? 0) <=> ($a['impressions'] ?? 0));
            $topImpr = $byImpr[0] ?? null;

            $byCtr = array_filter($websiteBreakdown, fn($w) => ($w['impressions'] ?? 0) > 0);
            usort($byCtr, fn($a, $b) => ($b['ctr'] ?? 0) <=> ($a['ctr'] ?? 0));
            $topCtr = !empty($byCtr) ? reset($byCtr) : null;

            $byPos = array_filter($websiteBreakdown, fn($w) => ($w['position'] ?? 0) > 0);
            usort($byPos, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
            $topPos = !empty($byPos) ? reset($byPos) : null;

            $topPerformers = [
                'clicks'      => $topClicks,
                'impressions' => $topImpr,
                'ctr'         => $topCtr,
                'position'    => $topPos,
            ];

            // Compute class breakdown summaries
            $classGroups = [];
            foreach ($websiteBreakdown as $w) {
                $cls = $w['class'] ?? 'General';
                if (!isset($classGroups[$cls])) {
                    $classGroups[$cls] = [
                        'class'            => $cls,
                        'sites_count'      => 0,
                        'clicks'           => 0,
                        'prev_clicks'      => 0,
                        'impressions'      => 0,
                        'prev_impressions' => 0,
                        'position_sum'     => 0,
                    ];
                }
                $classGroups[$cls]['sites_count']++;
                $classGroups[$cls]['clicks'] += (int) ($w['clicks'] ?? 0);
                $classGroups[$cls]['prev_clicks'] += (int) ($w['prev_clicks'] ?? 0);
                $classGroups[$cls]['impressions'] += (int) ($w['impressions'] ?? 0);
                $classGroups[$cls]['prev_impressions'] += (int) ($w['prev_impressions'] ?? 0);
                $classGroups[$cls]['position_sum'] += (float) ($w['position'] ?? 0);
            }

            $classSummaries = [];
            foreach ($classGroups as $cls => $cg) {
                $cg['clicks_growth_pct'] = $this->analyticsService->calcPercentChange($cg['clicks'], $cg['prev_clicks']);
                $cg['impressions_growth_pct'] = $this->analyticsService->calcPercentChange($cg['impressions'], $cg['prev_impressions']);
                $cg['ctr'] = $cg['impressions'] > 0 ? round(($cg['clicks'] / $cg['impressions']) * 100, 2) : 0;
                $cg['position'] = $cg['sites_count'] > 0 ? round($cg['position_sum'] / $cg['sites_count'], 1) : 0;
                $classSummaries[] = $cg;
            }

            $safeScope = preg_replace('/[^A-Za-z0-9_\-]/', '_', $scopeTitle);
            $fileName = 'GSC_Rankings_' . $safeScope . '_' . $dateRange['start'] . '_to_' . $dateRange['end'] . '.pdf';

            $pdf = Pdf::loadView('websites.search-console.pdf.rankings', [
                'scopeTitle'     => $scopeTitle,
                'rankings'       => $websiteBreakdown,
                'topPerformers'  => $topPerformers,
                'classSummaries' => $classSummaries,
                'members'        => $members,
                'selectedClass'  => $selectedClass,
                'dateRange'      => $dateRange,
            ])->setPaper('a4', 'portrait');

            return $pdf->download($fileName);
        }

        // 2. Compare Websites Head-to-Head Report
        if ($reportType === 'compare') {
            $siteAId = (int) $request->get('site_a');
            $siteBId = (int) $request->get('site_b');

            if (!$siteAId || !$siteBId) {
                if (count($selectedWebsites) >= 2) {
                    $siteAId = $selectedWebsites[0];
                    $siteBId = $selectedWebsites[1];
                } elseif (count($resolvedWebsiteIds) >= 2) {
                    $siteAId = $resolvedWebsiteIds[0];
                    $siteBId = $resolvedWebsiteIds[1];
                } elseif (count($resolvedWebsiteIds) === 1) {
                    $siteAId = $resolvedWebsiteIds[0];
                    $siteBId = $resolvedWebsiteIds[0];
                }
            }

            $compareIds = array_filter(array_unique([$siteAId, $siteBId]));
            $comparison = $this->analyticsService->getWebsitesComparison($compareIds, $dateRange['start'], $dateRange['end']);

            $siteA = Website::with('handler')->find($siteAId);
            $siteB = Website::with('handler')->find($siteBId);

            $metricsA = collect($comparison['websites'])->firstWhere('website_id', $siteAId) ?? [];
            $metricsB = collect($comparison['websites'])->firstWhere('website_id', $siteBId) ?? [];

            $queriesA = $metricsA['top_queries'] ?? [];
            $queriesB = $metricsB['top_queries'] ?? [];

            $dailyComparison = [];
            $dates = $comparison['chart_dates'] ?? [];
            $dailyClicksA = $metricsA['daily_clicks'] ?? [];
            $dailyClicksB = $metricsB['daily_clicks'] ?? [];
            $dailyImprA = $metricsA['daily_impressions'] ?? [];
            $dailyImprB = $metricsB['daily_impressions'] ?? [];

            foreach ($dates as $idx => $d) {
                $dailyComparison[] = [
                    'date'     => $d,
                    'clicks_a' => $dailyClicksA[$idx] ?? 0,
                    'clicks_b' => $dailyClicksB[$idx] ?? 0,
                    'impr_a'   => $dailyImprA[$idx] ?? 0,
                    'impr_b'   => $dailyImprB[$idx] ?? 0,
                ];
            }

            $scopeTitle = ($siteA?->name ?? 'Site A') . ' vs ' . ($siteB?->name ?? 'Site B');
            $safeScope = preg_replace('/[^A-Za-z0-9_\-]/', '_', $scopeTitle);
            $fileName = 'GSC_Comparison_' . $safeScope . '_' . $dateRange['start'] . '_to_' . $dateRange['end'] . '.pdf';

            $pdf = Pdf::loadView('websites.search-console.pdf.compare', [
                'scopeTitle'      => $scopeTitle,
                'siteA'           => $siteA,
                'siteB'           => $siteB,
                'metricsA'        => $metricsA,
                'metricsB'        => $metricsB,
                'queriesA'        => $queriesA,
                'queriesB'        => $queriesB,
                'dailyComparison' => $dailyComparison,
                'dateRange'       => $dateRange,
            ])->setPaper('a4', 'portrait');

            return $pdf->download($fileName);
        }

        // 3. Sitemaps & Page Indexation Report
        if ($reportType === 'sitemaps') {
            $websiteId = (int) ($request->get('website_id') ?: ($selectedWebsites[0] ?? ($resolvedWebsiteIds[0] ?? 0)));
            $website = Website::with('handler')->findOrFail($websiteId);

            $overview = $this->sitemapService->getIndexationOverview($website, null, null, 1, 300);
            $sitemapsData = $this->sitemapService->getSitemapsForWebsite($website);

            $summary = $overview['summary'] ?? [
                'total_discovered'     => 0,
                'total_indexed'        => 0,
                'total_not_indexed'    => 0,
                'index_rate'           => 0,
                'is_official_snapshot' => false,
                'gsc_last_updated'     => null,
                'gsc_reasons'          => [],
            ];
            $pages = $overview['pages']['data'] ?? [];
            $sitemaps = $sitemapsData['sitemaps'] ?? [];

            $scopeTitle = $website->name . ' (' . $website->clean_domain . ')';
            $safeScope = preg_replace('/[^A-Za-z0-9_\-]/', '_', $scopeTitle);
            $fileName = 'GSC_Sitemaps_Indexation_' . $safeScope . '_' . date('Y-m-d') . '.pdf';

            $pdf = Pdf::loadView('websites.search-console.pdf.sitemaps', [
                'scopeTitle' => $scopeTitle,
                'website'    => $website,
                'summary'    => $summary,
                'pages'      => $pages,
                'sitemaps'   => $sitemaps,
                'dateRange'  => $dateRange,
            ])->setPaper('a4', 'portrait');

            return $pdf->download($fileName);
        }

        // 4. Default: Overview & Analytics Report
        $websiteBreakdown = $this->analyticsService->getWebsiteBreakdown(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['prev_start'],
            $dateRange['prev_end']
        );

        $kpiSummary = $this->analyticsService->getKpiSummary(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['prev_start'],
            $dateRange['prev_end']
        );

        $dailyTrend = $this->analyticsService->getDailyTrend(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['prev_start'],
            $dateRange['prev_end']
        );

        $keywords = $this->analyticsService->getSearchKeywords(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $request->get('keyword_search'),
            'clicks',
            'desc',
            1,
            50
        );

        $topPages = $this->analyticsService->getTopPages(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            null,
            'clicks',
            'desc',
            1,
            30
        );

        $countries = $this->analyticsService->getCountries($resolvedWebsiteIds, $dateRange['start'], $dateRange['end']);
        $devices = $this->analyticsService->getDevices($resolvedWebsiteIds, $dateRange['start'], $dateRange['end']);

        $safeScope = preg_replace('/[^A-Za-z0-9_\-]/', '_', $scopeTitle);
        $fileName = 'GSC_SEO_Report_' . $safeScope . '_' . $dateRange['start'] . '_to_' . $dateRange['end'] . '.pdf';

        $pdf = Pdf::loadView('websites.search-console.pdf', compact(
            'scopeTitle',
            'websites',
            'websiteBreakdown',
            'members',
            'selectedClass',
            'dateRange',
            'kpiSummary',
            'dailyTrend',
            'keywords',
            'topPages',
            'countries',
            'devices'
        ))->setPaper('a4', 'portrait');

        return $pdf->download($fileName);
    }

    /**
     * Helper to build clean scope title for reports
     */
    protected function buildScopeTitle($websites, ?string $selectedClass, $members): string
    {
        if (count($websites) === 1) {
            return $websites->first()->name;
        } elseif (!empty($selectedClass) && $selectedClass !== 'all' && !empty($members) && $members->isNotEmpty()) {
            return $selectedClass . ' - ' . $members->pluck('name')->implode(', ') . ' (' . count($websites) . ' Sites)';
        } elseif (!empty($selectedClass) && $selectedClass !== 'all') {
            return $selectedClass . ' (' . count($websites) . ' Sites)';
        } elseif (!empty($members) && $members->isNotEmpty()) {
            return $members->pluck('name')->implode(', ') . ' Portfolio (' . count($websites) . ' Sites)';
        } else {
            return count($websites) . ' Websites (Portfolio Report)';
        }
    }

    /**
     * Export Multi-Sheet Excel Workbook (.xlsx)
     */
    public function exportExcel(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $reportType = $request->get('view', $request->get('report', 'overview'));
        $preset = $request->get('range', '28d');
        $customStart = $request->get('start_date');
        $customEnd = $request->get('end_date');
        $selectedClass = $request->get('class');
        $selectedWebsites = $this->parseFilterIds($request->get('websites'));
        $selectedMembers = $this->parseFilterIds($request->get('members'));

        $latestGoogleDataDate = GoogleSearchConsoleSite::max('last_data_date');
        $dateRange = $this->analyticsService->resolveDateRange($preset, $customStart, $customEnd, $latestGoogleDataDate);
        $resolvedWebsiteIds = $this->analyticsService->resolveWebsiteIds($user, $selectedWebsites, $selectedMembers, $selectedClass);

        $websites = Website::whereIn('id', $resolvedWebsiteIds)->get(['id', 'name', 'url', 'category', 'handled_by']);
        $members = User::whereIn('id', $selectedMembers ?: [])->get(['id', 'name']);
        $scopeTitle = $this->buildScopeTitle($websites, $selectedClass, $members);

        // 1. Website Rankings Leaderboard Excel
        if ($reportType === 'rankings') {
            $websiteBreakdown = $this->analyticsService->getWebsiteBreakdown(
                $resolvedWebsiteIds,
                $dateRange['start'],
                $dateRange['end'],
                $dateRange['prev_start'],
                $dateRange['prev_end']
            );

            usort($websiteBreakdown, fn($a, $b) => ($b['clicks'] ?? 0) <=> ($a['clicks'] ?? 0));

            // Extract top performers
            $topClicks = $websiteBreakdown[0] ?? null;
            $byImpr = $websiteBreakdown;
            usort($byImpr, fn($a, $b) => ($b['impressions'] ?? 0) <=> ($a['impressions'] ?? 0));
            $topImpr = $byImpr[0] ?? null;

            $byCtr = array_filter($websiteBreakdown, fn($w) => ($w['impressions'] ?? 0) > 0);
            usort($byCtr, fn($a, $b) => ($b['ctr'] ?? 0) <=> ($a['ctr'] ?? 0));
            $topCtr = !empty($byCtr) ? reset($byCtr) : null;

            $byPos = array_filter($websiteBreakdown, fn($w) => ($w['position'] ?? 0) > 0);
            usort($byPos, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
            $topPos = !empty($byPos) ? reset($byPos) : null;

            // Class summaries
            $classGroups = [];
            foreach ($websiteBreakdown as $w) {
                $cls = $w['class'] ?? 'General';
                if (!isset($classGroups[$cls])) {
                    $classGroups[$cls] = [
                        'class'            => $cls,
                        'sites_count'      => 0,
                        'clicks'           => 0,
                        'prev_clicks'      => 0,
                        'impressions'      => 0,
                        'prev_impressions' => 0,
                        'position_sum'     => 0,
                    ];
                }
                $classGroups[$cls]['sites_count']++;
                $classGroups[$cls]['clicks'] += (int) ($w['clicks'] ?? 0);
                $classGroups[$cls]['prev_clicks'] += (int) ($w['prev_clicks'] ?? 0);
                $classGroups[$cls]['impressions'] += (int) ($w['impressions'] ?? 0);
                $classGroups[$cls]['prev_impressions'] += (int) ($w['prev_impressions'] ?? 0);
                $classGroups[$cls]['position_sum'] += (float) ($w['position'] ?? 0);
            }

            $classRows = [];
            foreach ($classGroups as $cls => $cg) {
                $cGrowth = $this->analyticsService->calcPercentChange($cg['clicks'], $cg['prev_clicks']);
                $iGrowth = $this->analyticsService->calcPercentChange($cg['impressions'], $cg['prev_impressions']);
                $avgCtr = $cg['impressions'] > 0 ? round(($cg['clicks'] / $cg['impressions']) * 100, 2) : 0;
                $avgPos = $cg['sites_count'] > 0 ? round($cg['position_sum'] / $cg['sites_count'], 1) : 0;
                $classRows[] = [
                    $cls,
                    $cg['sites_count'],
                    $cg['clicks'],
                    $cg['prev_clicks'],
                    ($cGrowth >= 0 ? '+' : '') . $cGrowth . '%',
                    $cg['impressions'],
                    $cg['prev_impressions'],
                    ($iGrowth >= 0 ? '+' : '') . $iGrowth . '%',
                    $avgCtr . '%',
                    $avgPos,
                ];
            }

            $sheets = [
                [
                    'name' => 'Rankings Leaderboard',
                    'headers' => [
                        'Rank',
                        'Website Name',
                        'Domain',
                        'Class',
                        'Handler',
                        'Clicks (Current)',
                        'Clicks (Previous)',
                        'Clicks Growth (%)',
                        'Impressions (Current)',
                        'Impressions (Previous)',
                        'Impressions Growth (%)',
                        'CTR (%)',
                        'Average Position',
                        'Clicks Share (%)',
                        'Impressions Share (%)'
                    ],
                    'rows' => array_map(function ($idx, $wb) {
                        return [
                            $idx + 1,
                            $wb['name'],
                            $wb['clean_domain'] ?? $wb['domain'] ?? '',
                            $wb['class'] ?? 'General',
                            $wb['handler_name'] ?? 'Unassigned',
                            $wb['clicks'],
                            $wb['prev_clicks'] ?? 0,
                            (($wb['clicks_growth_pct'] ?? 0) >= 0 ? '+' : '') . ($wb['clicks_growth_pct'] ?? 0) . '%',
                            $wb['impressions'],
                            $wb['prev_impressions'] ?? 0,
                            (($wb['impressions_growth_pct'] ?? 0) >= 0 ? '+' : '') . ($wb['impressions_growth_pct'] ?? 0) . '%',
                            $wb['ctr'] . '%',
                            $wb['position'],
                            ($wb['clicks_share'] ?? 0) . '%',
                            ($wb['impressions_share'] ?? 0) . '%',
                        ];
                    }, array_keys($websiteBreakdown), $websiteBreakdown),
                ],
                [
                    'name' => 'Top Performers',
                    'headers' => ['Award / Category', 'Winner Website', 'Domain', 'Class', 'Handler', 'Key Metric Value', 'Growth Rate (%)'],
                    'rows' => [
                        ['Top Clicks (Volume Leader)', $topClicks['name'] ?? 'N/A', $topClicks['clean_domain'] ?? '', $topClicks['class'] ?? '', $topClicks['handler_name'] ?? '', ($topClicks['clicks'] ?? 0) . ' Clicks', (($topClicks['clicks_growth_pct'] ?? 0) >= 0 ? '+' : '') . ($topClicks['clicks_growth_pct'] ?? 0) . '%'],
                        ['Top Impressions (Visibility)', $topImpr['name'] ?? 'N/A', $topImpr['clean_domain'] ?? '', $topImpr['class'] ?? '', $topImpr['handler_name'] ?? '', ($topImpr['impressions'] ?? 0) . ' Impressions', (($topImpr['impressions_growth_pct'] ?? 0) >= 0 ? '+' : '') . ($topImpr['impressions_growth_pct'] ?? 0) . '%'],
                        ['Highest CTR (Efficiency)', $topCtr['name'] ?? 'N/A', $topCtr['clean_domain'] ?? '', $topCtr['class'] ?? '', $topCtr['handler_name'] ?? '', ($topCtr['ctr'] ?? 0) . '% CTR', ($topCtr['clicks'] ?? 0) . ' Clicks'],
                        ['Best Search Position', $topPos['name'] ?? 'N/A', $topPos['clean_domain'] ?? '', $topPos['class'] ?? '', $topPos['handler_name'] ?? '', '#' . ($topPos['position'] ?? 0) . ' Avg Pos', ($topPos['clicks'] ?? 0) . ' Clicks'],
                    ],
                ],
                [
                    'name' => 'Class Breakdown',
                    'headers' => ['Website Class', 'Total Sites', 'Total Clicks', 'Previous Clicks', 'Clicks Growth (%)', 'Total Impressions', 'Previous Impressions', 'Impressions Growth (%)', 'Avg CTR (%)', 'Avg Position'],
                    'rows' => $classRows,
                ],
            ];

            $filePath = $this->xlsxService->generate($sheets);
            $safeScope = preg_replace('/[^A-Za-z0-9_\-]/', '_', $scopeTitle);
            $fileName = 'GSC_Rankings_' . $safeScope . '_' . $dateRange['start'] . '_to_' . $dateRange['end'] . '.xlsx';

            return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
        }

        // 2. Compare Websites Head-to-Head Excel
        if ($reportType === 'compare') {
            $siteAId = (int) $request->get('site_a');
            $siteBId = (int) $request->get('site_b');

            if (!$siteAId || !$siteBId) {
                if (count($selectedWebsites) >= 2) {
                    $siteAId = $selectedWebsites[0];
                    $siteBId = $selectedWebsites[1];
                } elseif (count($resolvedWebsiteIds) >= 2) {
                    $siteAId = $resolvedWebsiteIds[0];
                    $siteBId = $resolvedWebsiteIds[1];
                } elseif (count($resolvedWebsiteIds) === 1) {
                    $siteAId = $resolvedWebsiteIds[0];
                    $siteBId = $resolvedWebsiteIds[0];
                }
            }

            $compareIds = array_filter(array_unique([$siteAId, $siteBId]));
            $comparison = $this->analyticsService->getWebsitesComparison($compareIds, $dateRange['start'], $dateRange['end']);

            $siteA = Website::with('handler')->find($siteAId);
            $siteB = Website::with('handler')->find($siteBId);

            $metricsA = collect($comparison['websites'])->firstWhere('website_id', $siteAId) ?? [];
            $metricsB = collect($comparison['websites'])->firstWhere('website_id', $siteBId) ?? [];

            $queriesA = $metricsA['top_queries'] ?? [];
            $queriesB = $metricsB['top_queries'] ?? [];

            $dailyComparison = [];
            $dates = $comparison['chart_dates'] ?? [];
            $dailyClicksA = $metricsA['daily_clicks'] ?? [];
            $dailyClicksB = $metricsB['daily_clicks'] ?? [];
            $dailyImprA = $metricsA['daily_impressions'] ?? [];
            $dailyImprB = $metricsB['daily_impressions'] ?? [];

            foreach ($dates as $idx => $d) {
                $cA = $dailyClicksA[$idx] ?? 0;
                $cB = $dailyClicksB[$idx] ?? 0;
                $iA = $dailyImprA[$idx] ?? 0;
                $iB = $dailyImprB[$idx] ?? 0;
                $dailyComparison[] = [
                    $d,
                    $cA,
                    $cB,
                    ($cA - $cB >= 0 ? '+' : '') . ($cA - $cB),
                    $iA,
                    $iB,
                    ($iA - $iB >= 0 ? '+' : '') . ($iA - $iB),
                ];
            }

            $nameA = $siteA?->name ?? 'Site A';
            $nameB = $siteB?->name ?? 'Site B';

            $cDiff = ($metricsA['clicks'] ?? 0) - ($metricsB['clicks'] ?? 0);
            $iDiff = ($metricsA['impressions'] ?? 0) - ($metricsB['impressions'] ?? 0);
            $ctrDiff = round(($metricsA['ctr'] ?? 0) - ($metricsB['ctr'] ?? 0), 2);
            $posA = $metricsA['position'] ?? 0;
            $posB = $metricsB['position'] ?? 0;
            $posWinner = ($posA > 0 && ($posA < $posB || $posB == 0)) ? $nameA : (($posB > 0 && ($posB < $posA || $posA == 0)) ? $nameB : 'Tied');

            $sheets = [
                [
                    'name' => 'Head-to-Head Comparison',
                    'headers' => ['Comparison Metric', "{$nameA} (Site A)", "{$nameB} (Site B)", 'Net Difference', 'Leader / Advantage'],
                    'rows' => [
                        ['Domain', $siteA?->clean_domain ?? '', $siteB?->clean_domain ?? '', '-', '-'],
                        ['Class', $siteA?->category ?? 'General', $siteB?->category ?? 'General', '-', '-'],
                        ['Responsible Member', $siteA?->handler?->name ?? 'Unassigned', $siteB?->handler?->name ?? 'Unassigned', '-', '-'],
                        ['Total Clicks', $metricsA['clicks'] ?? 0, $metricsB['clicks'] ?? 0, ($cDiff >= 0 ? '+' : '') . $cDiff, $cDiff > 0 ? $nameA : ($cDiff < 0 ? $nameB : 'Tied')],
                        ['Total Impressions', $metricsA['impressions'] ?? 0, $metricsB['impressions'] ?? 0, ($iDiff >= 0 ? '+' : '') . $iDiff, $iDiff > 0 ? $nameA : ($iDiff < 0 ? $nameB : 'Tied')],
                        ['Average CTR (%)', ($metricsA['ctr'] ?? 0) . '%', ($metricsB['ctr'] ?? 0) . '%', ($ctrDiff >= 0 ? '+' : '') . $ctrDiff . '%', $ctrDiff > 0 ? $nameA : ($ctrDiff < 0 ? $nameB : 'Tied')],
                        ['Average Position', '#' . ($metricsA['position'] ?? 0), '#' . ($metricsB['position'] ?? 0), round($posA - $posB, 1), $posWinner],
                    ],
                ],
                [
                    'name' => substr("Site A Top Queries", 0, 31),
                    'headers' => ['Query / Search Term', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position'],
                    'rows' => array_map(function ($q) {
                        return [
                            $q->query ?? $q['query'] ?? '',
                            $q->clicks ?? $q['clicks'] ?? 0,
                            $q->impressions ?? $q['impressions'] ?? 0,
                            ($q->ctr ?? $q['ctr'] ?? 0) . '%',
                            $q->position ?? $q['position'] ?? 0,
                        ];
                    }, is_array($queriesA) ? $queriesA : (method_exists($queriesA, 'all') ? $queriesA->all() : [])),
                ],
                [
                    'name' => substr("Site B Top Queries", 0, 31),
                    'headers' => ['Query / Search Term', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position'],
                    'rows' => array_map(function ($q) {
                        return [
                            $q->query ?? $q['query'] ?? '',
                            $q->clicks ?? $q['clicks'] ?? 0,
                            $q->impressions ?? $q['impressions'] ?? 0,
                            ($q->ctr ?? $q['ctr'] ?? 0) . '%',
                            $q->position ?? $q['position'] ?? 0,
                        ];
                    }, is_array($queriesB) ? $queriesB : (method_exists($queriesB, 'all') ? $queriesB->all() : [])),
                ],
                [
                    'name' => 'Daily Trend Comparison',
                    'headers' => ['Date', "{$nameA} Clicks", "{$nameB} Clicks", 'Clicks Delta', "{$nameA} Impressions", "{$nameB} Impressions", 'Impressions Delta'],
                    'rows' => $dailyComparison,
                ],
            ];

            $filePath = $this->xlsxService->generate($sheets);
            $safeScope = preg_replace('/[^A-Za-z0-9_\-]/', '_', "{$nameA}_vs_{$nameB}");
            $fileName = 'GSC_Comparison_' . $safeScope . '_' . $dateRange['start'] . '_to_' . $dateRange['end'] . '.xlsx';

            return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
        }

        // 3. Sitemaps & Page Indexation Excel
        if ($reportType === 'sitemaps') {
            $websiteId = (int) ($request->get('website_id') ?: ($selectedWebsites[0] ?? ($resolvedWebsiteIds[0] ?? 0)));
            $website = Website::with('handler')->findOrFail($websiteId);

            $overview = $this->sitemapService->getIndexationOverview($website, null, null, 1, 500);
            $sitemapsData = $this->sitemapService->getSitemapsForWebsite($website);

            $summary = $overview['summary'] ?? [
                'total_discovered'     => 0,
                'total_indexed'        => 0,
                'total_not_indexed'    => 0,
                'index_rate'           => 0,
                'is_official_snapshot' => false,
                'gsc_last_updated'     => null,
                'gsc_reasons'          => [],
            ];
            $pages = $overview['pages']['data'] ?? [];
            $sitemaps = $sitemapsData['sitemaps'] ?? [];

            $sheets = [
                [
                    'name' => 'Indexation Overview',
                    'headers' => ['Metric / Property', 'Value', 'Diagnostics & Details'],
                    'rows' => [
                        ['Website Name', $website->name, $website->url],
                        ['Clean Domain', $website->clean_domain, ''],
                        ['Website Class', $website->category ?: 'General', ''],
                        ['Responsible Member', $website->handler?->name ?? 'Unassigned', ''],
                        ['GSC Snapshot Status', $summary['is_official_snapshot'] ? 'Official GSC Verified Snapshot' : 'Local Dynamic Sync', ''],
                        ['Last Snapshot Date', $summary['gsc_last_updated'] ?? now()->format('Y-m-d'), ''],
                        ['Total Discovered URLs', $summary['total_discovered'] ?? 0, 'All known URLs from sitemaps & search queries'],
                        ['Total Indexed Pages', $summary['total_indexed'] ?? 0, 'Visible in Google Search Results'],
                        ['Total Not Indexed Pages', $summary['total_not_indexed'] ?? 0, 'Excluded, blocked, crawling issues, or noindex'],
                        ['Indexation Health Rate', ($summary['index_rate'] ?? 0) . '%', ($summary['index_rate'] ?? 0) >= 80 ? 'Optimal (>=80%)' : 'Needs Optimization (<80%)'],
                    ],
                ],
                [
                    'name' => 'Submitted Sitemaps',
                    'headers' => ['Sitemap Path / URL', 'Type', 'Last Submitted', 'Status', 'Submitted URLs Count'],
                    'rows' => array_map(function ($sm) {
                        return [
                            $sm['path'] ?? '',
                            $sm['type'] ?? 'sitemap',
                            $sm['last_submitted'] ?? 'N/A',
                            $sm['status'] ?? 'Active',
                            $sm['submitted_urls'] ?? $sm['urls_count'] ?? 0,
                        ];
                    }, $sitemaps),
                ],
                [
                    'name' => 'Indexing Issues & Reasons',
                    'headers' => ['Reason / Exclusion Description', 'Affected Pages Count', 'Status'],
                    'rows' => array_map(function ($r) {
                        return [
                            $r['reason'] ?? $r['name'] ?? '',
                            $r['pages_count'] ?? $r['count'] ?? 0,
                            'Excluded from Index',
                        ];
                    }, $summary['gsc_reasons'] ?? []),
                ],
                [
                    'name' => 'Page Coverage Details',
                    'headers' => ['Page URL', 'Index Status', 'Google Coverage State', 'Verdict', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position', 'Last Crawled'],
                    'rows' => array_map(function ($p) {
                        return [
                            $p['url'] ?? '',
                            !empty($p['is_indexed']) ? 'Indexed' : 'Not Indexed',
                            $p['coverage_state'] ?? '',
                            $p['verdict'] ?? '',
                            $p['clicks'] ?? 0,
                            $p['impressions'] ?? 0,
                            ($p['ctr'] ?? 0) . '%',
                            $p['position'] ?? 0,
                            $p['last_crawl_time'] ?? $p['last_seen_date'] ?? 'N/A',
                        ];
                    }, $pages),
                ],
            ];

            $filePath = $this->xlsxService->generate($sheets);
            $safeScope = preg_replace('/[^A-Za-z0-9_\-]/', '_', $website->clean_domain);
            $fileName = 'GSC_Sitemaps_Indexation_' . $safeScope . '_' . date('Y-m-d') . '.xlsx';

            return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
        }

        $kpi = $this->analyticsService->getKpiSummary(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['prev_start'],
            $dateRange['prev_end']
        );

        $keywords = $this->analyticsService->getSearchKeywords(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            null,
            'clicks',
            'desc',
            1,
            500
        );

        $topPages = $this->analyticsService->getTopPages(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            null,
            'clicks',
            'desc',
            1,
            500
        );

        $daily = $this->analyticsService->getDailyTrend(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['prev_start'],
            $dateRange['prev_end']
        );

        $countries = $this->analyticsService->getCountries($resolvedWebsiteIds, $dateRange['start'], $dateRange['end']);
        $devices = $this->analyticsService->getDevices($resolvedWebsiteIds, $dateRange['start'], $dateRange['end']);

        $websiteBreakdown = $this->analyticsService->getWebsiteBreakdown(
            $resolvedWebsiteIds,
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['prev_start'],
            $dateRange['prev_end']
        );

        if (count($websites) === 1) {
            $scopeTitle = $websites->first()->name;
        } elseif (!empty($selectedClass) && $selectedClass !== 'all' && !empty($selectedMembers) && $members->isNotEmpty()) {
            $scopeTitle = $selectedClass . ' - ' . $members->pluck('name')->implode(', ') . ' (' . count($websites) . ' Sites)';
        } elseif (!empty($selectedClass) && $selectedClass !== 'all') {
            $scopeTitle = $selectedClass . ' (' . count($websites) . ' Sites)';
        } elseif (!empty($selectedMembers) && $members->isNotEmpty()) {
            $scopeTitle = $members->pluck('name')->implode(', ') . ' (' . count($websites) . ' Sites)';
        } else {
            $scopeTitle = count($websites) . ' Websites';
        }

        // Build Multi-Sheet Structure
        $sheets = [
            // Sheet 1: Overview & Growth
            [
                'name' => 'Overview & Growth',
                'headers' => ['Metric / Property', 'Current Period', 'Previous Period', 'Net Change (Diff)', 'Growth Rate (%)', 'Trend'],
                'rows' => [
                    ['Report Period Label', $dateRange['label'] ?? $preset, 'Comparison Period', '', '', ''],
                    ['Start Date', $dateRange['start'], $dateRange['prev_start'], '', '', ''],
                    ['End Date', $dateRange['end'], $dateRange['prev_end'], '', '', ''],
                    ['Duration (Days)', $dateRange['days'], $dateRange['days'], '0', '0%', ''],
                    ['Filter Scope: Class', $selectedClass ?: 'All Classes', '', '', '', ''],
                    ['Filter Scope: Member', !empty($members) && $members->isNotEmpty() ? $members->pluck('name')->implode(', ') : 'All Members', '', '', '', ''],
                    ['Total Websites Included', count($websites), count($websites), '0', '0%', ''],
                    ['Total Clicks', $kpi['clicks']['current'], $kpi['clicks']['previous'], ($kpi['clicks']['diff'] >= 0 ? '+' : '') . $kpi['clicks']['diff'], ($kpi['clicks']['percent'] >= 0 ? '+' : '') . $kpi['clicks']['percent'] . '%', strtoupper($kpi['clicks']['trend'])],
                    ['Total Impressions', $kpi['impressions']['current'], $kpi['impressions']['previous'], ($kpi['impressions']['diff'] >= 0 ? '+' : '') . $kpi['impressions']['diff'], ($kpi['impressions']['percent'] >= 0 ? '+' : '') . $kpi['impressions']['percent'] . '%', strtoupper($kpi['impressions']['trend'])],
                    ['Average CTR', $kpi['ctr']['current'] . '%', $kpi['ctr']['previous'] . '%', ($kpi['ctr']['diff'] >= 0 ? '+' : '') . $kpi['ctr']['diff'] . '%', ($kpi['ctr']['percent'] >= 0 ? '+' : '') . $kpi['ctr']['percent'] . '%', strtoupper($kpi['ctr']['trend'])],
                    ['Average Position', $kpi['position']['current'], $kpi['position']['previous'], ($kpi['position']['diff'] > 0 ? '+' : '') . $kpi['position']['diff'], ($kpi['position']['percent'] >= 0 ? '+' : '') . $kpi['position']['percent'] . '%', ($kpi['position']['trend'] === 'up' ? 'IMPROVED (Higher)' : 'DROPPED')],
                ],
            ],

            // Sheet 2: Website Growth Breakdown
            [
                'name' => 'Websites Growth Breakdown',
                'headers' => [
                    'Website Name',
                    'Domain',
                    'Class',
                    'Assigned Handler',
                    'Clicks (Current)',
                    'Clicks (Previous)',
                    'Clicks Growth (%)',
                    'Impressions (Current)',
                    'Impressions (Previous)',
                    'Impressions Growth (%)',
                    'CTR (%)',
                    'Average Position',
                    'Clicks Share (%)',
                    'Impressions Share (%)'
                ],
                'rows' => array_map(function ($wb) {
                    return [
                        $wb['name'],
                        $wb['url'],
                        $wb['class'] ?? 'General',
                        $wb['handler_name'] ?? 'Unassigned',
                        $wb['clicks'],
                        $wb['prev_clicks'] ?? 0,
                        (($wb['clicks_growth_pct'] ?? 0) >= 0 ? '+' : '') . ($wb['clicks_growth_pct'] ?? 0) . '%',
                        $wb['impressions'],
                        $wb['prev_impressions'] ?? 0,
                        (($wb['impressions_growth_pct'] ?? 0) >= 0 ? '+' : '') . ($wb['impressions_growth_pct'] ?? 0) . '%',
                        $wb['ctr'] . '%',
                        $wb['position'],
                        ($wb['clicks_share'] ?? 0) . '%',
                        ($wb['impressions_share'] ?? 0) . '%',
                    ];
                }, $websiteBreakdown),
            ],

            // Sheet 3: Search Keywords
            [
                'name' => 'Search Keywords',
                'headers' => ['Keyword / Search Query', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position'],
                'rows' => array_map(function ($k) {
                    return [
                        $k['query'],
                        $k['clicks'],
                        $k['impressions'],
                        $k['ctr'] . '%',
                        $k['position'],
                    ];
                }, $keywords['data']),
            ],

            // Sheet 4: Top Pages
            [
                'name' => 'Top Pages',
                'headers' => ['Page URL', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position'],
                'rows' => array_map(function ($p) {
                    return [
                        $p['page'],
                        $p['clicks'],
                        $p['impressions'],
                        $p['ctr'] . '%',
                        $p['position'],
                    ];
                }, $topPages['data']),
            ],

            // Sheet 5: Daily Performance
            [
                'name' => 'Daily Performance',
                'headers' => ['Date Label', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position'],
                'rows' => array_map(function ($idx) use ($daily) {
                    return [
                        $daily['labels'][$idx] ?? '',
                        $daily['clicks'][$idx] ?? 0,
                        $daily['impressions'][$idx] ?? 0,
                        ($daily['ctr'][$idx] ?? 0) . '%',
                        $daily['position'][$idx] ?? 0,
                    ];
                }, array_keys($daily['labels'] ?? [])),
            ],

            // Sheet 6: Countries
            [
                'name' => 'Countries',
                'headers' => ['Country Code', 'Country Name', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position'],
                'rows' => array_map(function ($c) {
                    return [
                        $c['country_code'],
                        $c['country_name'],
                        $c['clicks'],
                        $c['impressions'],
                        $c['ctr'] . '%',
                        $c['position'],
                    ];
                }, $countries),
            ],

            // Sheet 7: Devices
            [
                'name' => 'Devices',
                'headers' => ['Device Category', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position', 'Click Share (%)', 'Impression Share (%)'],
                'rows' => array_map(function ($d) {
                    return [
                        $d['device'],
                        $d['clicks'],
                        $d['impressions'],
                        $d['ctr'] . '%',
                        $d['position'],
                        $d['click_share'] . '%',
                        $d['imp_share'] . '%',
                    ];
                }, $devices),
            ],
        ];

        $filePath = $this->xlsxService->generate($sheets);
        $safeScope = preg_replace('/[^A-Za-z0-9_\-]/', '_', $scopeTitle);
        $fileName = 'GSC_SEO_Report_' . $safeScope . '_' . $dateRange['start'] . '_to_' . $dateRange['end'] . '.xlsx';

        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Export CSV with filter constraints and growth comparison support
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $reportType = $request->get('view', $request->get('report', 'rankings'));
        $preset = $request->get('range', '28d');
        $customStart = $request->get('start_date');
        $customEnd = $request->get('end_date');
        $selectedClass = $request->get('class');
        $selectedWebsites = $this->parseFilterIds($request->get('websites'));
        $selectedMembers = $this->parseFilterIds($request->get('members'));

        $latestGoogleDataDate = GoogleSearchConsoleSite::max('last_data_date');
        $dateRange = $this->analyticsService->resolveDateRange($preset, $customStart, $customEnd, $latestGoogleDataDate);
        $resolvedWebsiteIds = $this->analyticsService->resolveWebsiteIds($user, $selectedWebsites, $selectedMembers, $selectedClass);

        $fileName = "GSC_{$reportType}_{$dateRange['start']}_to_{$dateRange['end']}.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($reportType, $resolvedWebsiteIds, $selectedWebsites, $dateRange, $request) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel compatibility

            switch ($reportType) {
                case 'overview':
                    fputcsv($handle, ['Metric', 'Current Period', 'Previous Period', 'Net Change (Diff)', 'Growth Rate (%)']);
                    $kpi = $this->analyticsService->getKpiSummary(
                        $resolvedWebsiteIds,
                        $dateRange['start'],
                        $dateRange['end'],
                        $dateRange['prev_start'],
                        $dateRange['prev_end']
                    );
                    fputcsv($handle, ['Reporting Period', "{$dateRange['start']} to {$dateRange['end']} ({$dateRange['days']} days)", "{$dateRange['prev_start']} to {$dateRange['prev_end']}", '', '']);
                    fputcsv($handle, ['Total Clicks', $kpi['clicks']['current'], $kpi['clicks']['previous'], ($kpi['clicks']['diff'] >= 0 ? '+' : '') . $kpi['clicks']['diff'], ($kpi['clicks']['percent'] >= 0 ? '+' : '') . $kpi['clicks']['percent'] . '%']);
                    fputcsv($handle, ['Total Impressions', $kpi['impressions']['current'], $kpi['impressions']['previous'], ($kpi['impressions']['diff'] >= 0 ? '+' : '') . $kpi['impressions']['diff'], ($kpi['impressions']['percent'] >= 0 ? '+' : '') . $kpi['impressions']['percent'] . '%']);
                    fputcsv($handle, ['Average CTR', $kpi['ctr']['current'] . '%', $kpi['ctr']['previous'] . '%', ($kpi['ctr']['diff'] >= 0 ? '+' : '') . $kpi['ctr']['diff'] . '%', ($kpi['ctr']['percent'] >= 0 ? '+' : '') . $kpi['ctr']['percent'] . '%']);
                    fputcsv($handle, ['Average Position', $kpi['position']['current'], $kpi['position']['previous'], ($kpi['position']['diff'] > 0 ? '+' : '') . $kpi['position']['diff'], ($kpi['position']['percent'] >= 0 ? '+' : '') . $kpi['position']['percent'] . '%']);
                    break;

                case 'breakdown':
                case 'rankings':
                case 'growth':
                    fputcsv($handle, [
                        'Rank',
                        'Website Name',
                        'Domain',
                        'Class',
                        'Handler',
                        'Clicks (Current)',
                        'Clicks (Previous)',
                        'Clicks Growth (%)',
                        'Impressions (Current)',
                        'Impressions (Previous)',
                        'Impressions Growth (%)',
                        'CTR (%)',
                        'Average Position',
                        'Clicks Share (%)',
                        'Impressions Share (%)'
                    ]);
                    $breakdown = $this->analyticsService->getWebsiteBreakdown(
                        $resolvedWebsiteIds,
                        $dateRange['start'],
                        $dateRange['end'],
                        $dateRange['prev_start'],
                        $dateRange['prev_end']
                    );
                    usort($breakdown, fn($a, $b) => ($b['clicks'] ?? 0) <=> ($a['clicks'] ?? 0));
                    $rank = 1;
                    foreach ($breakdown as $wb) {
                        fputcsv($handle, [
                            $rank++,
                            $wb['name'],
                            $wb['clean_domain'],
                            $wb['class'] ?? 'General',
                            $wb['handler_name'] ?? 'Unassigned',
                            $wb['clicks'],
                            $wb['prev_clicks'] ?? 0,
                            (($wb['clicks_growth_pct'] ?? 0) >= 0 ? '+' : '') . ($wb['clicks_growth_pct'] ?? 0) . '%',
                            $wb['impressions'],
                            $wb['prev_impressions'] ?? 0,
                            (($wb['impressions_growth_pct'] ?? 0) >= 0 ? '+' : '') . ($wb['impressions_growth_pct'] ?? 0) . '%',
                            $wb['ctr'] . '%',
                            $wb['position'],
                            ($wb['clicks_share'] ?? 0) . '%',
                            ($wb['impressions_share'] ?? 0) . '%',
                        ]);
                    }
                    break;

                case 'compare':
                    $siteAId = (int) $request->get('site_a');
                    $siteBId = (int) $request->get('site_b');

                    if (!$siteAId || !$siteBId) {
                        if (count($selectedWebsites) >= 2) {
                            $siteAId = $selectedWebsites[0];
                            $siteBId = $selectedWebsites[1];
                        } elseif (count($resolvedWebsiteIds) >= 2) {
                            $siteAId = $resolvedWebsiteIds[0];
                            $siteBId = $resolvedWebsiteIds[1];
                        } elseif (count($resolvedWebsiteIds) === 1) {
                            $siteAId = $resolvedWebsiteIds[0];
                            $siteBId = $resolvedWebsiteIds[0];
                        }
                    }

                    $compareIds = array_filter(array_unique([$siteAId, $siteBId]));
                    $comparison = $this->analyticsService->getWebsitesComparison($compareIds, $dateRange['start'], $dateRange['end']);

                    $siteA = Website::find($siteAId);
                    $siteB = Website::find($siteBId);
                    $metricsA = collect($comparison['websites'])->firstWhere('website_id', $siteAId) ?? [];
                    $metricsB = collect($comparison['websites'])->firstWhere('website_id', $siteBId) ?? [];

                    $nameA = $siteA?->name ?? 'Site A';
                    $nameB = $siteB?->name ?? 'Site B';

                    fputcsv($handle, ['=== HEAD-TO-HEAD SUMMARY ===', '', '', '', '']);
                    fputcsv($handle, ['Metric', "{$nameA} (Site A)", "{$nameB} (Site B)", 'Net Difference', 'Leader / Winner']);
                    fputcsv($handle, ['Domain', $siteA?->clean_domain ?? '', $siteB?->clean_domain ?? '', '-', '-']);
                    fputcsv($handle, ['Class', $siteA?->category ?? 'General', $siteB?->category ?? 'General', '-', '-']);
                    $cDiff = ($metricsA['clicks'] ?? 0) - ($metricsB['clicks'] ?? 0);
                    $iDiff = ($metricsA['impressions'] ?? 0) - ($metricsB['impressions'] ?? 0);
                    $ctrDiff = round(($metricsA['ctr'] ?? 0) - ($metricsB['ctr'] ?? 0), 2);
                    fputcsv($handle, ['Total Clicks', $metricsA['clicks'] ?? 0, $metricsB['clicks'] ?? 0, ($cDiff >= 0 ? '+' : '') . $cDiff, $cDiff > 0 ? $nameA : ($cDiff < 0 ? $nameB : 'Tied')]);
                    fputcsv($handle, ['Total Impressions', $metricsA['impressions'] ?? 0, $metricsB['impressions'] ?? 0, ($iDiff >= 0 ? '+' : '') . $iDiff, $iDiff > 0 ? $nameA : ($iDiff < 0 ? $nameB : 'Tied')]);
                    fputcsv($handle, ['Average CTR (%)', ($metricsA['ctr'] ?? 0) . '%', ($metricsB['ctr'] ?? 0) . '%', ($ctrDiff >= 0 ? '+' : '') . $ctrDiff . '%', $ctrDiff > 0 ? $nameA : ($ctrDiff < 0 ? $nameB : 'Tied')]);
                    fputcsv($handle, ['Average Position', $metricsA['position'] ?? 0, $metricsB['position'] ?? 0, round(($metricsA['position'] ?? 0) - ($metricsB['position'] ?? 0), 1), ($metricsA['position'] ?? 0) < ($metricsB['position'] ?? 0) ? $nameA : $nameB]);

                    fputcsv($handle, []);
                    fputcsv($handle, ["=== {$nameA} TOP QUERIES ===", '', '', '', '']);
                    fputcsv($handle, ['Search Query', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position']);
                    foreach (($metricsA['top_queries'] ?? []) as $q) {
                        fputcsv($handle, [$q->query ?? $q['query'] ?? '', $q->clicks ?? $q['clicks'] ?? 0, $q->impressions ?? $q['impressions'] ?? 0, ($q->ctr ?? $q['ctr'] ?? 0) . '%', $q->position ?? $q['position'] ?? 0]);
                    }

                    fputcsv($handle, []);
                    fputcsv($handle, ["=== {$nameB} TOP QUERIES ===", '', '', '', '']);
                    fputcsv($handle, ['Search Query', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position']);
                    foreach (($metricsB['top_queries'] ?? []) as $q) {
                        fputcsv($handle, [$q->query ?? $q['query'] ?? '', $q->clicks ?? $q['clicks'] ?? 0, $q->impressions ?? $q['impressions'] ?? 0, ($q->ctr ?? $q['ctr'] ?? 0) . '%', $q->position ?? $q['position'] ?? 0]);
                    }

                    fputcsv($handle, []);
                    fputcsv($handle, ['=== DAILY COMPARISON TREND ===', '', '', '', '']);
                    fputcsv($handle, ['Date', "{$nameA} Clicks", "{$nameB} Clicks", "{$nameA} Impressions", "{$nameB} Impressions"]);
                    $dates = $comparison['chart_dates'] ?? [];
                    $dClicksA = $metricsA['daily_clicks'] ?? [];
                    $dClicksB = $metricsB['daily_clicks'] ?? [];
                    $dImprA = $metricsA['daily_impressions'] ?? [];
                    $dImprB = $metricsB['daily_impressions'] ?? [];
                    foreach ($dates as $idx => $d) {
                        fputcsv($handle, [$d, $dClicksA[$idx] ?? 0, $dClicksB[$idx] ?? 0, $dImprA[$idx] ?? 0, $dImprB[$idx] ?? 0]);
                    }
                    break;

                case 'sitemaps':
                    $websiteId = (int) ($request->get('website_id') ?: ($selectedWebsites[0] ?? ($resolvedWebsiteIds[0] ?? 0)));
                    $website = Website::findOrFail($websiteId);

                    $overview = $this->sitemapService->getIndexationOverview($website, null, null, 1, 1000);
                    $sitemapsData = $this->sitemapService->getSitemapsForWebsite($website);

                    $summary = $overview['summary'] ?? [];
                    $pages = $overview['pages']['data'] ?? [];
                    $sitemaps = $sitemapsData['sitemaps'] ?? [];

                    fputcsv($handle, ['=== INDEXATION OVERVIEW ===', '']);
                    fputcsv($handle, ['Website Name', $website->name]);
                    fputcsv($handle, ['Clean Domain', $website->clean_domain]);
                    fputcsv($handle, ['Class', $website->category ?: 'General']);
                    fputcsv($handle, ['Snapshot Status', ($summary['is_official_snapshot'] ?? false) ? 'Official GSC Snapshot' : 'Local Dynamic']);
                    fputcsv($handle, ['Total Discovered URLs', $summary['total_discovered'] ?? 0]);
                    fputcsv($handle, ['Total Indexed Pages', $summary['total_indexed'] ?? 0]);
                    fputcsv($handle, ['Total Not Indexed Pages', $summary['total_not_indexed'] ?? 0]);
                    fputcsv($handle, ['Indexation Rate (%)', ($summary['index_rate'] ?? 0) . '%']);

                    fputcsv($handle, []);
                    fputcsv($handle, ['=== SUBMITTED SITEMAPS ===', '', '', '', '']);
                    fputcsv($handle, ['Sitemap Path / URL', 'Type', 'Last Submitted', 'Status', 'Submitted URLs Count']);
                    foreach ($sitemaps as $sm) {
                        fputcsv($handle, [
                            $sm['path'] ?? '',
                            $sm['type'] ?? 'sitemap',
                            $sm['last_submitted'] ?? 'N/A',
                            $sm['status'] ?? 'Active',
                            $sm['submitted_urls'] ?? $sm['urls_count'] ?? 0,
                        ]);
                    }

                    fputcsv($handle, []);
                    fputcsv($handle, ['=== PAGE INDEXING REASONS / EXCLUSIONS ===', '', '']);
                    fputcsv($handle, ['Reason / Issue Description', 'Affected Pages Count', 'Status']);
                    foreach (($summary['gsc_reasons'] ?? []) as $r) {
                        fputcsv($handle, [$r['reason'] ?? $r['name'] ?? '', $r['pages_count'] ?? $r['count'] ?? 0, 'Excluded from Index']);
                    }

                    fputcsv($handle, []);
                    fputcsv($handle, ['=== PAGES COVERAGE & TRAFFIC ===', '', '', '', '', '', '', '', '']);
                    fputcsv($handle, ['Page URL', 'Index Status', 'Google Coverage State', 'Verdict', 'Clicks', 'Impressions', 'CTR (%)', 'Avg Position', 'Last Crawled']);
                    foreach ($pages as $p) {
                        fputcsv($handle, [
                            $p['url'] ?? '',
                            !empty($p['is_indexed']) ? 'Indexed' : 'Not Indexed',
                            $p['coverage_state'] ?? '',
                            $p['verdict'] ?? '',
                            $p['clicks'] ?? 0,
                            $p['impressions'] ?? 0,
                            ($p['ctr'] ?? 0) . '%',
                            $p['position'] ?? 0,
                            $p['last_crawl_time'] ?? $p['last_seen_date'] ?? 'N/A',
                        ]);
                    }
                    break;

                case 'keywords':
                    fputcsv($handle, ['Search Query', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position']);
                    $res = $this->analyticsService->getSearchKeywords(
                        $resolvedWebsiteIds,
                        $dateRange['start'],
                        $dateRange['end'],
                        $request->get('keyword_search'),
                        'clicks',
                        'desc',
                        1,
                        5000
                    );
                    foreach ($res['data'] as $r) {
                        fputcsv($handle, [$r['query'], $r['clicks'], $r['impressions'], $r['ctr'] . '%', $r['position']]);
                    }
                    break;

                case 'pages':
                    fputcsv($handle, ['Landing Page URL', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position']);
                    $res = $this->analyticsService->getTopPages(
                        $resolvedWebsiteIds,
                        $dateRange['start'],
                        $dateRange['end'],
                        $request->get('page_search'),
                        'clicks',
                        'desc',
                        1,
                        5000
                    );
                    foreach ($res['data'] as $r) {
                        fputcsv($handle, [$r['page'], $r['clicks'], $r['impressions'], $r['ctr'] . '%', $r['position']]);
                    }
                    break;

                case 'daily':
                    fputcsv($handle, ['Date', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position']);
                    $daily = $this->analyticsService->getDailyTrend(
                        $resolvedWebsiteIds,
                        $dateRange['start'],
                        $dateRange['end'],
                        $dateRange['prev_start'],
                        $dateRange['prev_end']
                    );
                    foreach (($daily['labels'] ?? []) as $idx => $label) {
                        fputcsv($handle, [
                            $label,
                            $daily['clicks'][$idx] ?? 0,
                            $daily['impressions'][$idx] ?? 0,
                            ($daily['ctr'][$idx] ?? 0) . '%',
                            $daily['position'][$idx] ?? 0,
                        ]);
                    }
                    break;

                case 'countries':
                    fputcsv($handle, ['Country Code', 'Country Name', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position']);
                    $countries = $this->analyticsService->getCountries($resolvedWebsiteIds, $dateRange['start'], $dateRange['end']);
                    foreach ($countries as $c) {
                        fputcsv($handle, [$c['country_code'], $c['country_name'], $c['clicks'], $c['impressions'], $c['ctr'] . '%', $c['position']]);
                    }
                    break;

                case 'devices':
                    fputcsv($handle, ['Device', 'Clicks', 'Impressions', 'CTR (%)', 'Average Position', 'Click Share (%)', 'Impression Share (%)']);
                    $devices = $this->analyticsService->getDevices($resolvedWebsiteIds, $dateRange['start'], $dateRange['end']);
                    foreach ($devices as $d) {
                        fputcsv($handle, [$d['device'], $d['clicks'], $d['impressions'], $d['ctr'] . '%', $d['position'], $d['click_share'] . '%', $d['imp_share'] . '%']);
                    }
                    break;
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Helper to safely parse comma-separated or array query parameters into integer arrays
     */
    protected function parseFilterIds($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_filter(array_map('intval', $value));
        }

        return array_filter(array_map('intval', explode(',', (string) $value)));
    }

    /**
     * Get live sitemaps for selected website directly from Google Search Console
     */
    public function getSitemaps(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $websiteId = (int) $request->get('website_id');
        $website = Website::find($websiteId);

        if (!$website) {
            return response()->json([
                'success' => false,
                'message' => 'Website not found.',
            ], 404);
        }

        $data = $this->sitemapService->getSitemapsForWebsite($website);

        return response()->json(array_merge(['success' => true], $data));
    }

    /**
     * Submit sitemap to Google Search Console
     * STRICT REQUIREMENT: Website must be mapped/linked to Google Search Console first
     */
    public function submitSitemap(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $request->validate([
            'website_id'  => 'required|integer|exists:websites,id',
            'sitemap_url' => 'required|url',
        ]);

        $website = Website::findOrFail($request->website_id);

        $mappedSite = GoogleSearchConsoleSite::where('website_id', $website->id)
            ->where('is_sync_enabled', true)
            ->first();

        if (!$mappedSite) {
            return response()->json([
                'success' => false,
                'message' => "Website '{$website->name}' is not linked with Google Search Console. You must add the property to Google Search Console and map it in the Property Manager first.",
            ], 422);
        }

        try {
            $result = $this->sitemapService->submitSitemapForWebsite($website, $request->sitemap_url, $user->id);
            return response()->json($result);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $requiresReconnect = false;

            if (str_contains($msg, 'ACCESS_TOKEN_SCOPE_INSUFFICIENT') || str_contains($msg, 'insufficient') || str_contains($msg, 'Reconnect / Switch')) {
                $requiresReconnect = true;
            }

            // Decode clean error message if raw Google JSON string was passed
            if (str_starts_with(trim($msg), '{')) {
                $decoded = json_decode($msg, true);
                if (!empty($decoded['error']['message'])) {
                    $msg = $decoded['error']['message'];
                }
            }

            return response()->json([
                'success'            => false,
                'message'            => $msg,
                'error'              => $msg,
                'requires_reconnect' => $requiresReconnect,
            ], 422);
        }
    }

    /**
     * Delete sitemap from Google Search Console
     */
    public function deleteSitemap(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $request->validate([
            'website_id'   => 'required|integer|exists:websites,id',
            'sitemap_url'  => 'nullable|string',
            'sitemap_path' => 'nullable|string',
        ]);

        $sitemapUrl = $request->input('sitemap_url') ?: $request->input('sitemap_path');
        if (empty($sitemapUrl)) {
            return response()->json(['success' => false, 'message' => 'Sitemap URL is required.'], 422);
        }

        $website = Website::findOrFail($request->website_id);

        try {
            $result = $this->sitemapService->deleteSitemapForWebsite($website, $sitemapUrl);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Test sitemap URL reachability & XML validation
     */
    public function testSitemapUrl(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $sitemapUrl = $request->get('sitemap_url');
        if (empty($sitemapUrl)) {
            return response()->json(['reachable' => false, 'message' => 'Please provide a sitemap URL.']);
        }

        $result = $this->sitemapService->testSitemapReachability($sitemapUrl);
        return response()->json($result);
    }

    /**
     * Inspect a single URL live via Google Search Console URL Inspection API
     */
    public function inspectUrl(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $targetUrl = $request->input('url') ?: $request->input('inspection_url');
        if (empty($targetUrl) || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'Valid inspection URL is required.'], 422);
        }

        $website = Website::findOrFail($request->website_id);

        $mappedSite = GoogleSearchConsoleSite::where('website_id', $website->id)
            ->where('is_sync_enabled', true)
            ->first();

        if (!$mappedSite) {
            return response()->json([
                'success' => false,
                'message' => "Website '{$website->name}' is not linked with Google Search Console. You must add the property to Google Search Console and map it first.",
            ], 422);
        }

        try {
            $result = $this->sitemapService->inspectPageUrl($website, $targetUrl, $user->id);
            return response()->json([
                'success'    => true,
                'data'       => $result,
                'inspection' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Test a live webpage URL reachability and indexability in real time like Google Search Console
     */
    public function testLiveUrl(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $targetUrl = $request->input('url') ?: $request->input('test_url');
        if (empty($targetUrl) || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'Valid URL is required.'], 422);
        }

        try {
            $result = $this->sitemapService->testLiveUrl($targetUrl);
            return response()->json([
                'success'   => true,
                'live_test' => $result,
                'data'      => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get indexation breakdown (Indexed vs Not Indexed pages) for a website
     */
    public function getIndexStatus(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $websiteId = (int) $request->get('website_id');
        $website = Website::find($websiteId);

        if (!$website) {
            return response()->json([
                'success' => false,
                'message' => 'Website not found.',
            ], 404);
        }

        $filter = $request->get('filter', 'all');
        $search = $request->get('search');
        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(10, min(100, (int) $request->get('per_page', 25)));
        $force = (bool) $request->boolean('refresh');

        $result = $this->sitemapService->getIndexationOverview($website, $filter, $search, $page, $perPage, $force);

        return response()->json(array_merge(['success' => true], $result));
    }

    /**
     * Export website indexation report to CSV
     */
    public function exportSitemapsCsv(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $websiteId = (int) $request->get('website_id');
        $website = Website::findOrFail($websiteId);

        $overview = $this->sitemapService->getIndexationOverview($website, null, null, 1, 1000);
        $pages = $overview['pages']['data'] ?? [];

        $filename = 'sitemaps_indexation_' . $website->clean_domain . '_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($pages, $website) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'Website',
                'Page URL',
                'Index Status',
                'Google Coverage State',
                'Verdict',
                'Clicks',
                'Impressions',
                'CTR (%)',
                'Avg Position',
                'Last Crawl / Seen Date',
            ]);

            foreach ($pages as $p) {
                fputcsv($handle, [
                    $website->name,
                    $p['url'],
                    $p['is_indexed'] ? 'Indexed' : 'Not Indexed',
                    $p['coverage_state'],
                    $p['verdict'],
                    $p['clicks'],
                    $p['impressions'],
                    $p['ctr'] . '%',
                    $p['position'],
                    $p['last_crawl_time'] ?? $p['last_seen_date'] ?? 'N/A',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Update official Google Search Console page indexing snapshot numbers
     */
    public function updateCoverageSnapshot(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasWebsiteAccess(), 403);

        $validated = $request->validate([
            'website_id'        => 'required|exists:websites,id',
            'indexed_pages'     => 'required|integer|min:0',
            'not_indexed_pages' => 'required|integer|min:0',
            'updated_date'      => 'nullable|string',
            'reasons_count'     => 'nullable|integer|min:0',
            'reasons_text'      => 'nullable|string',
        ]);

        $mappedSite = GoogleSearchConsoleSite::where('website_id', $validated['website_id'])->first();
        if (!$mappedSite) {
            return response()->json(['success' => false, 'message' => 'Website is not linked with Google Search Console.'], 422);
        }

        $reasons = [];
        if (!empty($validated['reasons_text'])) {
            $lines = explode("\n", trim($validated['reasons_text']));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                if (preg_match('#^(\d+)\s*[-:]?\s*(.+)$#', $line, $m)) {
                    $reasons[] = ['count' => (int) $m[1], 'reason' => trim($m[2])];
                } elseif (preg_match('#^(.+?)\s*[-:]?\s*(\d+)$#', $line, $m)) {
                    $reasons[] = ['count' => (int) $m[2], 'reason' => trim($m[1])];
                } else {
                    $reasons[] = ['count' => 0, 'reason' => $line];
                }
            }
        } elseif (!empty($validated['reasons_count'])) {
            $reasons[] = [
                'count'  => (int) $validated['reasons_count'],
                'reason' => 'Official GSC indexing issues / exclusion reasons',
            ];
        }

        $date = !empty($validated['updated_date'])
            ? \Carbon\Carbon::parse($validated['updated_date'])
            : now();

        $mappedSite->update([
            'gsc_indexed_pages'       => $validated['indexed_pages'],
            'gsc_not_indexed_pages'   => $validated['not_indexed_pages'],
            'gsc_indexing_updated_at' => $date,
            'gsc_indexing_reasons'    => $reasons,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Google Search Console indexing report snapshot updated (Indexed: {$validated['indexed_pages']}, Not Indexed: {$validated['not_indexed_pages']})!",
            'site'    => $mappedSite,
        ]);
    }
}

