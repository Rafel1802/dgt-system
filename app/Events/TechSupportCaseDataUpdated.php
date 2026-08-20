<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TechSupportCaseDataUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $caseId;
    public $actorName;
    public $message;

    public function __construct(int $caseId, string $actorName, string $message)
    {
        $this->caseId = $caseId;
        $this->actorName = $actorName;
        $this->message = $message;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('tech-support');
    }

    public function broadcastAs(): string
    {
        return 'TechSupportCaseDataUpdated';
    }
}
