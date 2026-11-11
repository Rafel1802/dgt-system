<?php

namespace App\Events\Chat;

use App\Models\Chat\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.reaction';
    }

    public function broadcastWith(): array
    {
        $this->message->load('reactions.user:id,name');

        return [
            'message_id'      => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'reactions'       => $this->message->reactions->groupBy('reaction')->map(fn($r, $k) => [
                'emoji' => $k,
                'count' => count($r),
                'users' => $r->pluck('user.name')->all(),
            ])->values()->all(),
        ];
    }
}
