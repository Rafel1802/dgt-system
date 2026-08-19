<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerHandlerUpdatedLive implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $customerId;
    public $newHandlerName;
    public $actorName;
    public $teamName;
    public $customerType; // 'lead', 'customer', 'ebay', 'shipment', 'tech-support'

    public function __construct(
        $customerId,
        $newHandlerName,
        $actorName,
        $teamName,
        $customerType
    ) {
        $this->customerId = $customerId;
        $this->newHandlerName = $newHandlerName;
        $this->actorName = $actorName;
        $this->teamName = $teamName;
        $this->customerType = $customerType;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('crm.customer.' . $this->customerType . '.' . $this->customerId);
    }

    public function broadcastAs(): string
    {
        return 'CustomerHandlerUpdatedLive';
    }
}
