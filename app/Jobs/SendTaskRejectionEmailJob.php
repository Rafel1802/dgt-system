<?php

namespace App\Jobs;

use App\Models\Card;
use App\Models\User;
use App\Notifications\TaskRejectedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTaskRejectionEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];
    public int $timeout = 60;

    public function __construct(
        public readonly Card $card,
        public readonly User $rejectedBy,
        public readonly string $reason
    ) {}

    public function handle(): void
    {
        $creator = $this->card->creator;
        if ($creator) {
            $creator->notify(new TaskRejectedNotification($this->card, $this->rejectedBy, $this->reason));
            Log::info("TaskRejectionEmailJob: Notified creator {$creator->email} for card #{$this->card->id}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("TaskRejectionEmailJob failed for card #{$this->card->id}: {$exception->getMessage()}");
    }
}
