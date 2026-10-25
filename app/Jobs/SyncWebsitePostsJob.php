<?php

namespace App\Jobs;

use App\Models\Website;
use App\Models\WebsiteIntegration;
use App\Models\WebsiteSyncLog;
use App\Services\Ga4AnalyticsSyncService;
use App\Services\WebsiteSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncWebsitePostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout per job in seconds.
     */
    public int $timeout = 120;

    /**
     * Number of retry attempts.
     */
    public int $tries = 3;

    /**
     * Exponential backoff in seconds between attempts: 30s, 2m, 5m.
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public Website $website,
        public string $triggeredBy = 'scheduled',
        public ?int $userId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        WebsiteSyncService $syncService,
        Ga4AnalyticsSyncService $ga4Service
    ): void {
        $lockKey = 'dgt_sync_lock_site_' . $this->website->id;
        $lock = Cache::lock($lockKey, 150);

        if (!$lock->get()) {
            Log::info("SyncWebsitePostsJob: Sync already in progress for site #{$this->website->id} ({$this->website->name}). Skipping duplicate.");
            return;
        }

        try {
            $integration = $this->website->integration;
            if (!$integration || !$integration->is_active) {
                Log::info("SyncWebsitePostsJob: Website #{$this->website->id} has no active integration.");
                return;
            }

            // 1. Pull posts if configured for pull mode or WP plugin/REST
            if (in_array($integration->connector_type, [
                WebsiteIntegration::CONNECTOR_WP_PLUGIN,
                WebsiteIntegration::CONNECTOR_WP_REST,
                WebsiteIntegration::CONNECTOR_LARAVEL,
                WebsiteIntegration::CONNECTOR_PHP,
            ])) {
                $syncService->pullPostsFromRemote($this->website, $this->triggeredBy, $this->userId);
            }

            // 2. Synchronize GA4 data if configured
            if (!empty($integration->ga4_property_id)) {
                $ga4Service->syncWebsite($this->website, 30, $this->userId);
            }

        } catch (Throwable $e) {
            Log::error("SyncWebsitePostsJob failed for website #{$this->website->id} ({$this->website->name}): " . $e->getMessage());

            if ($this->attempts() >= $this->tries) {
                // Record permanent failure
                WebsiteSyncLog::create([
                    'website_id'    => $this->website->id,
                    'sync_type'     => 'blog_sync_pull',
                    'status'        => WebsiteSyncLog::STATUS_FAILED,
                    'error_message' => "Final attempt failed: " . $e->getMessage(),
                    'triggered_by'  => $this->triggeredBy,
                    'user_id'       => $this->userId,
                ]);

                if ($this->website->integration) {
                    $this->website->integration->update([
                        'last_sync_status' => WebsiteSyncLog::STATUS_FAILED,
                        'last_sync_error'  => $e->getMessage(),
                        'cms_status'       => WebsiteIntegration::CMS_STATUS_ERROR,
                    ]);
                }
            } else {
                throw $e; // Trigger exponential retry
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Tags for monitoring.
     */
    public function tags(): array
    {
        return [
            'website-sync',
            'site:' . $this->website->id,
        ];
    }
}
