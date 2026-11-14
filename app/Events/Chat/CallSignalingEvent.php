<?php

namespace App\Events\Chat;

use App\Models\Chat\ChatCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignalingEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatCall $call,
        public string $type, // 'incoming_call', 'offer', 'answer', 'ice_candidate', 'call_accepted', 'call_declined', 'call_ended', 'status'
        public array $payload = [],
        public ?int $targetUserId = null,
        public ?int $signalId = null,
        public ?int $senderId = null
    ) {}

    public function broadcastOn(): array
    {
        // When a signal is targeted at a specific peer (offer, answer, ice-candidate, incoming_call, call_accepted),
        // only deliver it to that target user so the sender never echoes their own WebRTC SDP/ICE back to themselves.
        if ($this->targetUserId) {
            return [
                new PrivateChannel('chat.user.' . $this->targetUserId),
            ];
        }

        return [
            new PrivateChannel('chat.conversation.' . $this->call->conversation_id),
            new PrivateChannel('chat.user.' . $this->call->receiver_id),
            new PrivateChannel('chat.user.' . $this->call->caller_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.signal';
    }

    public function broadcastWith(): array
    {
        $this->call->loadMissing(['caller', 'receiver']);
        $caller = $this->call->caller;
        $receiver = $this->call->receiver;

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
        $callType = $this->call->isVideo() ? 'video' : 'voice';

        // Guard against Pusher's 10KB event limit for large video SDP offers/answers;
        // full unmodified SDP is stored in chat_call_signals and fetched immediately by signal_id.
        $safePayload = $this->payload;
        if (isset($safePayload['sdp']) && strlen((string) json_encode($safePayload)) > 6200) {
            $safePayload = [
                'sdp_in_db'       => true,
                'signal_id'       => $this->signalId,
                'type'            => $safePayload['type'] ?? $this->type,
                'video_enabled'   => $safePayload['video_enabled'] ?? ($callType === 'video'),
                'canonical_event' => $safePayload['canonical_event'] ?? null,
            ];
        }

        return [
            'call_id'         => $this->call->id,
            'signal_id'       => $this->signalId,
            'sender_id'       => $senderId,
            'target_user_id'  => $this->targetUserId,
            'conversation_id' => $this->call->conversation_id,
            'caller_id'       => $this->call->caller_id,
            'receiver_id'     => $this->call->receiver_id,
            'call_type'       => $callType,
            'type'            => $this->type,
            'payload'         => $safePayload,
            'caller'          => $callerData,
            'receiver'        => $receiverData,
            'status'          => $this->call->status,
            'duration_seconds'=> $this->call->duration_seconds,
            'timestamp'       => now()->toIso8601String(),
            'call'            => [
                'id'              => $this->call->id,
                'conversation_id' => $this->call->conversation_id,
                'caller_id'       => $this->call->caller_id,
                'receiver_id'     => $this->call->receiver_id,
                'call_type'       => $callType,
                'status'          => $this->call->status,
                'duration_seconds'=> $this->call->duration_seconds,
                'caller'          => $callerData,
                'receiver'        => $receiverData,
            ],
        ];
    }
}
