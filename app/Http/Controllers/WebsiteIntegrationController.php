<?php

namespace App\Http\Controllers;

use App\Jobs\SyncWebsitePostsJob;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteIntegration;
use App\Models\WebsitePost;
use App\Models\WebsiteSyncLog;
use App\Services\Ga4AnalyticsSyncService;
use App\Services\Security\SafeUrlValidator;
use App\Services\WebsiteSyncService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WebsiteIntegrationController extends Controller
{
    public function __construct(
        private readonly WebsiteSyncService $syncService,
        private readonly Ga4AnalyticsSyncService $ga4Service
    ) {}

    // ─── 1. INTEGRATION PLATFORM MANAGEMENT VIEW ──────────────────────────────

    public function index(Request $request)
    {
        abort_unless(auth()->user()?->canAccessCentralConnectors(), 403, 'Access denied. The Central Connector platform is restricted to username "dara" and superadmin.');

        $hostingerAccount = $request->get('hostinger');
        $category = $request->get('category');
        $cmsStatus = $request->get('status');
        $syncStatus = $request->get('sync_status'); // 'all', 'connected', 'not_connected'
        $memberId = $request->get('member_id');
        $search = $request->get('search');

        $query = Website::where('is_archived', false)
            ->with(['integration', 'handler', 'assignees'])
            ->withCount('posts')
            ->withSum('posts', 'cumulative_views')
            ->withSum('posts', 'period_views');

        if ($hostingerAccount) {
            $aliases = [
                'americanskidsteers@gmail.com'  => ['americanskidsteers@gmail.com', 'Hostinger Account 1'],
                'americanskidsteer@gmail.com'   => ['americanskidsteer@gmail.com', 'Hostinger Account 2'],
                'impossiblemachinery@gmail.com' => ['impossiblemachinery@gmail.com', 'Hostinger Account 3'],
                'Hostinger Account 1'           => ['americanskidsteers@gmail.com', 'Hostinger Account 1'],
                'Hostinger Account 2'           => ['americanskidsteer@gmail.com', 'Hostinger Account 2'],
                'Hostinger Account 3'           => ['impossiblemachinery@gmail.com', 'Hostinger Account 3'],
            ];
            $targets = $aliases[$hostingerAccount] ?? [$hostingerAccount];
            $query->whereHas('integration', fn($q) => $q->whereIn('hostinger_account', $targets));
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($cmsStatus) {
            $query->whereHas('integration', fn($q) => $q->where('cms_status', $cmsStatus));
        }

        // Quick Filter: Connected vs Not Connected to Blog Sync
        if ($syncStatus === 'connected') {
            $query->whereHas('integration', fn($q) => $q->where('cms_status', WebsiteIntegration::CMS_STATUS_CONNECTED)->where('is_active', true));
        } elseif ($syncStatus === 'not_connected') {
            $query->where(function ($q) {
                $q->whereDoesntHave('integration')
                  ->orWhereHas('integration', fn($sq) => $sq->where('cms_status', '!=', WebsiteIntegration::CMS_STATUS_CONNECTED)->orWhere('is_active', false));
            });
        }

        if ($memberId) {
            $query->assignedToMember((int) $memberId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%");
            });
        }

        $websites = $query->orderBy('name')->paginate(25)->withQueryString();

        // Summary Statistics across all non-archived websites (cached 60s for instant tab switching)
        $stats = Cache::remember('dgt_fleet_summary_metrics', 60, function () {
            $totalWebsites = Website::where('is_archived', false)->count();
            $connectedCmsCount = WebsiteIntegration::where('cms_status', WebsiteIntegration::CMS_STATUS_CONNECTED)->where('is_active', true)->count();
            $notConnectedCount = $totalWebsites - $connectedCmsCount;
            $connectedAnalyticsCount = WebsiteIntegration::where('analytics_status', WebsiteIntegration::ANALYTICS_STATUS_CONNECTED)->count();
            $totalBlogPosts = WebsitePost::count();
            $totalCumulativeViews = (int) WebsitePost::sum('cumulative_views');
            $recentErrorsCount = WebsiteSyncLog::where('status', WebsiteSyncLog::STATUS_FAILED)
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            return compact(
                'totalWebsites',
                'connectedCmsCount',
                'notConnectedCount',
                'connectedAnalyticsCount',
                'totalBlogPosts',
                'totalCumulativeViews',
                'recentErrorsCount'
            );
        });
        extract($stats);

        // Filter dropdown data
        $categories = Website::whereNotNull('category')->distinct()->pluck('category')->sort()->values();
        $hostingerAccounts = [
            'americanskidsteers@gmail.com',
            'americanskidsteer@gmail.com',
            'impossiblemachinery@gmail.com',
        ];
        $members = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'avatar']);

        return view('websites.integrations.index', compact(
            'websites',
            'totalWebsites',
            'connectedCmsCount',
            'notConnectedCount',
            'connectedAnalyticsCount',
            'totalBlogPosts',
            'totalCumulativeViews',
            'recentErrorsCount',
            'categories',
            'hostingerAccounts',
            'members',
            'hostingerAccount',
            'category',
            'cmsStatus',
            'syncStatus',
            'memberId',
            'search'
        ));
    }

    // ─── 2. CROSS-WEBSITE BLOGS VIEW & RANKINGS ───────────────────────────────

    public function blogs(Request $request)
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $siteId = $request->get('website_id');
        $category = $request->get('category');
        $memberId = $request->get('member_id');
        $source = $request->get('source');
        $search = $request->get('search');
        $sort = $request->get('sort', 'views_desc');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = WebsitePost::with(['website.handler']);

        if ($siteId) {
            $query->where('website_id', $siteId);
        }

        if ($category) {
            $query->whereHas('website', fn($q) => $q->where('category', $category));
        }

        if ($memberId) {
            $query->whereHas('website', fn($q) => $q->assignedToMember((int) $memberId));
        }

        if ($source && $source !== 'all') {
            $query->where('analytics_source', $source);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('canonical_url', 'like', "%{$search}%")
                  ->orWhere('author_name', 'like', "%{$search}%")
                  ->orWhereHas('website', function ($wq) use ($search) {
                      $wq->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('handler', function ($hq) use ($search) {
                            $hq->where('name', 'like', "%{$search}%");
                        });
                  });
            });
        }

        if ($dateFrom) {
            $query->whereDate('published_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('published_at', '<=', $dateTo);
        }

        // Sorting
        match ($sort) {
            'views_asc'      => $query->orderBy('cumulative_views', 'asc'),
            'period_desc'    => $query->orderBy('period_views', 'desc'),
            'published_desc' => $query->orderBy('published_at', 'desc'),
            'published_asc'  => $query->orderBy('published_at', 'asc'),
            default          => $query->orderBy('cumulative_views', 'desc'),
        };

        // Top 5 Ranking Posts
        $topRanked = (clone $query)->take(5)->get();

        $posts = $query->paginate(30)->withQueryString();

        // Cached collections as pure arrays for fast dropdown filters, zero serialization bugs, and smooth tab switching
        $allWebsites = Cache::remember('dgt_active_websites_array', 300, function () {
            return Website::where('is_archived', false)
                ->with('handler:id,name,email,avatar')
                ->orderBy('name')
                ->get()
                ->map(function ($w) {
                    return [
                        'id' => (int) $w->id,
                        'name' => (string) $w->name,
                        'category' => (string) ($w->category ?: 'General'),
                        'handled_by' => $w->handled_by ? (int) $w->handled_by : null,
                        'handler_name' => $w->handler?->name ?: 'Unassigned',
                        'handler_avatar' => $w->handler?->avatar_url ?: '',
                    ];
                })
                ->all();
        });
        $selectedWebsite = $siteId ? collect($allWebsites)->firstWhere('id', (int) $siteId) : null;

        $categories = Cache::remember('dgt_website_categories_array', 300, function () {
            return Website::whereNotNull('category')->distinct()->pluck('category')->filter()->sort()->values()->all();
        });

        $members = Cache::remember('dgt_active_members_array', 300, function () {
            return User::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'avatar', 'team_role', 'crm_role'])
                ->map(function ($m) {
                    return [
                        'id' => (int) $m->id,
                        'name' => (string) $m->name,
                        'avatar' => (string) $m->avatar_url,
                        'role' => (string) ($m->team_role ?? $m->crm_role ?? 'Team Member'),
                    ];
                })
                ->all();
        });
        $selectedMember = $memberId ? collect($members)->firstWhere('id', (int) $memberId) : null;

        return view('websites.integrations.blogs', compact(
            'posts',
            'topRanked',
            'allWebsites',
            'selectedWebsite',
            'categories',
            'members',
            'selectedMember',
            'siteId',
            'category',
            'memberId',
            'source',
            'search',
            'sort',
            'dateFrom',
            'dateTo'
        ));
    }

    // ─── 3. EXPORT BLOGS TO CSV ───────────────────────────────────────────────

    public function exportBlogsCsv(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $siteId = $request->get('website_id');
        $category = $request->get('category');
        $memberId = $request->get('member_id');
        $source = $request->get('source');
        $search = $request->get('search');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $limit = $request->filled('limit') && (int)$request->get('limit') > 0 ? (int)$request->get('limit') : null;
        $sort = $request->get('sort', 'views_desc');

        $query = WebsitePost::with(['website.handler']);

        // Sorting
        match ($sort) {
            'views_asc'      => $query->orderBy('cumulative_views', 'asc'),
            'period_desc'    => $query->orderBy('period_views', 'desc'),
            'published_desc' => $query->orderBy('published_at', 'desc'),
            'published_asc'  => $query->orderBy('published_at', 'asc'),
            default          => $query->orderBy('cumulative_views', 'desc'),
        };

        if ($siteId) {
            $query->where('website_id', $siteId);
        }
        if ($category) {
            $query->whereHas('website', fn($q) => $q->where('category', $category));
        }
        if ($memberId) {
            $query->whereHas('website', fn($q) => $q->assignedToMember((int) $memberId));
        }
        if ($source && $source !== 'all') {
            $query->where('analytics_source', $source);
        }
        if ($dateFrom) {
            $query->whereDate('published_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('published_at', '<=', $dateTo);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('canonical_url', 'like', "%{$search}%")
                  ->orWhere('author_name', 'like', "%{$search}%")
                  ->orWhereHas('website', function ($wq) use ($search) {
                      $wq->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('handler', function ($hq) use ($search) {
                            $hq->where('name', 'like', "%{$search}%");
                        });
                  });
            });
        }

        if ($limit) {
            $query->take($limit);
        }

        $filenamePrefix = $limit ? "dgt-top-{$limit}-blogs-" : "dgt-central-blogs-";
        $filename = $filenamePrefix . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query, $limit) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Microsoft Excel
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Rank',
                'Website Name',
                'Domain',
                'Class / Category',
                'Assigned Member (Handler)',
                'Post Title',
                'Canonical URL',
                'Author',
                'Published Date',
                'Cumulative Views',
                'Period Views (30d)',
                'Page Views',
                'Unique Visitors',
                'Analytics Source',
                'Last Synced Timestamp',
            ]);

            $rank = 1;
            if ($limit) {
                $posts = $query->get();
                foreach ($posts as $post) {
                    fputcsv($handle, [
                        '#' . $rank++,
                        $post->website?->name ?? 'Unknown',
                        $post->website?->clean_domain ?? '',
                        $post->website?->category ?? '-',
                        $post->website?->handler?->name ?? 'Unassigned',
                        $post->title,
                        $post->canonical_url,
                        $post->author_name ?? '-',
                        $post->published_at ? $post->published_at->format('Y-m-d') : '-',
                        $post->cumulative_views,
                        $post->period_views,
                        $post->page_views,
                        $post->unique_visitors,
                        $post->analytics_source,
                        $post->last_synced_at ? $post->last_synced_at->format('Y-m-d H:i') : '-',
                    ]);
                }
            } else {
                $query->chunk(200, function ($posts) use ($handle, &$rank) {
                    foreach ($posts as $post) {
                        fputcsv($handle, [
                            '#' . $rank++,
                            $post->website?->name ?? 'Unknown',
                            $post->website?->clean_domain ?? '',
                            $post->website?->category ?? '-',
                            $post->website?->handler?->name ?? 'Unassigned',
                            $post->title,
                            $post->canonical_url,
                            $post->author_name ?? '-',
                            $post->published_at ? $post->published_at->format('Y-m-d') : '-',
                            $post->cumulative_views,
                            $post->period_views,
                            $post->page_views,
                            $post->unique_visitors,
                            $post->analytics_source,
                            $post->last_synced_at ? $post->last_synced_at->format('Y-m-d H:i') : '-',
                        ]);
                    }
                });
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ─── 4. EXPORT BLOGS TO PDF ───────────────────────────────────────────────

    public function exportBlogsPdf(Request $request)
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $siteId = $request->get('website_id');
        $category = $request->get('category');
        $memberId = $request->get('member_id');
        $search = $request->get('search');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $limit = $request->filled('limit') && (int)$request->get('limit') > 0 ? (int)$request->get('limit') : null;
        $sort = $request->get('sort', 'views_desc');

        $query = WebsitePost::with(['website.handler']);

        // Sorting
        match ($sort) {
            'views_asc'      => $query->orderBy('cumulative_views', 'asc'),
            'period_desc'    => $query->orderBy('period_views', 'desc'),
            'published_desc' => $query->orderBy('published_at', 'desc'),
            'published_asc'  => $query->orderBy('published_at', 'asc'),
            default          => $query->orderBy('cumulative_views', 'desc'),
        };

        if ($siteId) {
            $query->where('website_id', $siteId);
        }
        if ($category) {
            $query->whereHas('website', fn($q) => $q->where('category', $category));
        }
        if ($memberId) {
            $query->whereHas('website', fn($q) => $q->assignedToMember((int) $memberId));
        }
        if ($dateFrom) {
            $query->whereDate('published_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('published_at', '<=', $dateTo);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('canonical_url', 'like', "%{$search}%")
                  ->orWhere('author_name', 'like', "%{$search}%")
                  ->orWhereHas('website', function ($wq) use ($search) {
                      $wq->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('handler', function ($hq) use ($search) {
                            $hq->where('name', 'like', "%{$search}%");
                        });
                  });
            });
        }

        $effectiveLimit = $limit ?? 100;
        $posts = $query->take($effectiveLimit)->get();
        $totalViews = $posts->sum('cumulative_views');
        $site = $siteId ? Website::find($siteId) : null;
        $member = $memberId ? User::find($memberId) : null;

        $pdf = Pdf::loadView('websites.integrations.pdf-blogs', [
            'posts'       => $posts,
            'totalViews'  => $totalViews,
            'website'     => $site,
            'category'    => $category,
            'member'      => $member,
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'limit'       => $limit,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
            'generatedBy' => auth()->user()?->name ?? 'System',
        ])->setPaper('a4', 'landscape');

        $filenamePrefix = $limit ? "dgt-top-{$limit}-blogs-" : "dgt-blogs-report-";
        $filename = $filenamePrefix . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    // ─── 5. SAVE / CONFIGURE INTEGRATION ──────────────────────────────────────

    public function saveIntegration(Request $request, Website $website): JsonResponse|RedirectResponse
    {
        abort_unless(auth()->user()?->canAccessCentralConnectors(), 403, 'Access denied. The Central Connector platform is restricted to username "dara" and superadmin.');

        $validated = $request->validate([
            'connector_type'         => 'required|string|in:wordpress_plugin,wordpress_rest,custom_laravel,custom_php,custom_api,none',
            'endpoint_url'           => 'nullable|url|max:1000',
            'hostinger_account'      => 'nullable|string|max:100',
            'ga4_property_id'        => 'nullable|string|max:50',
            'ga4_credentials_json'   => 'nullable|string',
            'sync_frequency_minutes' => 'nullable|integer|min:15|max:1440',
            'is_active'              => 'nullable|boolean',
        ]);

        // Validate endpoint against SSRF if supplied
        if (!empty($validated['endpoint_url'])) {
            try {
                SafeUrlValidator::validate($validated['endpoint_url'], app()->isLocal());
            } catch (Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'SSRF Validation Error: ' . $e->getMessage(),
                ], 422);
            }
        }

        $integration = $website->integration;

        if (!$integration) {
            $defaults = WebsiteIntegration::createDefaultCredentials();
            $integration = WebsiteIntegration::create(array_merge($validated, [
                'website_id'             => $website->id,
                'api_key'                => $defaults['api_key'],
                'api_secret'             => $defaults['api_secret'],
                'webhook_secret'         => $defaults['webhook_secret'],
                'verification_token'     => $defaults['verification_token'],
                'cms_status'             => WebsiteIntegration::CMS_STATUS_DISCONNECTED,
                'analytics_status'       => !empty($validated['ga4_property_id']) ? WebsiteIntegration::ANALYTICS_STATUS_CONNECTED : WebsiteIntegration::ANALYTICS_STATUS_NOT_CONFIGURED,
                'is_active'              => $request->boolean('is_active', true),
                'sync_frequency_minutes' => $validated['sync_frequency_minutes'] ?? 60,
                'created_by'             => auth()->id(),
            ]));
        } else {
            $updateData = [
                'connector_type'         => $validated['connector_type'],
                'endpoint_url'           => $validated['endpoint_url'] ?? null,
                'hostinger_account'      => $validated['hostinger_account'] ?? null,
                'ga4_property_id'        => $validated['ga4_property_id'] ?? null,
                'sync_frequency_minutes' => $validated['sync_frequency_minutes'] ?? 60,
                'is_active'              => $request->boolean('is_active', true),
                'updated_by'             => auth()->id(),
            ];

            if (!empty($validated['ga4_credentials_json'])) {
                $updateData['ga4_credentials_json'] = $validated['ga4_credentials_json'];
                $updateData['analytics_status'] = WebsiteIntegration::ANALYTICS_STATUS_CONNECTED;
            }

            $integration->update($updateData);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success'     => true,
                'message'     => 'Integration settings saved successfully.',
                'integration' => $integration->fresh(),
            ]);
        }

        return back()->with('success', 'Integration settings saved successfully.');
    }

    // ─── 6. TEST CONNECTION ───────────────────────────────────────────────────

    public function testConnection(Request $request, Website $website): JsonResponse
    {
        abort_unless(auth()->user()?->canAccessCentralConnectors(), 403, 'Access denied. The Central Connector platform is restricted to username "dara" and superadmin.');

        $integration = $website->integration;
        if (!$integration) {
            return response()->json(['success' => false, 'message' => 'No integration configured for this website.'], 404);
        }

        $url = $integration->endpoint_url ?: $website->url;
        if (empty($url)) {
            return response()->json(['success' => false, 'message' => 'No URL or endpoint configured for this website.'], 422);
        }

        try {
            $validatedUrl = SafeUrlValidator::validate($url, app()->isLocal());
            $startTime = microtime(true);

            $target = rtrim($validatedUrl, '/');
            if ($integration->connector_type === WebsiteIntegration::CONNECTOR_WP_PLUGIN) {
                $target .= '/wp-json/dgt/v1/health';
            } elseif ($integration->connector_type === WebsiteIntegration::CONNECTOR_WP_REST) {
                $target .= '/wp-json/wp/v2';
            }

            $response = Http::timeout(10)->connectTimeout(5)->get($target);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $integration->update([
                    'cms_status'   => WebsiteIntegration::CMS_STATUS_CONNECTED,
                    'last_ping_at' => now(),
                ]);

                return response()->json([
                    'success'    => true,
                    'message'    => "Successfully connected to {$website->name} (HTTP {$response->status()}). Latency: {$latencyMs}ms",
                    'latency_ms' => $latencyMs,
                    'status'     => 'connected',
                ]);
            }

            return response()->json([
                'success'    => false,
                'message'    => "Connection returned HTTP {$response->status()}: " . substr($response->body(), 0, 150),
                'latency_ms' => $latencyMs,
                'status'     => 'error',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'status'  => 'error',
            ], 500);
        }
    }

    // ─── 7. SYNCHRONIZE NOW ───────────────────────────────────────────────────

    public function syncNow(Request $request, Website $website): JsonResponse|RedirectResponse
    {
        abort_unless(auth()->user()?->canAccessCentralConnectors(), 403, 'Access denied. The Central Connector platform is restricted to username "dara" and superadmin.');

        $integration = $website->integration;
        if (!$integration) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Integration is not configured.'], 404);
            }
            return back()->with('error', 'Integration is not configured.');
        }

        try {
            // If run synchronous option or queue
            if ($request->boolean('sync_direct', false)) {
                $result = $this->syncService->pullPostsFromRemote($website, 'manual', auth()->id());
                return response()->json($result);
            }

            SyncWebsitePostsJob::dispatch($website, 'manual_dashboard', auth()->id());

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Synchronization job queued for {$website->name}.",
                ]);
            }

            return back()->with('success', "Synchronization job dispatched for {$website->name}.");
        } catch (Throwable $e) {
            Log::error("Manual sync failed: {$e->getMessage()}");
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    // ─── 8. ROTATE CREDENTIALS ────────────────────────────────────────────────

    public function rotateCredentials(Request $request, Website $website): JsonResponse
    {
        abort_unless(auth()->user()?->canAccessCentralConnectors(), 403, 'Access denied. The Central Connector platform is restricted to username "dara" and superadmin.');

        $integration = $website->integration;
        if (!$integration) {
            return response()->json(['success' => false, 'message' => 'Integration does not exist.'], 404);
        }

        $newKeys = $integration->rotateCredentials();

        ActivityLog::record(
            auth()->user(),
            'rotated_website_credential',
            "Rotated integration API credentials for website: {$website->name}",
            $website
        );

        return response()->json([
            'success'            => true,
            'message'            => 'Credentials rotated successfully. Copy the secret now; it will not be displayed in plaintext again.',
            'api_key'            => $newKeys['api_key'],
            'api_secret'         => $newKeys['api_secret'],
            'webhook_secret'     => $newKeys['webhook_secret'],
            'verification_token' => $newKeys['verification_token'],
        ]);
    }

    // ─── 9. DISCONNECT / DEACTIVATE ───────────────────────────────────────────

    public function disconnect(Request $request, Website $website): JsonResponse|RedirectResponse
    {
        abort_unless(auth()->user()?->canAccessCentralConnectors(), 403, 'Access denied. The Central Connector platform is restricted to username "dara" and superadmin.');

        $integration = $website->integration;
        if ($integration) {
            $integration->update([
                'is_active'   => false,
                'cms_status'  => WebsiteIntegration::CMS_STATUS_DISCONNECTED,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Integration disconnected.']);
        }

        return back()->with('success', 'Integration disconnected.');
    }

    // ─── 10. UNSYNC WEBSITE BLOGS & DISCONNECT ────────────────────────────────

    public function unsync(Request $request, Website $website): JsonResponse|RedirectResponse
    {
        abort_unless(auth()->user()?->canAccessCentralConnectors(), 403, 'Access denied. The Central Connector platform is restricted to username "dara" and superadmin.');

        $integration = $website->integration;
        if ($integration) {
            $integration->update([
                'is_active'        => false,
                'cms_status'       => WebsiteIntegration::CMS_STATUS_DISCONNECTED,
                'last_sync_status' => 'unsynced',
            ]);
        }

        // Delete synchronized posts for this website if requested (default true)
        $postsDeleted = 0;
        if ($request->boolean('clear_posts', true)) {
            $postsDeleted = WebsitePost::where('website_id', $website->id)->delete();
        }

        ActivityLog::record(
            auth()->user(),
            'unsynced_website',
            "Unsynced website {$website->name} from blog synchronization (cleared {$postsDeleted} posts)",
            $website
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => "Successfully unsynced {$website->name}. Blog sync is disconnected" . ($postsDeleted > 0 ? " and {$postsDeleted} synced posts were cleared." : "."),
                'website_id'    => $website->id,
                'posts_deleted' => $postsDeleted,
            ]);
        }

        return back()->with('success', "Successfully unsynced {$website->name}.");
    }

    // ─── 11. GET SYNC LOGS ────────────────────────────────────────────────────

    public function getLogs(Request $request, Website $website): JsonResponse
    {
        abort_unless(auth()->user()?->canAccessCentralConnectors(), 403, 'Access denied. The Central Connector platform is restricted to username "dara" and superadmin.');

        $logs = WebsiteSyncLog::where('website_id', $website->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'logs'    => $logs,
        ]);
    }
}
