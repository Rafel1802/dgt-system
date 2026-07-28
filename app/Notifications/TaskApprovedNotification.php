<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately NOT ShouldQueue — this app has no queue worker running
 * (deployed on shared hosting with QUEUE_CONNECTION=database and nothing
 * ever processing it), so a queued notification would just sit in the
 * `jobs` table forever and never reach the bell/email. Dispatched via
 * KanbanService::approveCard(), which calls the wrapping job synchronously
 * (dispatchSync) after the DB transaction commits, so a mail hiccup here
 * can't roll back the approval itself.
 */
class TaskApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Card $card,
        public readonly User $approvedBy
    ) {}

    public function via(object $notifiable): array
    {
        // database/broadcast first: if the mail channel throws (e.g. bad
        // SMTP config), Laravel aborts the remaining channels in the same
        // notify() call — the in-app bell shouldn't go dark just because
        // outbound email is broken.
        return ['database', 'broadcast', 'mail'];
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
            'module'      => 'digital',
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
