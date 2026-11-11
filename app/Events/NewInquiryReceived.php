<?php

namespace App\Events;

use App\Models\WebsiteInquiry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewInquiryReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WebsiteInquiry $inquiry
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('websites'),
            new PrivateChannel('inquiries'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'inquiry.received';
    }

    public function broadcastWith(): array
    {
        $this->inquiry->loadMissing('website');

        return [
            'id'              => $this->inquiry->id,
            'website_id'      => $this->inquiry->website_id,
            'website_name'    => $this->inquiry->website?->name ?? 'Unknown Website',
            'website_domain'  => $this->inquiry->website?->domain ?? '',
            'inquiry_type'    => $this->inquiry->inquiry_type,
            'type_label'      => $this->inquiry->type_label,
            'status'          => $this->inquiry->status,
            'status_label'    => $this->inquiry->status_label,
            'form_name'       => $this->inquiry->form_name,
            'customer_name'   => $this->inquiry->customer_name ?? 'Anonymous',
            'customer_email'  => $this->inquiry->customer_email,
            'customer_phone'  => $this->inquiry->customer_phone,
            'customer_company'=> $this->inquiry->customer_company,
            'subject'         => $this->inquiry->subject,
            'message_snippet' => mb_strimwidth($this->inquiry->message ?? '', 0, 120, '...'),
            'product_service' => $this->inquiry->product_service,
            'source_url'      => $this->inquiry->source_url,
            'submitted_at'    => $this->inquiry->submitted_at?->diffForHumans() ?? 'Just now',
            'created_at'      => $this->inquiry->created_at?->toIso8601String(),
        ];
    }
}
