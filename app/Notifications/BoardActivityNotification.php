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

        $cardTeam = $card ? ($card->isBothTeams() ? 'Both' : ($card->detectTeam() ?? $card->team)) : null;

        $payload = [
            'module'       => 'digital',
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
            'card_team'    => $cardTeam,
            'link'         => route('boards.show', $board->slug) . ($card ? "?card={$card->id}" : ""),
            'created_at'   => now()->toIso8601String(),
        ];

        // Board-channel broadcast triggers real-time UI sync for active viewers
        event(new BoardUpdated($board->id, $board->slug, $action, $card?->id, $actor->id));

        if (!$force && $board->notifications_enabled === false) {
            return;
        }

        dispatch(function () use ($board, $actor, $action, $payload, $card, $cardTeam) {
            $board->loadMissing(['members', 'unwatchers']);
            $unwatcherIds = $board->unwatchers->pluck('id')->toArray();
            
            foreach ($board->members as $member) {
                if (in_array($member->id, $unwatcherIds)) {
                    continue;
                }

                // Team Isolation: Team A members must not receive Team B notifications, and vice-versa
                $memberTeam = $member->getDigitalTeam($board->workspace_id);
                $isGeneralSupervisor = $member->hasAnyRole(['super-admin', 'admin-digital', 'admin', 'supervisor', 'boss']) || $member->canFilterAllPlanningTeams();

                if (!$isGeneralSupervisor && $memberTeam && in_array($memberTeam, ['A', 'B'])) {
                    if ($card) {
                        $isBoth = in_array(strtoupper(trim($cardTeam ?? '')), ['BOTH', 'A,B', 'A&B', 'ALL', 'A+B', 'TEAM A & B']) || $card->isBothTeams();
                        if (!$isBoth) {
                            $isAssigned = false;
                            try {
                                $isAssigned = $card->relationLoaded('assignees')
                                    ? $card->assignees->contains('id', $member->id)
                                    : $card->assignees()->where('users.id', $member->id)->exists();
                            } catch (\Throwable $e) {}

                            if (!$isAssigned) {
                                // Member is Team A, card is Team B -> skip notification!
                                if ($cardTeam === 'B' && $memberTeam === 'A') {
                                    continue;
                                }
                                // Member is Team B, card is Team A -> skip notification!
                                if ($cardTeam === 'A' && $memberTeam === 'B') {
                                    continue;
                                }
                            }
                        }
                    } else {
                        // Workflow board list/board notification
                        $bName = strtolower($board->name ?? '');
                        if ((str_contains($bName, 'team a') || str_contains($bName, 'teama') || str_contains($bName, 'team-a')) && $memberTeam === 'B') {
                            continue;
                        }
                        if ((str_contains($bName, 'team b') || str_contains($bName, 'teamb') || str_contains($bName, 'team-b')) && $memberTeam === 'A') {
                            continue;
                        }
                    }
                }

                $isSuperAdminTesting = $actor->hasRole('super-admin') && in_array($action, ['file_edited', 'file_replaced']);
                
                if ($member->id !== $actor->id || $isSuperAdminTesting) {
                    $member->notify(new self($payload));
                }
            }
        })->afterResponse();
    }
}
