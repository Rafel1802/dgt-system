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
        $limit = max(5, (int) $this->option('limit') ?: 150);
        $delayMs = max(50, (int) $this->option('delay') ?: 120);
        $force = (bool) $this->option('force');

        $sitesQuery = GoogleSearchConsoleSite::where('is_sync_enabled', true)
            ->whereHas('account', fn($q) => $q->where('is_active', true));

        if ($websiteId) {
            $sitesQuery->where('website_id', $websiteId);
        }

        $sites = $sitesQuery->with('website')->get();

        if ($sites->isEmpty()) {
            $this->warn("No active Google Search Console sites found to inspect.");
            return 0;
        }

        $this->info("Starting Automated GSC URL Inspection across " . $sites->count() . " site(s)...");

        foreach ($sites as $site) {
            $website = $site->website;
            if (!$website) continue;

            $this->line("==================================================");
            $this->info("Scanning Website: {$website->name} ({$site->site_url})");

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
                'message'      => 'Discovering URLs...',
            ], 3600);

            try {
                // 1. Gather all discovered URLs (sitemaps + published pages)
                $urls = $sitemapService->getDiscoveredSitemapUrls($website);

                // Also include any URLs from google_search_console_pages
                $analyticsUrls = DB::table('google_search_console_pages')
                    ->where('website_id', $website->id)
                    ->pluck('page')
                    ->all();

                $allUrls = array_values(array_unique(array_filter(array_merge($urls, $analyticsUrls))));
                $totalDiscovered = count($allUrls);

                $this->line("Found {$totalDiscovered} total unique URLs.");

                if ($totalDiscovered === 0) {
                    Cache::put($cacheKey, ['running' => false, 'message' => 'No URLs discovered.'], 300);
                    continue;
                }

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

                $this->line(count($pendingUrls) . " URLs need inspection (skipped " . (count($allUrls) - count($pendingUrls)) . " recently verified).");

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
                    'message'      => "Inspecting {$toInspectCount} URLs via Google API...",
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
                    if ($processed % 10 === 0 || $processed === $toInspectCount) {
                        $this->line(sprintf(" [%d/%d] Indexed: %d, Not Indexed: %d, Errors: %d",
                            $processed, $toInspectCount, $indexedCount, $notIndexedCount, $errorsCount));

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

                // 3. Aggregate all verified URL inspections for this website into site headline stats
                $inspections = GoogleSearchConsoleUrlInspection::where('website_id', $website->id)->get();
                $totalVerifiedIndexed = $inspections->where('is_indexed', true)->count();
                $totalVerifiedNotIndexed = $inspections->where('is_indexed', false)->count();

                // If some discovered URLs are not in inspections, add them to not-indexed
                $uninspectedDiscovered = max(0, $totalDiscovered - ($totalVerifiedIndexed + $totalVerifiedNotIndexed));
                $effectiveNotIndexed = $totalVerifiedNotIndexed + $uninspectedDiscovered;

                // Tally exclusion reasons
                $reasonsTally = [];
                foreach ($inspections as $ins) {
                    if (!$ins->is_indexed) {
                        $st = $ins->coverage_state ?: 'Discovered - currently not indexed';
                        $reasonsTally[$st] = ($reasonsTally[$st] ?? 0) + 1;
                    }
                }
                if ($uninspectedDiscovered > 0) {
                    $reasonsTally['Discovered in sitemap - Not inspected yet'] = $uninspectedDiscovered;
                }

                $reasons = [];
                foreach ($reasonsTally as $rName => $rCount) {
                    $reasons[] = ['reason' => $rName, 'count' => (int) $rCount];
                }
                usort($reasons, fn($a, $b) => $b['count'] <=> $a['count']);

                // Update site record with live verified snapshot
                $site->update([
                    'gsc_indexed_pages'       => $totalVerifiedIndexed,
                    'gsc_not_indexed_pages'   => $effectiveNotIndexed,
                    'gsc_indexing_updated_at' => now(),
                    'gsc_indexing_reasons'    => $reasons,
                ]);

                $this->info("Completed {$website->name}! Verified Indexed: {$totalVerifiedIndexed}, Not Indexed: {$effectiveNotIndexed}");

                Cache::put($cacheKey, [
                    'running'      => false,
                    'finished_at'  => now()->toIso8601String(),
                    'website_id'   => $website->id,
                    'website_name' => $website->name,
                    'total'        => $toInspectCount,
                    'processed'    => $toInspectCount,
                    'indexed'      => $totalVerifiedIndexed,
                    'not_indexed'  => $effectiveNotIndexed,
                    'message'      => "Scan complete! {$totalVerifiedIndexed} indexed, {$effectiveNotIndexed} not indexed.",
                ], 3600);

            } catch (\Throwable $ex) {
                $this->error("Failed scanning {$website->name}: " . $ex->getMessage());
                Cache::put($cacheKey, [
                    'running' => false,
                    'error'   => $ex->getMessage(),
                ], 600);
            }
        }

        $this->info("All GSC auto-inspections finished successfully.");
        return 0;
    }
}
