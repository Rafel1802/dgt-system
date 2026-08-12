<?php

namespace App\Notifications;

use App\Models\Website;
use App\Models\WebsiteMember;
use App\Models\User;
use App\Support\InstantNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class WebsiteActivityNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $payload)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id'         => $this->id,
            'data'       => $this->payload,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public static function send(
        Website $website,
        string $action,
        string $description,
        ?string $note = null,
        ?string $attachmentUrl = null,
        bool $force = false
    ): void {
        $actor = auth()->user();
        if (!$actor) return;

        $payload = [
            'module'       => 'digital',
            'actor_id'     => $actor->id,
            'actor_name'   => $actor->name,
            'actor_avatar' => $actor->avatar_url,
            'actor_initials' => $actor->avatar_initials,
            'actor_avatar_color' => $actor->avatar_color,
            'action'       => $action,
            'description'  => $description,
            'website_id'   => $website->id,
            'website_name' => $website->name,
            'note'         => $note,
            'attachment_url'=> $attachmentUrl,
            'link'         => route('websites.index', ['fu_website' => $website->id]),
            'created_at'   => now()->toIso8601String(),
        ];

        // Gather recipients
        $recipients = collect();

        // 1. The handler
        if ($website->handler) {
            $recipients->push($website->handler);
        }

        // 2. Global website members (QC, Developers, etc)
        $memberIds = WebsiteMember::pluck('user_id');
        $members = User::whereIn('id', $memberIds)->get();
        foreach ($members as $member) {
            $recipients->push($member);
        }

        // 3. Supervisors / Bosses
        $supervisors = User::role(['admin-digital', 'boss', 'super-admin'])->get();
        foreach ($supervisors as $supervisor) {
            $recipients->push($supervisor);
        }

        // Make unique by ID
        $recipients = $recipients->unique('id');

        dispatch(function () use ($recipients, $actor, $payload) {
            foreach ($recipients as $recipient) {
                if ($recipient->id !== $actor->id) {
                    $recipient->notify(new self($payload));
                }
            }
        })->afterResponse();
    }
}
