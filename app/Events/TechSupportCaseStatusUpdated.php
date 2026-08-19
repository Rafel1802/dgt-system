<?php

namespace App\Events;

use App\Models\TechSupportCase;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TechSupportCaseStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $caseId;
    public $status;
    public $updaterId;

    public function __construct(TechSupportCase $case, $updaterId)
    {
        $this->caseId = $case->id;
        $this->status = $case->status;
        $this->updaterId = $updaterId;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('tech-support');
    }

    public function broadcastAs(): string
    {
        return 'TechSupportCaseStatusUpdated';
    }
}
