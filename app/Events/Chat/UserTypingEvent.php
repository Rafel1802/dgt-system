<?php

namespace App\Events\Chat;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTypingEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $userId,
        public string $userName,
        public bool $isTyping
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.conversation.' . $this->conversationId),
        ];

        try {
            $memberUserIds = \App\Models\Chat\ChatConversationMember::where('conversation_id', $this->conversationId)
                ->where('user_id', '!=', $this->userId)
                ->pluck('user_id');

            foreach ($memberUserIds as $memberId) {
                $channels[] = new PrivateChannel('chat.user.' . $memberId);
            }
        } catch (\Throwable $e) {}

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id'         => $this->userId,
            'user_name'       => $this->userName,
            'is_typing'       => $this->isTyping,
        ];
    }
}
