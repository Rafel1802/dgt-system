<?php

namespace App\Events;

use App\Models\Website;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebsiteUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $websiteId;
    public $status;
    public $progressPercent;
    public $userId;

    public function __construct(Website $website)
    {
        $this->websiteId = $website->id;
        $this->status = $website->status;
        $this->progressPercent = $website->progress_percent;
        $this->userId = auth()->id();
    }

    public function broadcastOn()
    {
        return new PrivateChannel('websites');
    }

    public function broadcastAs()
    {
        return 'WebsiteUpdated';
    }
}
