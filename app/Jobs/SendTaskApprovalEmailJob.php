<?php

namespace App\Jobs;

use App\Models\Card;
use App\Models\User;
use App\Notifications\TaskApprovedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTaskApprovalEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry on failure (e.g. SMTP timeout).
     */
    public int $tries = 3;

    /**
     * Seconds to wait before each retry.
     */
    public array $backoff = [30, 60, 120];

    /**
     * Seconds before the job is considered timed out.
     */
    public int $timeout = 60;

    public function __construct(
        public readonly Card $card,
        public readonly User $approvedBy
    ) {}

    /**
     * Send the approval email to all users with the 'boss' role.
     * Also notifies the card creator if they are not the approver.
     */
    public function handle(): void
    {
        // 1. Notify all Boss-role users
        $bosses = User::role('boss')->active()->get();

        if ($bosses->isEmpty()) {
            Log::warning("TaskApprovalEmailJob: No active Boss users found. Card #{$this->card->id} approved but no email sent.");
        }

        foreach ($bosses as $boss) {
            $boss->notify(new TaskApprovedNotification($this->card, $this->approvedBy));
            Log::info("TaskApprovalEmailJob: Notified boss {$boss->email} for card #{$this->card->id}");
        }

        // 2. Also notify the card creator so they know their task was approved
        $creator = $this->card->creator;
        if ($creator && $creator->id !== $this->approvedBy->id) {
            $creator->notify(new TaskApprovedNotification($this->card, $this->approvedBy));
        }
    }

    /**
     * Handle job failure — log it so it can be monitored.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("TaskApprovalEmailJob failed for card #{$this->card->id}: {$exception->getMessage()}");
    }
}
