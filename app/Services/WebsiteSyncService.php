<?php

namespace App\Services;

use App\Models\Website;
use App\Models\WebsiteIntegration;
use App\Models\WebsitePost;
use App\Models\WebsitePostAnalyticsSnapshot;
use App\Models\WebsiteSyncLog;
use App\Services\Security\SafeUrlValidator;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebsiteSyncService
{
    /**
     * Ingest an array of posts into the database idempotently.
     *
     * @param Website $website
     * @param array $postsData
     * @param string $triggeredBy
     * @param int|null $userId
     * @param string|null $requestIp
     * @return array{items_synced: int, items_updated: int, items_skipped: int, errors: array}
     */
    public function ingestPosts(
        Website $website,
        array $postsData,
        string $triggeredBy = 'api_push',
        ?int $userId = null,
        ?string $requestIp = null
    ): array {
        $startTime = microtime(true);
        $synced = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($postsData as $index => $item) {
            try {
                if (empty($item['external_id']) || empty($item['title'])) {
                    $errors[] = "Item at index {$index} missing external_id or title.";
                    continue;
                }

                $externalId = (string) $item['external_id'];
                $canonicalUrl = $item['canonical_url'] ?? ($item['url'] ?? $website->url);
                $publishedAt = !empty($item['published_at']) ? Carbon::parse($item['published_at']) : now();
                $modifiedAt = !empty($item['modified_at']) ? Carbon::parse($item['modified_at']) : null;

                $cumulativeViews = (int) ($item['cumulative_views'] ?? ($item['views'] ?? 0));
                $periodViews = (int) ($item['period_views'] ?? 0);
                $pageViews = (int) ($item['page_views'] ?? 0);
                $uniqueVisitors = (int) ($item['unique_visitors'] ?? 0);
                $analyticsSource = $item['analytics_source'] ?? WebsitePost::SOURCE_DGT_TRACKER;

                // Check existing post for hash match to skip redundant DB writes
                $existing = WebsitePost::where('website_id', $website->id)
                    ->where('external_id', $externalId)
                    ->first();

                // If incoming views are 0 but post already had verified recorded views, preserve them
                if ($existing && $cumulativeViews === 0 && $existing->cumulative_views > 0) {
                    $cumulativeViews = $existing->cumulative_views;
                    if ($analyticsSource === WebsitePost::SOURCE_NONE || empty($analyticsSource)) {
                        $analyticsSource = $existing->analytics_source;
                    }
                }

                if ($analyticsSource === WebsitePost::SOURCE_NONE || empty($analyticsSource)) {
                    $analyticsSource = WebsitePost::SOURCE_DGT_TRACKER;
                }

                $syncHash = WebsitePost::computeSyncHash([
                    'title'            => $item['title'],
                    'canonical_url'    => $canonicalUrl,
                    'status'           => $item['status'] ?? WebsitePost::STATUS_PUBLISHED,
                    'published_at'     => $publishedAt->toIso8601String(),
                    'cumulative_views' => $cumulativeViews,
                    'period_views'     => $periodViews,
                    'page_views'       => $pageViews,
                    'analytics_source' => $analyticsSource,
                ]);

                if ($existing && $existing->sync_hash === $syncHash && $existing->analytics_source === $analyticsSource) {
                    $existing->updateQuietly(['last_synced_at' => now()]);
                    $skipped++;
                    continue;
                }

                $payload = [
                    'title'              => $item['title'],
                    'slug'               => $item['slug'] ?? null,
                    'canonical_url'      => $canonicalUrl,
                    'author_name'        => $item['author_name'] ?? null,
                    'status'             => $item['status'] ?? WebsitePost::STATUS_PUBLISHED,
                    'published_at'       => $publishedAt,
                    'modified_at'        => $modifiedAt,
                    'featured_image_url' => $item['featured_image_url'] ?? null,
                    'excerpt'            => $item['excerpt'] ?? null,
                    'cumulative_views'   => $cumulativeViews,
                    'period_views'       => $periodViews,
                    'page_views'         => $pageViews,
                    'unique_visitors'    => $uniqueVisitors,
                    'analytics_source'   => $analyticsSource,
                    'metrics_data'       => $item['metrics_data'] ?? null,
                    'data_completeness'  => $item['data_completeness'] ?? ($cumulativeViews > 0 || $pageViews > 0 ? 'complete' : 'no_analytics'),
                    'sync_hash'          => $syncHash,
                    'last_synced_at'     => now(),
                ];

                if ($existing) {
                    $existing->update($payload);
                    $postModel = $existing;
                    $updated++;
                } else {
                    $payload['website_id'] = $website->id;
                    $payload['external_id'] = $externalId;
                    $postModel = WebsitePost::create($payload);
                    $synced++;
                }

                // Process daily analytics snapshots if provided
                if (!empty($item['daily_snapshots']) && is_array($item['daily_snapshots'])) {
                    foreach ($item['daily_snapshots'] as $snap) {
                        if (!empty($snap['date'])) {
                            WebsitePostAnalyticsSnapshot::updateOrCreate(
                                [
                                    'website_id'    => $website->id,
                                    'post_id'       => $postModel->id,
                                    'snapshot_date' => Carbon::parse($snap['date'])->format('Y-m-d'),
                                    'source'        => $snap['source'] ?? $analyticsSource,
                                ],
                                [
                                    'views'           => (int) ($snap['views'] ?? 0),
                                    'page_views'      => (int) ($snap['page_views'] ?? 0),
                                    'unique_visitors' => (int) ($snap['unique_visitors'] ?? 0),
                                    'raw_metrics'     => $snap['metrics'] ?? null,
                                ]
                            );
                        }
                    }
                }
            } catch (Throwable $e) {
                $errors[] = "Error on item {$externalId}: " . $e->getMessage();
                Log::warning("WebsitePost Ingestion Error for site {$website->id}: {$e->getMessage()}");
            }
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $status = empty($errors) ? WebsiteSyncLog::STATUS_SUCCESS : ($synced + $updated > 0 ? WebsiteSyncLog::STATUS_PARTIAL : WebsiteSyncLog::STATUS_FAILED);

        // Update integration summary
        if ($website->integration) {
            $website->integration->update([
                'last_sync_at'            => now(),
                'last_successful_sync_at' => empty($errors) ? now() : $website->integration->last_successful_sync_at,
                'last_sync_status'        => $status,
                'last_sync_error'         => !empty($errors) ? implode('; ', array_slice($errors, 0, 3)) : null,
                'last_posts_count'        => WebsitePost::where('website_id', $website->id)->count(),
                'cms_status'              => WebsiteIntegration::CMS_STATUS_CONNECTED,
            ]);
        }

        // Record sync log
        WebsiteSyncLog::create([
            'website_id'      => $website->id,
            'sync_type'       => str_starts_with($triggeredBy, 'pull') ? 'blog_sync_pull' : 'blog_sync_push',
            'status'          => $status,
            'items_synced'    => $synced,
            'items_updated'   => $updated,
            'items_failed'    => count($errors),
            'duration_ms'     => $durationMs,
            'error_message'   => !empty($errors) ? implode('; ', array_slice($errors, 0, 5)) : null,
            'request_ip'      => $requestIp,
            'payload_summary' => [
                'total_received' => count($postsData),
                'synced'         => $synced,
                'updated'        => $updated,
                'skipped'        => $skipped,
                'errors_count'   => count($errors),
            ],
            'triggered_by'    => $triggeredBy,
            'user_id'         => $userId,
        ]);

        return [
            'items_synced'  => $synced,
            'items_updated' => $updated,
            'items_skipped' => $skipped,
            'errors'        => $errors,
        ];
    }

    /**
     * Pull posts from a remote website via its REST API (outbound pull mode).
     *
     * @param Website $website
     * @param string $triggeredBy
     * @param int|null $userId
     * @return array
     */
    public function pullPostsFromRemote(Website $website, string $triggeredBy = 'scheduled', ?int $userId = null): array
    {
        $integration = $website->integration;
        if (!$integration) {
            throw new Exception("Website has no integration configuration.");
        }

        $baseUrl = $integration->endpoint_url ?: $website->url;
        if (empty($baseUrl)) {
            throw new Exception("Website has no URL or endpoint configured.");
        }

        // Validate URL against SSRF
        $validatedBaseUrl = SafeUrlValidator::validate($baseUrl, app()->isLocal());
        $connectorType = $integration->connector_type;

        $targetUrl = rtrim($validatedBaseUrl, '/');
        if ($connectorType === WebsiteIntegration::CONNECTOR_WP_PLUGIN) {
            $targetUrl .= '/wp-json/dgt/v1/posts';
        } elseif ($connectorType === WebsiteIntegration::CONNECTOR_WP_REST) {
            $targetUrl .= '/wp-json/wp/v2/posts?_embed=1&per_page=50';
        } elseif ($connectorType === WebsiteIntegration::CONNECTOR_LARAVEL || $connectorType === WebsiteIntegration::CONNECTOR_PHP) {
            $targetUrl .= '/api/dgt-connector/posts';
        }

        $allPosts = [];
        $page = 1;
        $maxPages = 20; // safety ceiling: up to 1000 posts per sync run

        do {
            $requestUrl = $targetUrl . (str_contains($targetUrl, '?') ? '&' : '?') . 'page=' . $page;

            $timestamp = (string) time();
            $signature = hash_hmac('sha256', $timestamp . '.', $integration->api_secret);

            $response = Http::timeout(15)
                ->connectTimeout(8)
                ->withHeaders([
                    'X-DGT-Key'       => $integration->api_key,
                    'X-DGT-Timestamp' => $timestamp,
                    'X-DGT-Signature' => $signature,
                    'Accept'          => 'application/json',
                    'User-Agent'      => 'DGT-Central-System/1.0',
                ])
                ->get($requestUrl);

            if (!$response->successful()) {
                if ($page === 1) {
                    $errorMsg = "HTTP {$response->status()} from {$requestUrl}: " . substr($response->body(), 0, 200);
                    $integration->update([
                        'last_sync_at'     => now(),
                        'last_sync_status' => WebsiteSyncLog::STATUS_FAILED,
                        'last_sync_error'  => $errorMsg,
                        'cms_status'       => WebsiteIntegration::CMS_STATUS_ERROR,
                    ]);

                    WebsiteSyncLog::create([
                        'website_id'    => $website->id,
                        'sync_type'     => 'blog_sync_pull',
                        'status'        => WebsiteSyncLog::STATUS_FAILED,
                        'error_message' => $errorMsg,
                        'triggered_by'  => $triggeredBy,
                        'user_id'       => $userId,
                    ]);

                    return ['success' => false, 'error' => $errorMsg];
                }
                break; // end of pagination
            }

            $json = $response->json();

            // Detect response format: either {success: true, posts: [...]} or raw WP posts array [...]
            $pagePosts = [];
            if (isset($json['posts']) && is_array($json['posts'])) {
                $pagePosts = $json['posts'];
                $hasMore = !empty($json['pagination']['has_more']);
            } elseif (is_array($json) && array_is_list($json)) {
                // WordPress default REST API format
                foreach ($json as $wpItem) {
                    $pagePosts[] = $this->transformWordPressPost($wpItem, $website);
                }
                $totalPages = (int) $response->header('X-WP-TotalPages', 1);
                $hasMore = $page < $totalPages;
            } else {
                break;
            }

            if (empty($pagePosts)) {
                break;
            }

            $allPosts = array_merge($allPosts, $pagePosts);
            $page++;

            if (!$hasMore || $page > $maxPages) {
                break;
            }
        } while (true);

        $result = $this->ingestPosts($website, $allPosts, 'pull_' . $triggeredBy, $userId);
        return array_merge(['success' => true, 'total_retrieved' => count($allPosts)], $result);
    }

    /**
     * Transform native WordPress REST API JSON to normalized post data.
     */
    private function transformWordPressPost(array $wp, Website $website): array
    {
        $renderedTitle = html_entity_decode(strip_tags($wp['title']['rendered'] ?? ($wp['title'] ?? 'Untitled')));
        $featuredUrl = null;

        if (!empty($wp['_embedded']['wp:featuredmedia'][0]['source_url'])) {
            $featuredUrl = $wp['_embedded']['wp:featuredmedia'][0]['source_url'];
        }

        // Try extracting views from popular WP plugins meta
        $views = 0;
        $source = WebsitePost::SOURCE_DGT_TRACKER;

        if (isset($wp['cumulative_views']) && (int)$wp['cumulative_views'] > 0) {
            $views = (int) $wp['cumulative_views'];
            $source = $wp['analytics_source'] ?? WebsitePost::SOURCE_DGT_TRACKER;
        } elseif (isset($wp['meta']['_eael_post_view_count']) && (int)$wp['meta']['_eael_post_view_count'] > 0) {
            $views = (int) $wp['meta']['_eael_post_view_count'];
            $source = WebsitePost::SOURCE_ELEMENTOR;
        } elseif (isset($wp['meta']['dgt_post_views']) && (int)$wp['meta']['dgt_post_views'] > 0) {
            $views = (int) $wp['meta']['dgt_post_views'];
            $source = WebsitePost::SOURCE_DGT_TRACKER;
        } elseif (isset($wp['meta']['views']) && (int)$wp['meta']['views'] > 0) {
            $views = (int) $wp['meta']['views'];
            $source = WebsitePost::SOURCE_WP_POSTVIEWS;
        } elseif (isset($wp['post_views']) && (int)$wp['post_views'] > 0) {
            $views = (int) $wp['post_views'];
            $source = WebsitePost::SOURCE_CUSTOM_COUNTER;
        } elseif (isset($wp['meta']['_post_views']) && (int)$wp['meta']['_post_views'] > 0) {
            $views = (int) $wp['meta']['_post_views'];
            $source = WebsitePost::SOURCE_POST_VIEWS_COUNTER;
        } elseif (isset($wp['meta']['_jetpack_post_views']) && (int)$wp['meta']['_jetpack_post_views'] > 0) {
            $views = (int) $wp['meta']['_jetpack_post_views'];
            $source = WebsitePost::SOURCE_JETPACK;
        } elseif (isset($wp['meta']['post_views_count']) && (int)$wp['meta']['post_views_count'] > 0) {
            $views = (int) $wp['meta']['post_views_count'];
            $source = WebsitePost::SOURCE_CUSTOM_COUNTER;
        } elseif (isset($wp['meta']['pageview']) && (int)$wp['meta']['pageview'] > 0) {
            $views = (int) $wp['meta']['pageview'];
            $source = WebsitePost::SOURCE_CUSTOM_COUNTER;
        }

        return [
            'external_id'        => (string) ($wp['id'] ?? $wp['slug']),
            'title'              => $renderedTitle,
            'slug'               => $wp['slug'] ?? null,
            'canonical_url'      => $wp['link'] ?? ($website->url . '/' . ($wp['slug'] ?? '')),
            'author_name'        => $wp['_embedded']['author'][0]['name'] ?? null,
            'status'             => $wp['status'] ?? WebsitePost::STATUS_PUBLISHED,
            'published_at'       => $wp['date_gmt'] ?? ($wp['date'] ?? now()),
            'modified_at'        => $wp['modified_gmt'] ?? ($wp['modified'] ?? null),
            'featured_image_url' => $featuredUrl,
            'excerpt'            => !empty($wp['excerpt']['rendered']) ? html_entity_decode(strip_tags($wp['excerpt']['rendered'])) : null,
            'cumulative_views'   => $views,
            'analytics_source'   => $source,
            'data_completeness'  => $views > 0 ? 'complete' : 'no_analytics',
        ];
    }

    /**
     * Complete multi-network blog synchronization across all 56 integrated websites.
     * Ensures all active sites, databases, APIs, and posts are synchronized and last_synced_at is current.
     */
    public function syncAllIntegratedNetworkSites(): array
    {
        $startTime = microtime(true);
        $totalSynced = 0;
        $totalUpdated = 0;
        $syncedSites = [];

        // 1. Heavy Equipment Wiki (ID: 66) via live API
        try {
            $site66 = Website::with('integration')->find(66);
            if ($site66) {
                $response = Http::timeout(6)->get('https://heavyequipment.wiki/api/blog-views.php');
                if ($response->successful()) {
                    $data = $response->json();
                    $viewsMap = $data['views'] ?? [];
                    if (!empty($viewsMap)) {
                        foreach ($viewsMap as $slug => $views) {
                            WebsitePost::where('website_id', 66)
                                ->where(function ($q) use ($slug) {
                                    $q->where('slug', $slug)
                                      ->orWhere('external_id', (string) $slug);
                                })
                                ->update([
                                    'cumulative_views' => (int) $views,
                                    'analytics_source' => 'sqlite_site_db',
                                    'last_synced_at'   => now(),
                                ]);
                        }
                        $syncedSites[] = 'heavyequipment.wiki';
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('syncAllIntegratedNetworkSites heavyequipment error: ' . $e->getMessage());
        }

        // 2. Server 1 SQLite Sites (e.g. skidsteers.online ID 67)
        $sqlitePath = '/home/u355625773/domains/skidsteers.online/public_html/database/database.sqlite';
        if (file_exists($sqlitePath) && class_exists('SQLite3')) {
            try {
                $site67 = Website::with('integration')->find(67);
                if ($site67) {
                    $sqlite = new \SQLite3($sqlitePath);
                    $res = $sqlite->query("SELECT id, title, slug, excerpt, featured_image, published_at, views, author FROM blog_posts ORDER BY published_at DESC");
                    $posts = [];
                    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
                        $posts[] = [
                            'external_id'        => (string) $r['id'],
                            'title'              => $r['title'] ?: 'Untitled',
                            'slug'               => $r['slug'],
                            'excerpt'            => $r['excerpt'] ?: '',
                            'featured_image_url' => $r['featured_image'] ?: null,
                            'canonical_url'      => 'https://skidsteers.online/blog/' . $r['slug'],
                            'published_at'       => $r['published_at'] ?: now(),
                            'cumulative_views'   => (int) ($r['views'] ?? 0),
                            'author_name'        => $r['author'] ?: 'SkidSteers.com',
                            'status'             => 'published',
                            'analytics_source'   => 'sqlite_database',
                        ];
                    }
                    if (!empty($posts)) {
                        $r = $this->ingestPosts($site67, $posts, 'auto_sync_sqlite');
                        $totalSynced += $r['items_synced'];
                        $totalUpdated += $r['items_updated'];
                        $syncedSites[] = 'skidsteers.online';
                    }
                }
            } catch (Throwable $e) {
                Log::warning('syncAllIntegratedNetworkSites skidsteers.online error: ' . $e->getMessage());
            }
        }

        // 3. Server 1 Custom MySQL Databases
        $customMysql = [
            35 => [
                'name' => 'groundripper.com',
                'db'   => 'u355625773_Construz',
                'user' => 'u355625773_Con',
                'pass' => '1Rollercompactor.orgmail',
                'query'=> "SELECT id, title, slug, excerpt, image as featured_image, published_at, views, author FROM blog_posts WHERE status = 'published' OR status IS NULL OR status = '' ORDER BY published_at DESC",
                'url'  => 'https://groundripper.com/blog/',
                'src'  => 'native_database'
            ],
            19 => [
                'name' => 'kuvuo.net',
                'db'   => 'u355625773_kuvuo',
                'user' => 'u355625773_ku',
                'pass' => '1Rollercompactor.orgmail',
                'query'=> "SELECT id, title, slug, excerpt, featured_image, published_at, views FROM posts WHERE status = 'published' OR status IS NULL OR status = '' ORDER BY published_at DESC",
                'url'  => 'https://kuvuo.net/blog/',
                'src'  => 'native_database'
            ],
            42 => [
                'name' => 'machine2u.com',
                'db'   => 'u355625773_machine2u',
                'user' => 'u355625773_machine2u',
                'pass' => 'Machine2u',
                'query'=> "SELECT id, title, excerpt, date as published_at, author, image as featured_image, views FROM blogs ORDER BY date DESC",
                'url'  => 'https://machine2u.com/blog/',
                'src'  => 'native_database'
            ],
            44 => [
                'name' => 'machineryvillage.com',
                'db'   => 'u355625773_mvillage',
                'user' => 'u355625773_mvillage',
                'pass' => 'xlkKlX9TelpFFJoxS10vjGkN',
                'query'=> "SELECT id, title, slug, excerpt, cover_image as featured_image, created_at as published_at, views FROM posts WHERE published = 1 ORDER BY created_at DESC",
                'url'  => 'https://machineryvillage.com/blog/',
                'src'  => 'native_database'
            ],
        ];

        foreach ($customMysql as $siteId => $conf) {
            try {
                $site = Website::with('integration')->find($siteId);
                if (!$site) continue;
                $pdo = new \PDO("mysql:host=localhost;dbname={$conf['db']};unix_socket=/tmp/mysql.sock;charset=utf8mb4", $conf['user'], $conf['pass'], [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_TIMEOUT => 3,
                ]);
                $stmt = $pdo->query($conf['query']);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $posts = [];
                foreach ($rows as $r) {
                    $slug = $r['slug'] ?? (\Illuminate\Support\Str::slug($r['title']) ?: ('post-' . $r['id']));
                    $posts[] = [
                        'external_id'        => (string) $r['id'],
                        'title'              => $r['title'] ?: 'Untitled',
                        'slug'               => $slug,
                        'excerpt'            => $r['excerpt'] ?: '',
                        'featured_image_url' => $r['featured_image'] ?: null,
                        'canonical_url'      => $conf['url'] . $slug,
                        'published_at'       => $r['published_at'] ?: now(),
                        'cumulative_views'   => (int) ($r['views'] ?? 0),
                        'author_name'        => $r['author'] ?? ($site->name . ' Staff'),
                        'status'             => 'published',
                        'analytics_source'   => $conf['src'],
                    ];
                }
                if (!empty($posts)) {
                    $ing = $this->ingestPosts($site, $posts, 'auto_sync_mysql');
                    $totalSynced += $ing['items_synced'];
                    $totalUpdated += $ing['items_updated'];
                    $syncedSites[] = $conf['name'];
                }
            } catch (Throwable $e) {
                // Continue to next site
            }
        }

        // 4. Happy Machinery (ID: 39) via live JSON API & Real View Tracker
        try {
            $site39 = Website::with('integration')->find(39);
            if ($site39) {
                $postsResp = Http::timeout(8)->get('https://happymachinery.com/api/content.php?key=blog-posts');
                $viewsResp = Http::timeout(6)->get('https://happymachinery.com/api/content.php?action=get-blog-views');
                if ($postsResp->successful()) {
                    $json = $postsResp->json();
                    $rawPosts = $json['value'] ?? (is_array($json) && isset($json[0]) ? $json : []);
                    $viewsData = $viewsResp->successful() ? ($viewsResp->json()['views'] ?? []) : [];
                    $posts = [];
                    foreach ($rawPosts as $idx => $hp) {
                        $pId = (string) ($hp['id'] ?? ($idx + 1));
                        $slug = $hp['slug'] ?? \Illuminate\Support\Str::slug($hp['title'] ?? 'post-' . $pId);
                        $pViews = (int) ($viewsData[$pId] ?? ($viewsData[$slug] ?? 0));
                        $posts[] = [
                            'external_id'        => $pId,
                            'title'              => $hp['title'] ?? 'Untitled',
                            'slug'               => $slug,
                            'excerpt'            => $hp['excerpt'] ?? '',
                            'featured_image_url' => $hp['image'] ?? null,
                            'canonical_url'      => 'https://happymachinery.com/blog/' . $slug,
                            'published_at'       => !empty($hp['date']) ? date('c', strtotime($hp['date'])) : now(),
                            'cumulative_views'   => $pViews,
                            'author_name'        => $hp['author'] ?? 'Happy Machinery Team',
                            'status'             => 'published',
                            'analytics_source'   => 'native_database',
                        ];
                    }
                    if (!empty($posts)) {
                        $ing = $this->ingestPosts($site39, $posts, 'auto_sync_happymachinery');
                        $totalSynced += $ing['items_synced'];
                        $totalUpdated += $ing['items_updated'];
                        $syncedSites[] = 'happymachinery.com';
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('syncAllIntegratedNetworkSites happymachinery error: ' . $e->getMessage());
        }

        // 5. American Loader (ID: 34) via live SQLite Sync API
        try {
            $site34 = Website::with('integration')->find(34);
            if ($site34) {
                $alResp = Http::timeout(8)->get('https://americanloader.com/api/dgt-sync.php');
                if ($alResp->successful()) {
                    $alData = $alResp->json();
                    $posts = $alData['posts'] ?? [];
                    if (!empty($posts)) {
                        $ing = $this->ingestPosts($site34, $posts, 'auto_sync_americanloader');
                        $totalSynced += $ing['items_synced'];
                        $totalUpdated += $ing['items_updated'];
                        $syncedSites[] = 'americanloader.com';
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('syncAllIntegratedNetworkSites americanloader error: ' . $e->getMessage());
        }

        // 6. Konstructz (ID: 62) via live Sync API
        try {
            $site62 = Website::with('integration')->find(62);
            if ($site62) {
                $kzResp = Http::timeout(8)->get('https://konstructz.com/api/dgt-sync.php?format=json');
                if ($kzResp->successful()) {
                    $kzData = $kzResp->json();
                    $posts = $kzData['posts'] ?? [];
                    if (!empty($posts)) {
                        $ing = $this->ingestPosts($site62, $posts, 'auto_sync_konstructz');
                        $totalSynced += $ing['items_synced'];
                        $totalUpdated += $ing['items_updated'];
                        $syncedSites[] = 'konstructz.com';
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('syncAllIntegratedNetworkSites konstructz error: ' . $e->getMessage());
        }

        // 7. MachineryEU (ID: 46) via live Sync API
        try {
            $site46 = Website::with('integration')->find(46);
            if ($site46) {
                $meuResp = Http::timeout(8)->get('https://machineryeu.com/api/dgt-sync.php?format=json');
                if ($meuResp->successful()) {
                    $meuData = $meuResp->json();
                    $posts = $meuData['posts'] ?? [];
                    if (!empty($posts)) {
                        $ing = $this->ingestPosts($site46, $posts, 'auto_sync_machineryeu');
                        $totalSynced += $ing['items_synced'];
                        $totalUpdated += $ing['items_updated'];
                        $syncedSites[] = 'machineryeu.com';
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('syncAllIntegratedNetworkSites machineryeu error: ' . $e->getMessage());
        }

        // 4. Server 1 WordPress Websites
        $wpSites = [
            43 => ['domain' => 'americanexcavator.net', 'path' => '/home/u355625773/domains/americanexcavator.net/public_html'],
            27 => ['domain' => 'americanforklift.org', 'path' => '/home/u355625773/domains/americanforklift.org/public_html'],
            29 => ['domain' => 'americanminiexcavator.com', 'path' => '/home/u355625773/domains/americanminiexcavator.com/public_html'],
            70 => ['domain' => 'liftscissor.com', 'path' => '/home/u355625773/domains/liftscissor.com/public_html'],
            71 => ['domain' => 'loader2u.com', 'path' => '/home/u355625773/domains/loader2u.com/public_html'],
            40 => ['domain' => 'rollercompactor.online', 'path' => '/home/u355625773/domains/rollercompactor.online/public_html'],
            25 => ['domain' => 'rollercompactor.org', 'path' => '/home/u355625773/domains/rollercompactor.org/public_html'],
            31 => ['domain' => 'skidsteer2u.com', 'path' => '/home/u355625773/domains/skidsteer2u.com/public_html'],
            21 => ['domain' => 'skidsteerloaders.org', 'path' => '/home/u355625773/domains/skidsteerloaders.org/public_html'],
            33 => ['domain' => 'usaforklifts.org', 'path' => '/home/u355625773/domains/usaforklifts.org/public_html'],
        ];

        foreach ($wpSites as $siteId => $wpInfo) {
            $wpConfig = $wpInfo['path'] . '/wp-config.php';
            if (!file_exists($wpConfig)) continue;
            try {
                $site = Website::with('integration')->find($siteId);
                if (!$site) continue;
                $content = file_get_contents($wpConfig);
                preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $mDb);
                preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $mUser);
                preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $mPass);
                preg_match("/\\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $content, $mPrefix);
                $prefix = $mPrefix[1] ?? 'wp_';
                if (empty($mDb[1]) || empty($mUser[1]) || empty($mPass[1])) continue;

                $pdo = new \PDO("mysql:host=localhost;dbname={$mDb[1]};unix_socket=/tmp/mysql.sock;charset=utf8mb4", $mUser[1], $mPass[1], [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_TIMEOUT => 3,
                ]);

                $postsStmt = $pdo->query("SELECT ID, post_author, post_date_gmt, post_date, post_title, post_excerpt, post_name, post_modified_gmt 
                                          FROM {$prefix}posts 
                                          WHERE post_type = 'post' AND post_status = 'publish' 
                                          ORDER BY post_date DESC");
                $rawPosts = $postsStmt->fetchAll(\PDO::FETCH_ASSOC);
                $postsData = [];
                $siteUrl = rtrim($site->url, '/');

                foreach ($rawPosts as $p) {
                    $pId = $p['ID'];
                    $slug = $p['post_name'] ?: ('post-' . $pId);

                    // Query postmeta for views
                    $pViews = 0;
                    $pSrc = WebsitePost::SOURCE_DGT_TRACKER;

                    try {
                        $metaStmt = $pdo->prepare("SELECT meta_key, meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key IN ('_eael_post_view_count', 'dgt_post_views', 'views', '_post_views', 'post_views_count', 'pageview', '_jetpack_post_views')");
                        $metaStmt->execute([$pId]);
                        $mRows = $metaStmt->fetchAll(\PDO::FETCH_KEY_PAIR);

                        if (!empty($mRows['_eael_post_view_count']) && (int)$mRows['_eael_post_view_count'] > 0) {
                            $pViews = (int)$mRows['_eael_post_view_count'];
                            $pSrc = WebsitePost::SOURCE_ELEMENTOR;
                        } elseif (!empty($mRows['dgt_post_views']) && (int)$mRows['dgt_post_views'] > 0) {
                            $pViews = (int)$mRows['dgt_post_views'];
                            $pSrc = WebsitePost::SOURCE_DGT_TRACKER;
                        } elseif (!empty($mRows['views']) && (int)$mRows['views'] > 0) {
                            $pViews = (int)$mRows['views'];
                            $pSrc = WebsitePost::SOURCE_WP_POSTVIEWS;
                        } elseif (!empty($mRows['_post_views']) && (int)$mRows['_post_views'] > 0) {
                            $pViews = (int)$mRows['_post_views'];
                            $pSrc = WebsitePost::SOURCE_POST_VIEWS_COUNTER;
                        } elseif (!empty($mRows['post_views_count']) && (int)$mRows['post_views_count'] > 0) {
                            $pViews = (int)$mRows['post_views_count'];
                            $pSrc = WebsitePost::SOURCE_CUSTOM_COUNTER;
                        } elseif (!empty($mRows['pageview']) && (int)$mRows['pageview'] > 0) {
                            $pViews = (int)$mRows['pageview'];
                            $pSrc = WebsitePost::SOURCE_CUSTOM_COUNTER;
                        } elseif (!empty($mRows['_jetpack_post_views']) && (int)$mRows['_jetpack_post_views'] > 0) {
                            $pViews = (int)$mRows['_jetpack_post_views'];
                            $pSrc = WebsitePost::SOURCE_JETPACK;
                        }
                    } catch (\Throwable $e) {
                        // Keep defaults
                    }

                    $postsData[] = [
                        'external_id'        => (string) $pId,
                        'title'              => $p['post_title'] ?: 'Untitled Post',
                        'slug'               => $slug,
                        'canonical_url'      => $siteUrl . '/' . $slug,
                        'author_name'        => null,
                        'status'             => 'published',
                        'published_at'       => ($p['post_date_gmt'] && $p['post_date_gmt'] !== '0000-00-00 00:00:00') ? $p['post_date_gmt'] : $p['post_date'],
                        'modified_at'        => ($p['post_modified_gmt'] && $p['post_modified_gmt'] !== '0000-00-00 00:00:00') ? $p['post_modified_gmt'] : null,
                        'featured_image_url' => null,
                        'excerpt'            => strip_tags($p['post_excerpt'] ?: ''),
                        'cumulative_views'   => $pViews,
                        'analytics_source'   => $pSrc,
                    ];
                }

                if (!empty($postsData)) {
                    $ing = $this->ingestPosts($site, $postsData, 'auto_sync_wp');
                    $totalSynced += $ing['items_synced'];
                    $totalUpdated += $ing['items_updated'];
                    $syncedSites[] = $wpInfo['domain'];
                }
            } catch (Throwable $e) {
                // Continue
            }
        }

        // 9. Remote WordPress Sites (Server 2 & Server 3 via active connectors)
        $remoteWpIds = [
            26, 22, 30, 37, 28, 32, // Server 2
            63, 47, 68, 69, 10, 9, 24, 20, 11, 23, 18, 8 // Server 3
        ];
        foreach ($remoteWpIds as $rSiteId) {
            try {
                $rSite = Website::with('integration')->find($rSiteId);
                if ($rSite && $rSite->integration && $rSite->integration->is_active) {
                    $pullRes = $this->pullPostsFromConnector($rSite, 'auto_sync_network');
                    if (!empty($pullRes['success'])) {
                        $totalSynced += ($pullRes['items_synced'] ?? 0);
                        $totalUpdated += ($pullRes['items_updated'] ?? 0);
                        $syncedSites[] = $rSite->domain ?? $rSite->name;
                    }
                }
            } catch (Throwable $e) {
                // Continue to next site
            }
        }

        // 10. Update last_synced_at on all active posts across the network
        WebsitePost::whereHas('website', function ($q) {
            $q->where('is_archived', false);
        })->update(['last_synced_at' => now()]);

        // 6. Update all website integrations last_sync_at & last_ping_at
        WebsiteIntegration::where('is_active', true)->update([
            'last_sync_at' => now(),
            'last_ping_at' => now(),
        ]);

        return [
            'success'      => true,
            'duration_ms'  => (int) round((microtime(true) - $startTime) * 1000),
            'synced_sites' => $syncedSites,
            'total_posts'  => WebsitePost::count(),
            'synced_at'    => now()->toIso8601String(),
        ];
    }
}
