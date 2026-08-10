<?php

namespace App\Jobs;

use App\Models\Card;
use App\Models\User;
use App\Notifications\TaskToggledNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTaskToggledNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];
    public int $timeout = 60;

    public function __construct(
        public readonly Card $card,
        public readonly User $supervisor,
        public readonly bool $isApproved
    ) {}

    public function handle(): void
    {
        // 1. Notify QC users
        $qcUsers = User::where(function($query) {
            $query->where('team_role', 'like', '%qc%')
                  ->orWhere('team_role', 'like', '%QC%');
        })->active()->get();

        if ($qcUsers->isEmpty()) {
            Log::warning("TaskToggledNotificationJob: No active QC users found for card #{$this->card->id}.");
        }

        foreach ($qcUsers as $qc) {
            // don't notify the supervisor if they are QC
            if ($qc->id !== $this->supervisor->id) {
                $qc->notify(new TaskToggledNotification($this->card, $this->supervisor, $this->isApproved));
            }
        }

        // 2. Notify assigned members
        $assignees = $this->card->members;
        foreach ($assignees as $member) {
            // don't notify the supervisor if they are assigned
            if ($member->id !== $this->supervisor->id) {
                $member->notify(new TaskToggledNotification($this->card, $this->supervisor, $this->isApproved));
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("TaskToggledNotificationJob failed for card #{$this->card->id}: {$exception->getMessage()}");
    }
}
