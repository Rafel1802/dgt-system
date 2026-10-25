<?php

namespace App\Services\GoogleSearchConsole;

use App\Models\GoogleSearchConsole\GoogleSearchConsoleDailyMetric;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleSearchQuery;
use App\Models\GoogleSearchConsole\GoogleSearchConsolePage;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleCountry;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleDevice;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleSite;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleSyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class GoogleSearchConsoleSyncService
{
    protected GoogleSearchConsoleClientService $clientService;

    public function __construct(GoogleSearchConsoleClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Synchronize a single mapped Search Console site property
     *
     * @param GoogleSearchConsoleSite $site
     * @param int $days Number of days back to sync (default: 30)
     * @param string $triggeredBy 'schedule', 'manual', or 'oauth'
     * @param int|null $userId User triggering the sync
     * @return array
     */
    public function syncSite(GoogleSearchConsoleSite $site, int $days = 30, string $triggeredBy = 'manual', ?int $userId = null): array
    {
        $log = GoogleSearchConsoleSyncLog::create([
            'website_id'   => $site->website_id,
            'site_url'     => $site->site_url,
            'started_at'   => now(),
            'status'       => 'running',
            'triggered_by' => $triggeredBy,
            'user_id'      => $userId,
        ]);

        $site->update([
            'sync_status' => 'syncing',
            'sync_error'  => null,
        ]);

        try {
            // Google data has 2-3 day lag; query up to yesterday or 2 days ago
            $endDate = now()->subDays(1)->format('Y-m-d');
            $startDate = now()->subDays($days)->format('Y-m-d');

            $log->update([
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ]);

            $totalRecords = 0;
            $latestDataDate = null;

            // 1. Sync Daily Metrics (dimensions: date)
            $dailyRows = $this->clientService->querySearchAnalytics(
                $site->site_url,
                $startDate,
                $endDate,
                ['date'],
                1000,
                0,
                'web',
                $site->account
            );

            if (!empty($dailyRows)) {
                $dailyUpserts = [];
                foreach ($dailyRows as $row) {
                    $date = $row['keys'][0];
                    if (!$latestDataDate || $date > $latestDataDate) {
                        $latestDataDate = $date;
                    }

                    $dailyUpserts[] = [
                        'website_id'   => $site->website_id,
                        'site_url'     => $site->site_url,
                        'data_date'    => $date,
                        'clicks'       => $row['clicks'],
                        'impressions'  => $row['impressions'],
                        'ctr'          => round($row['ctr'], 4),
                        'position'     => round($row['position'], 2),
                        'search_type'  => 'web',
                        'updated_at'   => now(),
                    ];
                }

                if (!empty($dailyUpserts)) {
                    GoogleSearchConsoleDailyMetric::upsert(
                        $dailyUpserts,
                        ['website_id', 'data_date', 'search_type'],
                        ['clicks', 'impressions', 'ctr', 'position', 'updated_at']
                    );
                    $totalRecords += count($dailyUpserts);
                }
            }

            // 2. Sync Search Keywords / Queries (dimensions: date, query)
            $queryRows = $this->clientService->querySearchAnalytics(
                $site->site_url,
                $startDate,
                $endDate,
                ['date', 'query'],
                5000,
                0,
                'web',
                $site->account
            );

            if (!empty($queryRows)) {
                $queryUpserts = [];
                foreach ($queryRows as $row) {
                    $date = $row['keys'][0];
                    $queryText = $row['keys'][1] ?? '';
                    if (empty($queryText)) continue;

                    $queryUpserts[] = [
                        'website_id'   => $site->website_id,
                        'site_url'     => $site->site_url,
                        'data_date'    => $date,
                        'query'        => $queryText,
                        'query_hash'   => sha1($queryText),
                        'clicks'       => $row['clicks'],
                        'impressions'  => $row['impressions'],
                        'ctr'          => round($row['ctr'], 4),
                        'position'     => round($row['position'], 2),
                        'updated_at'   => now(),
                    ];
                }

                // Chunk upserts in batches of 500 to prevent SQL placeholder limits
                foreach (array_chunk($queryUpserts, 500) as $chunk) {
                    GoogleSearchConsoleSearchQuery::upsert(
                        $chunk,
                        ['website_id', 'data_date', 'query_hash'],
                        ['clicks', 'impressions', 'ctr', 'position', 'updated_at']
                    );
                }
                $totalRecords += count($queryUpserts);
            }

            // 3. Sync Top Pages (dimensions: date, page)
            $pageRows = $this->clientService->querySearchAnalytics(
                $site->site_url,
                $startDate,
                $endDate,
                ['date', 'page'],
                2000,
                0,
                'web',
                $site->account
            );

            if (!empty($pageRows)) {
                $pageUpserts = [];
                foreach ($pageRows as $row) {
                    $date = $row['keys'][0];
                    $pageUrl = $row['keys'][1] ?? '';
                    if (empty($pageUrl)) continue;

                    $pageUpserts[] = [
                        'website_id'   => $site->website_id,
                        'site_url'     => $site->site_url,
                        'data_date'    => $date,
                        'page'         => $pageUrl,
                        'page_hash'    => sha1($pageUrl),
                        'clicks'       => $row['clicks'],
                        'impressions'  => $row['impressions'],
                        'ctr'          => round($row['ctr'], 4),
                        'position'     => round($row['position'], 2),
                        'updated_at'   => now(),
                    ];
                }

                foreach (array_chunk($pageUpserts, 500) as $chunk) {
                    GoogleSearchConsolePage::upsert(
                        $chunk,
                        ['website_id', 'data_date', 'page_hash'],
                        ['clicks', 'impressions', 'ctr', 'position', 'updated_at']
                    );
                }
                $totalRecords += count($pageUpserts);
            }

            // 4. Sync Countries (dimensions: date, country)
            $countryRows = $this->clientService->querySearchAnalytics(
                $site->site_url,
                $startDate,
                $endDate,
                ['date', 'country'],
                1000,
                0,
                'web',
                $site->account
            );

            if (!empty($countryRows)) {
                $countryUpserts = [];
                foreach ($countryRows as $row) {
                    $date = $row['keys'][0];
                    $country = strtoupper(substr($row['keys'][1] ?? 'UNKNOWN', 0, 10));

                    $countryUpserts[] = [
                        'website_id'   => $site->website_id,
                        'site_url'     => $site->site_url,
                        'data_date'    => $date,
                        'country'      => $country,
                        'clicks'       => $row['clicks'],
                        'impressions'  => $row['impressions'],
                        'ctr'          => round($row['ctr'], 4),
                        'position'     => round($row['position'], 2),
                        'updated_at'   => now(),
                    ];
                }

                foreach (array_chunk($countryUpserts, 500) as $chunk) {
                    GoogleSearchConsoleCountry::upsert(
                        $chunk,
                        ['website_id', 'data_date', 'country'],
                        ['clicks', 'impressions', 'ctr', 'position', 'updated_at']
                    );
                }
                $totalRecords += count($countryUpserts);
            }

            // 5. Sync Devices (dimensions: date, device)
            $deviceRows = $this->clientService->querySearchAnalytics(
                $site->site_url,
                $startDate,
                $endDate,
                ['date', 'device'],
                100,
                0,
                'web',
                $site->account
            );

            if (!empty($deviceRows)) {
                $deviceUpserts = [];
                foreach ($deviceRows as $row) {
                    $date = $row['keys'][0];
                    $device = strtoupper(substr($row['keys'][1] ?? 'DESKTOP', 0, 30));

                    $deviceUpserts[] = [
                        'website_id'   => $site->website_id,
                        'site_url'     => $site->site_url,
                        'data_date'    => $date,
                        'device'       => $device,
                        'clicks'       => $row['clicks'],
                        'impressions'  => $row['impressions'],
                        'ctr'          => round($row['ctr'], 4),
                        'position'     => round($row['position'], 2),
                        'updated_at'   => now(),
                    ];
                }

                foreach (array_chunk($deviceUpserts, 500) as $chunk) {
                    GoogleSearchConsoleDevice::upsert(
                        $chunk,
                        ['website_id', 'data_date', 'device'],
                        ['clicks', 'impressions', 'ctr', 'position', 'updated_at']
                    );
                }
                $totalRecords += count($deviceUpserts);
            }

            // Update Site state
            $site->update([
                'last_synced_at' => now(),
                'last_data_date' => $latestDataDate ?: $site->last_data_date,
                'sync_status'    => 'success',
                'sync_error'     => null,
            ]);

            $log->update([
                'finished_at'    => now(),
                'status'         => 'success',
                'records_synced' => $totalRecords,
                'message'        => "Successfully synced {$totalRecords} records across {$days} days. Latest data date: " . ($latestDataDate ?: 'N/A'),
            ]);

            return [
                'success'        => true,
                'records_synced' => $totalRecords,
                'latest_date'    => $latestDataDate,
            ];

        } catch (Exception $e) {
            Log::error('GSC syncSite error', [
                'site_url' => $site->site_url,
                'error'    => $e->getMessage(),
            ]);

            $site->update([
                'sync_status' => 'failed',
                'sync_error'  => $e->getMessage(),
            ]);

            $log->update([
                'finished_at' => now(),
                'status'      => 'failed',
                'message'     => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Synchronize all enabled mapped sites
     */
    public function syncAll(int $days = 30, string $triggeredBy = 'schedule', ?int $userId = null): array
    {
        $sites = GoogleSearchConsoleSite::where('is_sync_enabled', true)
            ->whereHas('account', fn($q) => $q->where('is_active', true))
            ->get();

        $results = [
            'total_sites'   => $sites->count(),
            'successful'    => 0,
            'failed'        => 0,
            'total_records' => 0,
            'errors'        => [],
        ];

        foreach ($sites as $site) {
            $res = $this->syncSite($site, $days, $triggeredBy, $userId);
            if ($res['success']) {
                $results['successful']++;
                $results['total_records'] += $res['records_synced'];
            } else {
                $results['failed']++;
                $results['errors'][$site->site_url] = $res['error'];
            }
        }

        return $results;
    }
}
