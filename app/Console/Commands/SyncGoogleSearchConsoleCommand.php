<?php

namespace App\Console\Commands;

use App\Models\GoogleSearchConsole\GoogleSearchConsoleSite;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleSyncService;
use Illuminate\Console\Command;

class SyncGoogleSearchConsoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gsc:sync
                            {--days=30 : Number of days back to synchronize}
                            {--website= : Specific DGT website ID to sync}
                            {--force : Force sync even if already synced today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize performance data from Google Search Console API';

    /**
     * Execute the console command.
     */
    public function handle(GoogleSearchConsoleSyncService $syncService): int
    {
        $days = (int) $this->option('days') ?: 30;
        $websiteId = $this->option('website');

        $this->info("Starting Google Search Console sync (Window: last {$days} days)...");

        if ($websiteId) {
            $site = GoogleSearchConsoleSite::where('website_id', $websiteId)->first();
            if (!$site) {
                $this->error("No Search Console mapping found for website ID: {$websiteId}");
                return 1;
            }

            $this->line("Syncing property: {$site->site_url} (Website #{$site->website_id})...");
            $res = $syncService->syncSite($site, $days, 'schedule');

            if ($res['success']) {
                $this->info("Sync successful! Synced {$res['records_synced']} records. Latest data date: {$res['latest_date']}");
                return 0;
            } else {
                $this->error("Sync failed: {$res['error']}");
                return 1;
            }
        }

        $results = $syncService->syncAll($days, 'schedule');

        $this->info("Sync complete!");
        $this->line("Total Sites:    {$results['total_sites']}");
        $this->line("Successful:     {$results['successful']}");
        $this->line("Failed:         {$results['failed']}");
        $this->line("Total Records:  {$results['total_records']}");

        if (!empty($results['errors'])) {
            $this->warn("Errors encountered:");
            foreach ($results['errors'] as $siteUrl => $err) {
                $this->line(" - [{$siteUrl}]: {$err}");
            }
        }

        return $results['failed'] > 0 ? 1 : 0;
    }
}
