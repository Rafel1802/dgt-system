<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class GenericDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $data) {}

    public function via(object $notifiable): array
    {
        // Database only. Live push is handled by InstantNotifier → InstantNotificationBroadcast
        // (ShouldBroadcastNow). Including 'broadcast' here ALSO queues Laravel's own
        // BroadcastNotificationCreated job; when a queue worker/cron runs it, the same
        // alert is delivered a second time and the UI shows a duplicate card.
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->data;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'data' => $this->data,
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
