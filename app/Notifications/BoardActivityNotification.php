<?php

namespace App\Notifications;

use App\Events\BoardUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class BoardActivityNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly array $payload)
    {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        // Broadcasts over websockets and saves to standard database table
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id'         => $this->id,
            'data'       => $this->payload,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Static helper to dispatch notifications to all board members.
     */
    public static function send(
        \App\Models\Board $board,
        string $action,
        string $description,
        ?\App\Models\Card $card = null,
        bool $force = false
    ): void {
        $actor = auth()->user();
        if (!$actor) return;

        $payload = [
            'actor_id'     => $actor->id,
            'actor_name'   => $actor->name,
            'actor_avatar' => $actor->avatar_url,
            'actor_initials' => $actor->avatar_initials,
            'actor_avatar_color' => $actor->avatar_color,
            'action'       => $action,
            'description'  => $description,
            'board_id'     => $board->id,
            'board_name'   => $board->name,
            'board_slug'   => $board->slug,
            'browser_notifications_enabled' => (bool) ($board->browser_notifications_enabled ?? false),
            'card_id'      => $card?->id,
            'card_title'   => $card?->title,
            'link'         => route('boards.show', $board->slug) . ($card ? "?card={$card->id}" : ""),
            'created_at'   => now()->toIso8601String(),
        ];

        // Board-channel broadcast disabled to prevent board UI auto-refresh/blink.
        // Notifications still arrive via per-user private channel ($member->notify below).
        // event(new BoardUpdated($board->id, $board->slug, $action, $card?->id, $actor->id));

        if (!$force && $board->notifications_enabled === false) {
            return;
        }

        $board->loadMissing('members');
        foreach ($board->members as $member) {
            $isSuperAdminTesting = $actor->hasRole('super-admin') && in_array($action, ['file_edited', 'file_replaced']);
            
            if ($member->id !== $actor->id || $isSuperAdminTesting) {
                $member->notify(new self($payload));
            }
        }
    }
}
