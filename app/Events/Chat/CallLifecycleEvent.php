<?php

namespace App\Events\Chat;

use App\Models\Chat\ChatCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallLifecycleEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatCall $call,
        public string $eventName, // e.g. 'call.invited', 'call.ringing', 'call.accepted', 'call.rejected', 'call.cancelled', 'call.offer', 'call.answer', 'call.ice_candidate', 'call.ended', 'call.busy', 'call.timeout', 'call.failed'
        public array $payload = [],
        public ?int $targetUserId = null,
        public ?int $signalId = null,
        public ?int $senderId = null
    ) {}

    public function broadcastOn(): array
    {
        if ($this->targetUserId) {
            return [
                new PrivateChannel('chat.user.' . $this->targetUserId),
            ];
        }

        return [
            new PrivateChannel('chat.user.' . $this->call->receiver_id),
            new PrivateChannel('chat.user.' . $this->call->caller_id),
        ];
    }

    public function broadcastAs(): string
    {
        return $this->eventName;
    }

    public function broadcastWith(): array
    {
        $this->call->loadMissing(['caller', 'receiver']);
        $caller = $this->call->caller;
        $receiver = $this->call->receiver;
        $callType = $this->call->isVideo() ? 'video' : 'voice';

        $callerData = [
            'id'         => $caller?->id,
            'name'       => $caller?->name ?? 'User',
            'avatar_url' => $caller?->avatar ? asset('storage/' . $caller->avatar) : null,
        ];
        $receiverData = [
            'id'         => $receiver?->id,
            'name'       => $receiver?->name ?? 'User',
            'avatar_url' => $receiver?->avatar ? asset('storage/' . $receiver->avatar) : null,
        ];

        $senderId = $this->senderId ?? (($this->targetUserId === $this->call->caller_id) ? $this->call->receiver_id : $this->call->caller_id);

        // Guard against Pusher's 10KB event limit for large video SDP offers/answers.
        $safePayload = $this->payload;
        if (isset($safePayload['sdp']) && strlen((string) json_encode($safePayload)) > 6200) {
            $safePayload = [
                'sdp_in_db'     => true,
                'signal_id'     => $this->signalId,
                'type'          => $safePayload['type'] ?? $this->eventName,
                'video_enabled' => $safePayload['video_enabled'] ?? ($callType === 'video'),
            ];
        }

        return [
            'event'            => $this->eventName,
            'call_id'          => $this->call->id,
            'signal_id'        => $this->signalId,
            'sender_id'        => $senderId,
            'target_user_id'   => $this->targetUserId,
            'conversation_id'  => $this->call->conversation_id,
            'caller_id'        => $this->call->caller_id,
            'receiver_id'      => $this->call->receiver_id,
            'call_type'        => $callType,
            'type'             => $this->eventName,
            'payload'          => $safePayload,
            'caller'           => $callerData,
            'receiver'         => $receiverData,
            'status'           => $this->call->status,
            'duration_seconds' => $this->call->duration_seconds,
            'timestamp'        => now()->toIso8601String(),
            'call'             => [
                'id'               => $this->call->id,
                'conversation_id'  => $this->call->conversation_id,
                'caller_id'        => $this->call->caller_id,
                'receiver_id'      => $this->call->receiver_id,
                'call_type'        => $callType,
                'status'           => $this->call->status,
                'duration_seconds' => $this->call->duration_seconds,
                'caller'           => $callerData,
                'receiver'         => $receiverData,
            ],
        ];
    }
}
