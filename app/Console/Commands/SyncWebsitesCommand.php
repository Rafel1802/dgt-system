<?php

namespace App\Console\Commands;

use App\Jobs\SyncWebsitePostsJob;
use App\Models\Website;
use App\Models\WebsiteIntegration;
use App\Services\WebsiteSyncService;
use Illuminate\Console\Command;

class SyncWebsitesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dgt:sync-websites
                            {--site= : Specific website ID to synchronize}
                            {--category= : Filter by website category (e.g. "1st Class")}
                            {--hostinger= : Filter by Hostinger account}
                            {--sync : Run synchronously instead of queueing}
                            {--force : Run even if recently synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize connected websites, blogs, views, and health checks across Hostinger accounts';

    /**
     * Execute the console command.
     */
    public function handle(WebsiteSyncService $syncService): int
    {
        $siteId = $this->option('site');
        $category = $this->option('category');
        $hostinger = $this->option('hostinger');
        $runSync = (bool) $this->option('sync');

        $query = Website::where('is_archived', false)
            ->whereHas('integration', function ($q) use ($hostinger) {
                $q->where('is_active', true);
                if ($hostinger) {
                    $q->where('hostinger_account', $hostinger);
                }
            });

        if ($siteId) {
            $query->where('id', $siteId);
        }

        if ($category) {
            $query->where('category', $category);
        }

        $websites = $query->with('integration')->get();

        if ($websites->isEmpty()) {
            $this->warn("No active integrated websites found matching the criteria.");
            return 0;
        }

        $this->info("Found {$websites->count()} website(s) to synchronize.");
        $dispatched = 0;
        $failed = 0;

        foreach ($websites as $website) {
            $this->line("Processing [Site #{$website->id}] {$website->name} ({$website->clean_domain})...");

            if ($runSync) {
                try {
                    $res = $syncService->pullPostsFromRemote($website, 'cli_manual');
                    if (!empty($res['success'])) {
                        $this->info(" -> Synced {$res['items_synced']} posts, updated {$res['items_updated']}.");
                        $dispatched++;
                    } else {
                        $this->error(" -> Error: " . ($res['error'] ?? 'Unknown error'));
                        $failed++;
                    }
                } catch (\Throwable $e) {
                    $this->error(" -> Failed: " . $e->getMessage());
                    $failed++;
                }
            } else {
                SyncWebsitePostsJob::dispatch($website, 'scheduled');
                $this->line(" -> Queued SyncWebsitePostsJob.");
                $dispatched++;
            }
        }

        $this->info("Completed! Dispatched/Processed: {$dispatched}, Failed: {$failed}");
        return $failed > 0 ? 1 : 0;
    }
}
