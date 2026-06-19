<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Card $card,
        public readonly User $rejectedBy,
        public readonly string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
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
            'card_id'     => $this->card->id,
            'card_title'  => $this->card->title,
            'rejected_by' => $this->rejectedBy->name,
            'reason'      => $this->reason,
            'type'        => 'task_rejected',
        ];
    }
}
