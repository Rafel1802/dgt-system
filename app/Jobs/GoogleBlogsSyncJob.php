<?php

namespace App\Jobs;

use App\Models\WebsiteFollowUp;
use App\Services\GoogleBlogsSheetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Background job that syncs a single blog follow-up to the Google Blogs Sheet.
 *
 * Runs after the local DB record is saved so the HTTP response to the user
 * is never blocked by a Google network call.
 *
 * Retry-safe: uses the follow-up ID as the idempotency key, so re-queuing
 * the same job does not produce duplicate sheet rows.
 */
class GoogleBlogsSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry up to 3 times on transient failures (connection timeout etc.) */
    public int $tries = 3;

    /** Wait 60 s before first retry, exponential back-off handled by backoff(). */
    public int $backoff = 60;

    public function __construct(
        public readonly int    $followUpId,
        public readonly string $classNumber,
        public readonly string $publicLink,
        public readonly string $date,        // d/m format, e.g. "27/08"
    ) {}

    public function handle(GoogleBlogsSheetService $service): void
    {
        $followUp = WebsiteFollowUp::find($this->followUpId);

        if (! $followUp) {
            Log::warning("GoogleBlogsSyncJob: FollowUp #{$this->followUpId} not found — skipping.");
            return;
        }

        // Already synced — skip (idempotency guard at DB level)
        if ($followUp->google_sheet_status === 'synced') {
            return;
        }

        Log::info("GoogleBlogsSyncJob: Syncing follow-up #{$this->followUpId} to Google Sheet.", [
            'class' => $this->classNumber,
            'date'  => $this->date,
        ]);

        $idempotencyKey = 'fu-' . $this->followUpId;

        $result = $service->pushBlog(
            classNumber:    $this->classNumber,
            publicLink:     $this->publicLink,
            date:           $this->date,
            websiteName:    $followUp->website?->name ?? 'Unknown Website',
            idempotencyKey: $idempotencyKey,
        );

        if ($result['success']) {
            $followUp->updateQuietly([
                'google_sheet_status'    => 'synced',
                'google_sheet_row'       => $result['row'] ?? null,
                'google_sheet_synced_at' => now(),
                'google_sheet_error'     => null,
            ]);
            Log::info("GoogleBlogsSyncJob: Follow-up #{$this->followUpId} synced. Row: " . ($result['row'] ?? 'unknown'));
        } else {
            $errorMsg = $result['error'] ?? 'Unknown error';

            $followUp->updateQuietly([
                'google_sheet_status' => 'failed',
                'google_sheet_error'  => $errorMsg,
            ]);

            Log::error("GoogleBlogsSyncJob: Failed to sync follow-up #{$this->followUpId}.", [
                'error' => $errorMsg,
            ]);

            // Re-throw a runtime exception so the queue marks it for retry
            throw new \RuntimeException("Google Blogs Sheet sync failed: {$errorMsg}");
        }
    }

    /** Exponential back-off: 60 s, 120 s, 240 s */
    public function backoff(): array
    {
        return [60, 120, 240];
    }

    /** After all retries exhausted, mark permanently failed */
    public function failed(\Throwable $exception): void
    {
        $followUp = WebsiteFollowUp::find($this->followUpId);
        if ($followUp) {
            $followUp->updateQuietly([
                'google_sheet_status' => 'failed',
                'google_sheet_error'  => 'Max retries exhausted: ' . $exception->getMessage(),
            ]);
        }
        Log::error("GoogleBlogsSyncJob: Permanently failed for follow-up #{$this->followUpId}.", [
            'error' => $exception->getMessage(),
        ]);
    }
}
