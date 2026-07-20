<?php

namespace App\Support;

use App\Events\InstantNotificationBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Drop-in replacement for `$notifiable->notify($notification)` that also
 * makes the bell update live over Pusher — see InstantNotificationBroadcast
 * for why the notification's own 'broadcast' channel alone doesn't deliver
 * in this deployment (no queue worker; Laravel queues it and it never runs).
 *
 * Pre-assigns $notification->id before calling ->notify() rather than
 * reading it back afterward — Laravel's NotificationSender clones the
 * notification internally (`$original = clone $notification`, repeated per
 * channel), so any ->id it assigns lands on those clones, never on the
 * instance the caller still holds. NotificationSender only mints its own
 * UUID `if (! $notification->id)`, so pre-setting it here makes Laravel
 * reuse this exact id for the `notifications` table row instead — keeping
 * the live push and the row it corresponds to in sync (and letting the
 * frontend's dedupe-by-id check work correctly if the on-demand
 * /notifications refresh also picks up the same row).
 */
class InstantNotifier
{
    public static function send(object $notifiable, Notification $notification): void
    {
        $notification->id ??= (string) Str::uuid();

        $notifiable->notify($notification);

        if (! method_exists($notification, 'toBroadcast') || ! isset($notifiable->id)) {
            return;
        }

        $payload = $notification->toBroadcast($notifiable)->data;

        event(new InstantNotificationBroadcast($notifiable->id, $payload));
    }
}
