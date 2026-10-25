<?php

namespace App\Services;

use App\Models\Website;
use App\Models\WebsiteIntegration;
use App\Models\WebsitePost;
use App\Models\WebsitePostAnalyticsSnapshot;
use App\Models\WebsiteSyncLog;
use Exception;
use Google\Client as GoogleClient;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\DateRange;
use Google\Service\AnalyticsData\Dimension;
use Google\Service\AnalyticsData\Metric;
use Google\Service\AnalyticsData\RunReportRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class Ga4AnalyticsSyncService
{
    /**
     * Synchronize GA4 metrics for a specific website.
     *
     * @param Website $website
     * @param int $days Number of past days to query (default 30)
     * @param int|null $userId
     * @return array
     */
    public function syncWebsite(Website $website, int $days = 30, ?int $userId = null): array
    {
        $startTime = microtime(true);
        $integration = $website->integration;

        if (!$integration || empty($integration->ga4_property_id)) {
            return [
                'success' => false,
                'message' => 'GA4 Property ID is not configured for this website.',
            ];
        }

        try {
            $client = $this->buildGoogleClient($integration);
            $analyticsData = new AnalyticsData($client);

            $propertyId = preg_replace('/[^0-9]/', '', $integration->ga4_property_id);
            $startDate = Carbon::now()->subDays($days)->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');

            $dateRange = new DateRange();
            $dateRange->setStartDate($startDate);
            $dateRange->setEndDate($endDate);

            $pagePathDim = new Dimension();
            $pagePathDim->setName('pagePath');

            $dateDim = new Dimension();
            $dateDim->setName('date');

            $viewsMetric = new Metric();
            $viewsMetric->setName('screenPageViews');

            $usersMetric = new Metric();
            $usersMetric->setName('activeUsers');

            $reportRequest = new RunReportRequest();
            $reportRequest->setProperty('properties/' . $propertyId);
            $reportRequest->setDateRanges([$dateRange]);
            $reportRequest->setDimensions([$pagePathDim, $dateDim]);
            $reportRequest->setMetrics([$viewsMetric, $usersMetric]);
            $reportRequest->setLimit(10000);

            $response = $analyticsData->properties->runReport('properties/' . $propertyId, $reportRequest);

            $posts = WebsitePost::where('website_id', $website->id)->get();
            $postsByPath = [];
            foreach ($posts as $p) {
                $path = parse_url($p->canonical_url, PHP_URL_PATH) ?: '/';
                $path = '/' . trim($path, '/');
                $postsByPath[$path] = $p;
            }

            $matchedPosts = [];
            $rows = $response->getRows() ?: [];
            $recordsUpdated = 0;

            foreach ($rows as $row) {
                $dimValues = $row->getDimensionValues();
                $metricValues = $row->getMetricValues();

                $pagePath = '/' . trim($dimValues[0]->getValue(), '/');
                $dateStr = $dimValues[1]->getValue(); // YYYYMMDD
                $formattedDate = Carbon::createFromFormat('Ymd', $dateStr)->format('Y-m-d');

                $pageViews = (int) ($metricValues[0]->getValue() ?? 0);
                $uniqueVisitors = (int) ($metricValues[1]->getValue() ?? 0);

                if (isset($postsByPath[$pagePath])) {
                    $post = $postsByPath[$pagePath];
                    $matchedPosts[$post->id] = true;

                    // Upsert daily snapshot
                    WebsitePostAnalyticsSnapshot::updateOrCreate(
                        [
                            'website_id'    => $website->id,
                            'post_id'       => $post->id,
                            'snapshot_date' => $formattedDate,
                            'source'        => WebsitePost::SOURCE_GA4,
                        ],
                        [
                            'views'           => $pageViews,
                            'page_views'      => $pageViews,
                            'unique_visitors' => $uniqueVisitors,
                        ]
                    );
                    $recordsUpdated++;
                }
            }

            // Recalculate totals for matched posts
            foreach (array_keys($matchedPosts) as $postId) {
                $post = WebsitePost::find($postId);
                if ($post) {
                    $totalPageViews = WebsitePostAnalyticsSnapshot::where('post_id', $postId)
                        ->where('source', WebsitePost::SOURCE_GA4)
                        ->sum('page_views');

                    $totalVisitors = WebsitePostAnalyticsSnapshot::where('post_id', $postId)
                        ->where('source', WebsitePost::SOURCE_GA4)
                        ->sum('unique_visitors');

                    $periodViews = WebsitePostAnalyticsSnapshot::where('post_id', $postId)
                        ->where('source', WebsitePost::SOURCE_GA4)
                        ->where('snapshot_date', '>=', Carbon::now()->subDays(30)->toDateString())
                        ->sum('page_views');

                    $post->update([
                        'page_views'        => $totalPageViews,
                        'unique_visitors'   => $totalVisitors,
                        'period_views'      => $periodViews,
                        'analytics_source'  => WebsitePost::SOURCE_GA4,
                        'data_completeness' => 'complete',
                    ]);
                }
            }

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $integration->update([
                'analytics_status' => WebsiteIntegration::ANALYTICS_STATUS_CONNECTED,
            ]);

            WebsiteSyncLog::create([
                'website_id'      => $website->id,
                'sync_type'       => 'ga4_sync',
                'status'          => WebsiteSyncLog::STATUS_SUCCESS,
                'items_synced'    => count($matchedPosts),
                'items_updated'   => $recordsUpdated,
                'duration_ms'     => $durationMs,
                'triggered_by'    => 'manual',
                'user_id'         => $userId,
                'payload_summary' => [
                    'property_id'   => $propertyId,
                    'rows_analyzed' => count($rows),
                    'posts_matched' => count($matchedPosts),
                ],
            ]);

            return [
                'success'       => true,
                'posts_matched' => count($matchedPosts),
                'rows_synced'   => $recordsUpdated,
                'duration_ms'   => $durationMs,
            ];

        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            Log::error("GA4 Sync Error for site {$website->id}: {$e->getMessage()}");

            $integration->update([
                'analytics_status' => WebsiteIntegration::ANALYTICS_STATUS_ERROR,
            ]);

            WebsiteSyncLog::create([
                'website_id'    => $website->id,
                'sync_type'     => 'ga4_sync',
                'status'        => WebsiteSyncLog::STATUS_FAILED,
                'error_message' => "GA4 Error: " . $e->getMessage(),
                'duration_ms'   => $durationMs,
                'triggered_by'  => 'manual',
                'user_id'       => $userId,
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Build Google API Client using service account credentials.
     */
    private function buildGoogleClient(WebsiteIntegration $integration): GoogleClient
    {
        $client = new GoogleClient();
        $client->setApplicationName('DGT System GA4 Connector');
        $client->setScopes([AnalyticsData::ANALYTICS_READONLY]);

        if (!empty($integration->ga4_credentials_json)) {
            $creds = json_decode($integration->ga4_credentials_json, true);
            if (is_array($creds)) {
                $client->setAuthConfig($creds);
                return $client;
            }
        }

        // Fallback to default google service account path if configured
        $defaultKeyPath = config('services.google.service_account_path', storage_path('app/google-service-account.json'));
        if (file_exists($defaultKeyPath)) {
            $client->setAuthConfig($defaultKeyPath);
            return $client;
        }

        throw new Exception("No valid Google Analytics service account credentials found for this website.");
    }
}
