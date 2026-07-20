<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Laravel's own notification broadcasting (`via() => [..., 'broadcast']`)
 * fires Illuminate\Notifications\Events\BroadcastNotificationCreated, which
 * implements ShouldBroadcast — Laravel queues that as a BroadcastEvent job
 * rather than sending it immediately. This deployment has no queue worker
 * running (QUEUE_CONNECTION=database with nothing ever processing it — see
 * TaskApprovedNotification's docblock), so that job just sits in the `jobs`
 * table forever and Pusher never actually fires; the bell only ever updated
 * on manual refresh, never live.
 *
 * This event delivers the exact same payload synchronously instead, the
 * same ShouldBroadcastNow pattern BoardUpdated already established for this
 * exact problem. broadcastAs() intentionally reuses Laravel's own event name
 * so the existing frontend (`channel.bind('Illuminate\Notifications\Events\BroadcastNotificationCreated', ...)`
 * in layouts/app.blade.php) keeps working with zero client-side changes —
 * dispatch this via App\Support\InstantNotifier::send() rather than
 * constructing it directly.
 */
class InstantNotificationBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'Illuminate\Notifications\Events\BroadcastNotificationCreated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
