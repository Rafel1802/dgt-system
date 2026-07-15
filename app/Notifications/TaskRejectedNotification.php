<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Deliberately NOT ShouldQueue — see TaskApprovedNotification for why. */
class TaskRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Card $card,
        public readonly User $rejectedBy,
        public readonly string $reason
    ) {}

    public function via(object $notifiable): array
    {
        // See TaskApprovedNotification::via() — database/broadcast must not
        // depend on the (separately broken) mail channel succeeding.
        return ['database', 'broadcast', 'mail'];
    }

    /**
     * Build rejection email using the branded HTML template.
     * Variables: $notifiable, $card, $rejectedBy, $reason
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->card->loadMissing(['creator']);

        return (new MailMessage)
            ->subject("❌ Task Needs Revision: {$this->card->title} — DGT System")
            ->view('emails.task-rejected', [
                'notifiable' => $notifiable,
                'card'       => $this->card,
                'rejectedBy' => $this->rejectedBy,
                'reason'     => $this->reason,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'module'      => 'digital',
            'card_id'     => $this->card->id,
            'card_title'  => $this->card->title,
            'rejected_by' => $this->rejectedBy->name,
            'reason'      => $this->reason,
            'type'        => 'task_rejected',
            'title'       => 'Task needs revision',
            'message'     => "{$this->rejectedBy->name} rejected {$this->card->title}",
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
