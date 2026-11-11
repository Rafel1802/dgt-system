<?php

namespace App\Events\Chat;

use App\Models\Chat\ChatConversationMember;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $messageId,
        public ?int $deletedByUserId = null
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.conversation.' . $this->conversationId),
        ];

        try {
            $memberIds = ChatConversationMember::where('conversation_id', $this->conversationId)
                ->pluck('user_id');

            foreach ($memberIds as $mId) {
                $channels[] = new PrivateChannel('chat.user.' . $mId);
            }
        } catch (\Throwable $e) {}

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id'              => (int) $this->messageId,
            'conversation_id' => (int) $this->conversationId,
            'deleted_by'      => $this->deletedByUserId,
        ];
    }
}
