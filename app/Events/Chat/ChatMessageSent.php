<?php

namespace App\Events\Chat;

use App\Models\Chat\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message, public ?string $clientTempId = null, public bool $isUpdate = false) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.conversation.' . $this->message->conversation_id),
        ];

        // Also broadcast directly to each conversation member's private user channel
        // so their sidebar, unread badge, and sound chime update instantly (0ms) without waiting for polling
        try {
            $memberIds = \App\Models\Chat\ChatConversationMember::where('conversation_id', $this->message->conversation_id)
                ->where('user_id', '!=', $this->message->user_id)
                ->pluck('user_id');

            foreach ($memberIds as $mId) {
                $channels[] = new PrivateChannel('chat.user.' . $mId);
            }
        } catch (\Throwable $e) {}

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->load(['user:id,name,avatar', 'attachments', 'replyTo.user:id,name', 'reactions.user:id,name']);

        return [
            'id'              => (int) $this->message->id,
            'client_temp_id'  => $this->clientTempId ?? ($this->message->client_temp_id ?? null),
            'conversation_id' => (int) $this->message->conversation_id,
            'user_id'         => (int) $this->message->user_id,
            'status'          => 'sent',
            'is_update'       => $this->isUpdate,
            'is_seen'         => false,
            'sender_name'     => $this->message->user->name ?? 'User',
            'sender_avatar'   => $this->message->user && $this->message->user->avatar ? asset('storage/' . $this->message->user->avatar) : null,
            'user'            => [
                'id'         => (int) $this->message->user_id,
                'name'       => $this->message->user->name ?? 'User',
                'avatar_url' => $this->message->user ? $this->message->user->avatar_url : null,
            ],
            'type'            => $this->message->type,
            'content'         => $this->message->content ?? '',
            'body'            => $this->message->content ?? '',
            'link_preview'    => $this->message->link_preview,
            'poll_data'       => $this->message->poll_data,
            'is_edited'       => (bool) $this->message->is_edited,
            'is_pinned'       => (bool) $this->message->is_pinned,
            'reply_to'        => $this->message->replyTo ? [
                'id'      => (int) $this->message->replyTo->id,
                'name'    => $this->message->replyTo->user->name ?? 'User',
                'content' => \Illuminate\Support\Str::limit($this->message->replyTo->content ?? 'Media', 40),
            ] : null,
            'attachments'     => $this->message->attachments->map(fn($a) => [
                'id'               => (int) $a->id,
                'file_name'        => $a->file_name,
                'file_type'        => ($this->message->type === 'voice' || !empty($a->waveform_data) || str_starts_with($a->file_name, 'voice_')) && str_starts_with($a->file_type, 'video/') ? 'audio/webm' : $a->file_type,
                'file_size'        => $a->formatted_size,
                'duration_seconds' => $a->duration_seconds,
                'waveform_data'    => $a->waveform_data,
                'file_url'         => asset('storage/' . $a->file_path),
                'view_url'         => route('chat.attachments.view', ['attachment' => $a->id]),
                'download_url'     => $a->download_url,
            ])->all(),
            'reactions'       => $this->message->reactions->groupBy('reaction')->map(fn($r, $k) => [
                'emoji' => $k,
                'count' => count($r),
                'users' => $r->pluck('user.name')->all(),
            ])->values()->all(),
            'created_at'      => $this->message->created_at->toIso8601String(),
            'formatted_time'  => $this->message->created_at->format('H:i'),
        ];
    }
}
