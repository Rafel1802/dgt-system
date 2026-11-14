<?php

namespace App\Console\Commands;

use App\Models\GoogleSearchConsole\GoogleSearchConsoleSite;
use App\Models\GoogleSearchConsole\GoogleSearchConsoleUrlInspection;
use App\Models\Website;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleSitemapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoInspectGoogleSearchConsoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gsc:auto-inspect
                            {--website= : Specific DGT website ID to inspect}
                            {--limit=150 : Maximum URLs to inspect per website}
                            {--delay=120 : Delay in milliseconds between Google API requests}
                            {--force : Re-inspect URLs even if inspected recently}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically inspect URLs via Google Search Console URL Inspection API in the background';

    /**
     * Execute the console command.
     */
    public function handle(GoogleSearchConsoleSitemapService $sitemapService): int
    {
        $websiteId = $this->option('website');
        $limit = max(3, (int) $this->option('limit') ?: 25);
        $delayMs = max(20, (int) $this->option('delay') ?: 80);
        $force = (bool) $this->option('force');

        $activeAccount = \App\Models\GoogleSearchConsole\GoogleSearchConsoleAccount::where('is_active', true)->first();

        // Ensure all Websites with a domain have a GoogleSearchConsoleSite record so no website is skipped
        $websitesQuery = Website::query()->whereNotNull('url')->where('url', '!=', '');
        if ($websiteId) {
            $websitesQuery->where('id', $websiteId);
        }
        $websites = $websitesQuery->orderBy('id')->get();

        foreach ($websites as $ws) {
            $cleanDomain = $ws->clean_domain ?: preg_replace('#^(https?://)?(www\.)?#i', '', rtrim((string) $ws->url, '/'));
            $cleanDomain = strtolower(trim(preg_replace('#/.*$#', '', (string) $cleanDomain)));
            if (!$cleanDomain || str_contains($cleanDomain, '127.0.0.1') || str_contains($cleanDomain, 'localhost')) {
                continue;
            }

            $existingSite = GoogleSearchConsoleSite::where('website_id', $ws->id)->first();
            if (!$existingSite) {
                $existingSite = GoogleSearchConsoleSite::whereIn('site_url', \App\Http\Controllers\GoogleSearchConsoleController::exactGscPropertyCandidates($cleanDomain))->first();
                if ($existingSite && !$existingSite->website_id) {
                    $existingSite->update(['website_id' => $ws->id, 'is_sync_enabled' => true]);
                } elseif (!$existingSite) {
                    GoogleSearchConsoleSite::create([
                        'account_id'       => $activeAccount?->id,
                        'website_id'       => $ws->id,
                        'site_url'         => "sc-domain:{$cleanDomain}",
                        'permission_level' => 'siteFullUser',
                        'is_verified'      => true,
                        'is_sync_enabled'  => true,
                    ]);
                }
            } elseif (!$existingSite->is_sync_enabled) {
                $existingSite->update(['is_sync_enabled' => true]);
            }
        }

        $sitesQuery = GoogleSearchConsoleSite::where('is_sync_enabled', true)->whereNotNull('website_id');
        if ($websiteId) {
            $sitesQuery->where('website_id', $websiteId);
        }

        $sites = $sitesQuery->with('website')->orderBy('website_id')->get();

        if ($sites->isEmpty()) {
            $this->warn("No active Google Search Console sites found to inspect.");
            Cache::put('gsc_auto_inspect_all_status', [
                'running' => false,
                'message' => 'No websites found to scan.',
            ], 600);
            return 0;
        }

        $totalSites = $sites->count();
        $recentLogs = [];
        $sumIndexedAll = 0;
        $sumNotIndexedAll = 0;

        if (!$websiteId) {
            Cache::put('gsc_auto_inspect_all_status', [
                'running'                 => true,
                'started_at'              => now()->toIso8601String(),
                'total_sites'             => $totalSites,
                'sites_completed'         => 0,
                'current_domain'          => 'Initializing multi-site scanner...',
                'total_indexed_found'     => 0,
                'total_not_indexed_found' => 0,
                'recent_logs'             => ['🚀 Starting full scan across ' . $totalSites . ' websites...'],
                'message'                 => "Scanning 0/{$totalSites} websites...",
            ], 3600);
        }

        $this->info("Starting Automated GSC URL Inspection across {$totalSites} site(s)...");

        foreach ($sites as $siteIdx => $site) {
            $website = $site->website;
            if (!$website) continue;

            $cleanDomain = $website->clean_domain ?: preg_replace('#^(sc-domain:|https?://(www\.)?)#i', '', rtrim((string) $site->site_url, '/'));
            $siteNum = $siteIdx + 1;

            $this->line("==================================================");
            $this->info("[{$siteNum}/{$totalSites}] Scanning Website: {$website->name} ({$site->site_url})");

            if (!$websiteId) {
                $recentLogs[] = "[{$siteNum}/{$totalSites}] Scanning {$cleanDomain}...";
                if (count($recentLogs) > 25) {
                    $recentLogs = array_slice($recentLogs, -25);
                }
                Cache::put('gsc_auto_inspect_all_status', [
                    'running'                 => true,
                    'started_at'              => now()->toIso8601String(),
                    'total_sites'             => $totalSites,
                    'sites_completed'         => $siteIdx,
                    'current_domain'          => $cleanDomain,
                    'total_indexed_found'     => $sumIndexedAll,
                    'total_not_indexed_found' => $sumNotIndexedAll,
                    'recent_logs'             => $recentLogs,
                    'message'                 => "Scanning [{$siteNum}/{$totalSites}] {$cleanDomain}...",
                ], 3600);
            }

            $cacheKey = "gsc_inspecting_{$website->id}";
            Cache::put($cacheKey, [
                'running'      => true,
                'started_at'   => now()->toIso8601String(),
                'website_id'   => $website->id,
                'website_name' => $website->name,
                'total'        => 0,
                'processed'    => 0,
                'indexed'      => 0,
                'not_indexed'  => 0,
                'errors'       => 0,
                'message'      => 'Discovering Sitemaps & URLs...',
            ], 3600);

            try {
                // 0. Sync sitemaps first so submitted/indexed counts are fresh
                try {
                    $sitemapService->getWebsiteSitemaps($website, false);
                } catch (\Throwable $e) {}

                // 1. Gather all discovered URLs (sitemaps + published pages)
                $urls = $sitemapService->getDiscoveredSitemapUrls($website, false);

                // Also include any URLs from google_search_console_pages
                $analyticsUrls = DB::table('google_search_console_pages')
                    ->where('website_id', $website->id)
                    ->pluck('page')
                    ->all();

                $allUrls = array_values(array_unique(array_filter(array_merge($urls, $analyticsUrls))));
                if (empty($allUrls) && $cleanDomain) {
                    $allUrls = [
                        'https://' . $cleanDomain . '/',
                        'https://www.' . $cleanDomain . '/',
                    ];
                }
                $totalDiscovered = count($allUrls);

                $this->line("Found {$totalDiscovered} total unique URLs.");

                // 2. Filter URLs that need inspection (never inspected, or older than 7 days if not forced)
                $cutoff = $force ? now()->addDay() : now()->subDays(7);
                $recentlyInspectedHashes = GoogleSearchConsoleUrlInspection::where('website_id', $website->id)
                    ->where('inspected_at', '>=', $cutoff)
                    ->pluck('url_hash')
                    ->all();
                $recentSet = array_flip($recentlyInspectedHashes);

                $pendingUrls = [];
                foreach ($allUrls as $u) {
                    $h = sha1($u);
                    if (!isset($recentSet[$h])) {
                        $pendingUrls[] = $u;
                    }
                }

                $urlsToInspect = array_slice($pendingUrls, 0, $limit);
                $toInspectCount = count($urlsToInspect);

                $indexedCount = 0;
                $notIndexedCount = 0;
                $errorsCount = 0;

                Cache::put($cacheKey, [
                    'running'      => true,
                    'started_at'   => now()->toIso8601String(),
                    'website_id'   => $website->id,
                    'website_name' => $website->name,
                    'total'        => $toInspectCount,
                    'processed'    => 0,
                    'indexed'      => 0,
                    'not_indexed'  => 0,
                    'errors'       => 0,
                    'message'      => "Inspecting {$toInspectCount} URLs...",
                ], 3600);

                foreach ($urlsToInspect as $idx => $url) {
                    try {
                        $res = $sitemapService->inspectPageUrl($website, $url);
                        if (!empty($res['is_indexed'])) {
                            $indexedCount++;
                        } else {
                            $notIndexedCount++;
                        }
                    } catch (\Throwable $e) {
                        $errorsCount++;
                        Log::warning("GSC auto-inspect error for {$url}: " . $e->getMessage());
                    }

                    $processed = $idx + 1;
                    if ($processed % 5 === 0 || $processed === $toInspectCount) {
                        Cache::put($cacheKey, [
                            'running'      => true,
                            'started_at'   => now()->toIso8601String(),
                            'website_id'   => $website->id,
                            'website_name' => $website->name,
                            'total'        => $toInspectCount,
                            'processed'    => $processed,
                            'indexed'      => $indexedCount,
                            'not_indexed'  => $notIndexedCount,
                            'errors'       => $errorsCount,
                            'message'      => "Inspected {$processed}/{$toInspectCount} URLs...",
                        ], 3600);
                    }

                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                }

                // 3. Refresh full indexation overview so Indexed / Not Indexed counts and reasons are stored in DB
                $overview = $sitemapService->getIndexationOverview($website, 'all', '', 1, 25, false);
                $summary = $overview['summary'] ?? [];

                $finalIndexed = (int) ($summary['total_indexed'] ?? 0);
                $finalNotIndexed = (int) ($summary['total_not_indexed'] ?? 0);
                $finalReasons = $summary['gsc_reasons'] ?? [];

                // Ensure site record has updated timestamp and counts
                $site->refresh();
                $site->update([
                    'gsc_indexed_pages'       => $site->gsc_indexed_pages !== null ? max((int) $site->gsc_indexed_pages, $finalIndexed) : $finalIndexed,
                    'gsc_not_indexed_pages'   => ($site->gsc_not_indexed_pages !== null && (int) $site->gsc_not_indexed_pages > 0) ? (int) $site->gsc_not_indexed_pages : $finalNotIndexed,
                    'gsc_indexing_updated_at' => now(),
                    'gsc_indexing_reasons'    => !empty($site->gsc_indexing_reasons) ? $site->gsc_indexing_reasons : $finalReasons,
                    'last_synced_at'          => now(),
                    'sync_status'             => 'success',
                ]);

                $siteIndexed = (int) $site->gsc_indexed_pages;
                $siteNotIndexed = (int) $site->gsc_not_indexed_pages;
                $sumIndexedAll += $siteIndexed;
                $sumNotIndexedAll += $siteNotIndexed;

                $this->info("Completed {$website->name}! Indexed: {$siteIndexed}, Not Indexed: {$siteNotIndexed}");

                if (!$websiteId) {
                    $recentLogs[] = "✓ {$cleanDomain}: {$siteIndexed} Indexed, {$siteNotIndexed} Not Indexed";
                    if (count($recentLogs) > 25) {
                        $recentLogs = array_slice($recentLogs, -25);
                    }
                    Cache::put('gsc_auto_inspect_all_status', [
                        'running'                 => true,
                        'started_at'              => now()->toIso8601String(),
                        'total_sites'             => $totalSites,
                        'sites_completed'         => $siteNum,
                        'current_domain'          => $cleanDomain,
                        'total_indexed_found'     => $sumIndexedAll,
                        'total_not_indexed_found' => $sumNotIndexedAll,
                        'recent_logs'             => $recentLogs,
                        'message'                 => "Scanned {$siteNum}/{$totalSites} websites...",
                    ], 3600);
                }

                Cache::put($cacheKey, [
                    'running'      => false,
                    'finished_at'  => now()->toIso8601String(),
                    'website_id'   => $website->id,
                    'website_name' => $website->name,
                    'total'        => $toInspectCount,
                    'processed'    => $toInspectCount,
                    'indexed'      => $siteIndexed,
                    'not_indexed'  => $siteNotIndexed,
                    'message'      => "Scan complete! {$siteIndexed} indexed, {$siteNotIndexed} not indexed.",
                ], 3600);

            } catch (\Throwable $ex) {
                $this->error("Failed scanning {$website->name}: " . $ex->getMessage());
                if (!$websiteId) {
                    $recentLogs[] = "⚠️ {$cleanDomain}: " . $ex->getMessage();
                }
                Cache::put($cacheKey, [
                    'running' => false,
                    'error'   => $ex->getMessage(),
                ], 600);
            }
        }

        if (!$websiteId) {
            $recentLogs[] = "🎉 Finished scanning all {$totalSites} websites! Total Indexed: {$sumIndexedAll}, Total Not Indexed: {$sumNotIndexedAll}";
            Cache::put('gsc_auto_inspect_all_status', [
                'running'                 => false,
                'finished_at'             => now()->toIso8601String(),
                'total_sites'             => $totalSites,
                'sites_completed'         => $totalSites,
                'current_domain'          => "All {$totalSites} websites scanned!",
                'total_indexed_found'     => $sumIndexedAll,
                'total_not_indexed_found' => $sumNotIndexedAll,
                'recent_logs'             => $recentLogs,
                'message'                 => "Completed! Scanned {$totalSites} websites ({$sumIndexedAll} Indexed, {$sumNotIndexedAll} Not Indexed).",
            ], 3600);
        }

        $this->info("All GSC auto-inspections finished successfully.");
        return 0;
    }
}
