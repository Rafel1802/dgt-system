<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerDataUpdatedLive implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $customerId;
    public $message;
    public $customerType; // 'lead', 'customer', 'ebay', 'logistic', 'tech'
    public $actorName;
    public $teamName;

    public function __construct(
        $customerId,
        $customerType,
        $message,
        $actorName = null,
        $teamName = null
    ) {
        $this->customerId = $customerId;
        $this->customerType = $customerType;
        $this->message = $message;
        $this->actorName = $actorName;
        $this->teamName = $teamName;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('crm.customer.' . $this->customerType . '.' . $this->customerId),
            new PrivateChannel('crm.global'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CustomerDataUpdatedLive';
    }
}
