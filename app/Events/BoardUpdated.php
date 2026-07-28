<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BoardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $boardId,
        public string $boardSlug,
        public string $action = 'updated',
        public ?int $cardId = null,
        public ?int $actorId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("boards.{$this->boardId}")];
    }

    public function broadcastAs(): string
    {
        return 'board.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'board_id' => $this->boardId,
            'board_slug' => $this->boardSlug,
            'action' => $this->action,
            'card_id' => $this->cardId,
            'actor_id' => $this->actorId,
            'created_at' => now()->toISOString(),
        ];
    }
}
