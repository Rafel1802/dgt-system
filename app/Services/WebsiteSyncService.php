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
                $analyticsSource = $item['analytics_source'] ?? WebsitePost::SOURCE_NONE;

                $syncHash = WebsitePost::computeSyncHash([
                    'title'            => $item['title'],
                    'canonical_url'    => $canonicalUrl,
                    'status'           => $item['status'] ?? WebsitePost::STATUS_PUBLISHED,
                    'published_at'     => $publishedAt->toIso8601String(),
                    'cumulative_views' => $cumulativeViews,
                    'period_views'     => $periodViews,
                    'page_views'       => $pageViews,
                ]);

                // Check existing post for hash match to skip redundant DB writes
                $existing = WebsitePost::where('website_id', $website->id)
                    ->where('external_id', $externalId)
                    ->first();

                if ($existing && $existing->sync_hash === $syncHash) {
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
        $source = WebsitePost::SOURCE_NONE;

        if (isset($wp['post_views'])) {
            $views = (int) $wp['post_views'];
            $source = WebsitePost::SOURCE_CUSTOM_COUNTER;
        } elseif (isset($wp['meta']['views'])) {
            $views = (int) $wp['meta']['views'];
            $source = WebsitePost::SOURCE_WP_POSTVIEWS;
        } elseif (isset($wp['meta']['_jetpack_post_views'])) {
            $views = (int) $wp['meta']['_jetpack_post_views'];
            $source = WebsitePost::SOURCE_JETPACK;
        } elseif (isset($wp['meta']['post_views_count'])) {
            $views = (int) $wp['meta']['post_views_count'];
            $source = WebsitePost::SOURCE_POST_VIEWS_COUNTER;
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
}
