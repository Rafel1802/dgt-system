<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TaskApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Card $card,
        public readonly User $approvedBy
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Build the mail message using the branded HTML template.
     * Variables passed to the template:
     *   $notifiable  — the Boss/creator User receiving the email
     *   $card        — the Card model (with assignees, creator pre-loaded)
     *   $approvedBy  — the Supervisor who approved
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Eager-load relationships needed in the template
        $this->card->loadMissing(['creator', 'assignees']);

        return (new MailMessage)
            ->subject("✅ Task Approved: {$this->card->title} — DGT System")
            ->view('emails.task-approved', [
                'notifiable' => $notifiable,
                'card'       => $this->card,
                'approvedBy' => $this->approvedBy,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'card_id'     => $this->card->id,
            'card_title'  => $this->card->title,
            'approved_by' => $this->approvedBy->name,
            'type'        => 'task_approved',
            'title'       => 'Task approved',
            'message'     => "{$this->approvedBy->name} approved {$this->card->title}",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'data' => $this->toArray($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
