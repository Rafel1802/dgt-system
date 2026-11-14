<?php

namespace App\Services\Chat;

use App\Events\Chat\CallLifecycleEvent;
use App\Events\Chat\CallSignalingEvent;
use App\Events\Chat\ChatMessageSent;
use App\Models\Chat\ChatCall;
use App\Models\Chat\ChatCallSignal;
use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatMessage;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DgtChatCallService
{
    /**
     * Build STUN + TURN ICE server configuration for WebRTC peer connections.
     * Supports Coturn HMAC-SHA1 time-limited credentials, static Managed TURN
     * credentials, and configurable fallback relays without exposing server secrets.
     */
    public function getIceServers(?User $user = null): array
    {
        $servers = [];

        $stunUrls = config('webrtc.stun_urls', [
            'stun:stun.l.google.com:19302',
            'stun:stun1.l.google.com:19302',
            'stun:stun2.l.google.com:19302',
            'stun:stun.cloudflare.com:3478',
        ]);

        foreach ($stunUrls as $stunUrl) {
            if (!empty($stunUrl)) {
                $servers[] = ['urls' => $stunUrl];
            }
        }

        $turnUrls = config('webrtc.turn.urls', []);
        $turnSecret = config('webrtc.turn.secret');
        $turnUsername = config('webrtc.turn.username');
        $turnCredential = config('webrtc.turn.credential');
        $ttl = max(300, (int) config('webrtc.turn.ttl', 3600));

        if (!empty($turnUrls) && !empty($turnSecret)) {
            $expiresAt = time() + $ttl;
            $ephemeralUser = $expiresAt . ':user_' . ($user?->id ?? 'guest');
            $ephemeralCred = base64_encode(hash_hmac('sha1', $ephemeralUser, (string) $turnSecret, true));

            $servers[] = [
                'urls'       => array_values($turnUrls),
                'username'   => $ephemeralUser,
                'credential' => $ephemeralCred,
            ];
        } elseif (!empty($turnUrls) && !empty($turnUsername) && !empty($turnCredential)) {
            $servers[] = [
                'urls'       => array_values($turnUrls),
                'username'   => (string) $turnUsername,
                'credential' => (string) $turnCredential,
            ];
        }

        return $servers;
    }

    /**
     * Full client ICE configuration payload.
     */
    public function getIceConfiguration(?User $user = null): array
    {
        return [
            'iceServers'         => $this->getIceServers($user),
            'iceTransportPolicy' => config('webrtc.call.ice_transport_policy', 'all'),
            'ttl'                => (int) config('webrtc.turn.ttl', 3600),
            'ringTimeoutSeconds' => (int) config('webrtc.call.ring_timeout_seconds', 45),
        ];
    }

    /**
     * Start a new WebRTC 1-to-1 voice or video call.
     */
    public function initiateCall(
        User $caller,
        int $conversationId,
        int $receiverId,
        string $callType = 'voice',
        array $metadata = []
    ): ChatCall {
        $normalizedType = ($callType === 'video') ? 'video' : 'voice';
        $conv = ChatConversation::findOrFail($conversationId);

        if (!$conv->hasMember($caller->id) || !$conv->hasMember($receiverId)) {
            throw new RuntimeException('Unauthorized to call in this conversation.', 403);
        }

        if ($caller->id === $receiverId) {
            throw new RuntimeException('You cannot call yourself.', 422);
        }

        $ringTimeout = max(30, (int) config('webrtc.call.ring_timeout_seconds', 45));

        $call = DB::transaction(function () use ($caller, $conv, $receiverId, $normalizedType, $metadata, $ringTimeout) {
            // Auto-expire stale unanswered calls globally
            ChatCall::whereIn('status', ['calling', 'ringing'])
                ->where('created_at', '<', now()->subSeconds($ringTimeout + 15))
                ->update([
                    'status'     => 'missed',
                    'ended_at'   => now(),
                    'end_reason' => 'timeout',
                ]);

            // Auto-close any previous unended calls between these same two users
            ChatCall::where(function ($q) use ($caller, $receiverId) {
                    $q->where(function ($sq) use ($caller, $receiverId) {
                        $sq->where('caller_id', $caller->id)->where('receiver_id', $receiverId);
                    })->orWhere(function ($sq) use ($caller, $receiverId) {
                        $sq->where('caller_id', $receiverId)->where('receiver_id', $caller->id);
                    });
                })
                ->whereIn('status', ['calling', 'ringing', 'connected'])
                ->update([
                    'status'     => 'ended',
                    'ended_at'   => now(),
                    'end_reason' => 'replaced',
                ]);

            // Check if receiver is currently busy on a call with someone else
            $receiverActiveCall = ChatCall::where(function ($q) use ($receiverId) {
                    $q->where('caller_id', $receiverId)->orWhere('receiver_id', $receiverId);
                })
                ->where(function ($sq) use ($ringTimeout) {
                    $sq->where('status', 'connected')
                       ->orWhere(function ($rq) use ($ringTimeout) {
                           $rq->whereIn('status', ['calling', 'ringing'])
                              ->where('created_at', '>=', now()->subSeconds($ringTimeout));
                       });
                })
                ->first();

            if ($receiverActiveCall) {
                $busyAttributes = [
                    'conversation_id'  => $conv->id,
                    'caller_id'        => $caller->id,
                    'receiver_id'      => $receiverId,
                    'status'           => 'busy',
                    'started_at'       => now(),
                    'ended_at'         => now(),
                    'duration_seconds' => 0,
                    'end_reason'       => 'busy',
                ];
                if (Schema::hasColumn('chat_calls', 'call_type')) {
                    $busyAttributes['call_type'] = $normalizedType;
                }
                $busyCall = ChatCall::create($busyAttributes);
                $busyCall->load(['caller', 'receiver']);

                $this->emitCallEvent(
                    $busyCall,
                    'call_busy',
                    'call.busy',
                    ['call_id' => $busyCall->id, 'call_type' => $normalizedType, 'reason' => 'busy'],
                    $caller->id,
                    null,
                    $receiverId
                );

                throw new RuntimeException('User is currently busy on another call.', 409);
            }

            $attributes = [
                'conversation_id'  => $conv->id,
                'caller_id'        => $caller->id,
                'receiver_id'      => $receiverId,
                'status'           => 'calling',
                'started_at'       => now(),
                'duration_seconds' => 0,
                'end_reason'       => null,
            ];

            if (Schema::hasColumn('chat_calls', 'call_type')) {
                $attributes['call_type'] = $normalizedType;
            } else {
                $attributes['end_reason'] = ($normalizedType === 'video') ? 'video' : null;
            }

            if (Schema::hasColumn('chat_calls', 'metadata') && !empty($metadata)) {
                $attributes['metadata'] = $metadata;
            }

            return ChatCall::create($attributes);
        });

        $call->load(['caller', 'receiver']);

        $this->emitCallEvent(
            $call,
            'incoming_call',
            'call.invited',
            [
                'call_id'         => $call->id,
                'conversation_id' => $conv->id,
                'call_type'       => $normalizedType,
                'ice_servers'     => $this->getIceServers($call->receiver),
            ],
            $receiverId,
            null,
            $caller->id
        );

        return $call;
    }

    /**
     * Recipient device acknowledges incoming call alert -> transitions to 'ringing'.
     */
    public function markRinging(User $user, int $callId): ChatCall
    {
        $call = ChatCall::with(['caller', 'receiver'])->findOrFail($callId);

        if ($user->id !== $call->receiver_id) {
            throw new RuntimeException('Only the call recipient can acknowledge ringing.', 403);
        }

        if ($call->status === 'calling') {
            $call->update(['status' => 'ringing']);
        }

        $this->emitCallEvent(
            $call,
            'call_ringing',
            'call.ringing',
            ['call_id' => $call->id, 'status' => $call->status],
            $call->caller_id,
            null,
            $user->id
        );

        return $call;
    }

    /**
     * Send WebRTC signal (SDP Offer, SDP Answer, ICE candidate, or media state update).
     */
    public function sendSignal(User $user, int $callId, string $type, array $payload): ChatCallSignal
    {
        $call = ChatCall::with(['caller', 'receiver'])->findOrFail($callId);

        if ($user->id !== $call->caller_id && $user->id !== $call->receiver_id) {
            throw new RuntimeException('Unauthorized to signal on this call.', 403);
        }

        if (in_array($call->status, ['ended', 'declined', 'missed', 'cancelled', 'busy'], true) && $type !== 'end') {
            throw new RuntimeException('Cannot signal on an inactive call.', 422);
        }

        $normalizedType = ($type === 'ice-candidate') ? 'ice_candidate' : $type;
        $targetUserId = ($user->id === $call->caller_id) ? $call->receiver_id : $call->caller_id;

        if ($normalizedType === 'offer' && $call->status === 'calling') {
            $call->update(['status' => 'ringing']);
        }

        $signal = ChatCallSignal::create([
            'call_id'     => $call->id,
            'sender_id'   => $user->id,
            'receiver_id' => $targetUserId,
            'type'        => $normalizedType,
            'payload'     => $payload,
            'is_read'     => false,
        ]);

        $canonicalEvent = match ($normalizedType) {
            'offer'         => 'call.offer',
            'answer'        => 'call.answer',
            'ice_candidate' => 'call.ice_candidate',
            'failed'        => 'call.failed',
            default         => 'call.' . str_replace('-', '_', $normalizedType),
        };

        $this->emitCallEvent(
            $call,
            $normalizedType,
            $canonicalEvent,
            $payload,
            $targetUserId,
            $signal->id,
            $user->id
        );

        return $signal;
    }

    /**
     * Get pending signals for polling fallback / reconnection sync.
     */
    public function getPendingSignals(User $user, int $callId, int $afterId = 0): array
    {
        $call = ChatCall::findOrFail($callId);
        if ($user->id !== $call->caller_id && $user->id !== $call->receiver_id) {
            throw new RuntimeException('Unauthorized.', 403);
        }

        $signals = ChatCallSignal::where('call_id', $callId)
            ->where('receiver_id', $user->id)
            ->where('id', '>', $afterId)
            ->orderBy('id', 'asc')
            ->get();

        if ($signals->isNotEmpty()) {
            ChatCallSignal::whereIn('id', $signals->pluck('id'))
                ->update(['is_read' => true]);
        }

        return $signals->map(fn($s) => [
            'id'        => $s->id,
            'type'      => ($s->type === 'ice-candidate') ? 'ice_candidate' : $s->type,
            'payload'   => $s->payload,
            'sender_id' => $s->sender_id,
            'timestamp' => $s->created_at->toIso8601String(),
        ])->all();
    }

    /**
     * Recipient accepts incoming call.
     */
    public function acceptCall(User $user, int $callId): ChatCall
    {
        $call = ChatCall::with(['caller', 'receiver'])->findOrFail($callId);

        if ($user->id !== $call->receiver_id) {
            throw new RuntimeException('Only the call recipient can accept the call.', 403);
        }

        if ($call->status === 'connected') {
            return $call;
        }

        if (in_array($call->status, ['declined', 'cancelled', 'missed', 'busy'], true)) {
            throw new RuntimeException('This call is no longer active.', 422);
        }

        if ($call->status === 'ended') {
            if (!($call->ended_at && $call->ended_at->diffInSeconds(now()) < 15 && $call->end_reason === 'replaced')) {
                throw new RuntimeException('This call has already ended.', 422);
            }
        }

        $call->update([
            'status'      => 'connected',
            'answered_at' => $call->answered_at ?? now(),
        ]);

        // Notify caller that recipient accepted
        $this->emitCallEvent(
            $call,
            'call_accepted',
            'call.accepted',
            ['call_id' => $call->id, 'call_type' => $call->isVideo() ? 'video' : 'voice'],
            $call->caller_id,
            null,
            $user->id
        );

        // Also notify recipient's other open tabs/devices so they stop ringing immediately
        $this->emitCallEvent(
            $call,
            'answered_elsewhere',
            'call.answered_elsewhere',
            ['call_id' => $call->id],
            $user->id,
            null,
            $user->id
        );

        return $call;
    }

    /**
     * Recipient rejects / declines incoming call.
     */
    public function declineCall(User $user, int $callId, string $reason = 'declined'): ChatCall
    {
        $call = ChatCall::with(['caller', 'receiver', 'conversation'])->findOrFail($callId);

        if ($user->id !== $call->receiver_id && $user->id !== $call->caller_id) {
            throw new RuntimeException('Unauthorized.', 403);
        }

        if (in_array($call->status, ['ended', 'declined', 'missed', 'cancelled'], true)) {
            return $call;
        }

        $isBusy = ($reason === 'busy');
        $finalStatus = $isBusy ? 'busy' : 'declined';

        $call->update([
            'status'     => $finalStatus,
            'ended_at'   => now(),
            'end_reason' => $reason,
        ]);

        $this->recordCallMessage($call, $finalStatus === 'busy' ? 'declined' : 'declined');

        $this->emitCallEvent(
            $call,
            $isBusy ? 'call_busy' : 'call_declined',
            $isBusy ? 'call.busy' : 'call.rejected',
            [
                'call_id' => $call->id,
                'reason'  => $reason,
            ],
            null,
            null,
            $user->id
        );

        return $call;
    }

    /**
     * Caller cancels outgoing call before recipient answers.
     */
    public function cancelCall(User $user, int $callId, string $reason = 'cancelled'): ChatCall
    {
        $call = ChatCall::with(['caller', 'receiver', 'conversation'])->findOrFail($callId);

        if ($user->id !== $call->caller_id && $user->id !== $call->receiver_id) {
            throw new RuntimeException('Unauthorized.', 403);
        }

        if ($call->status === 'connected' || $call->answered_at) {
            return $this->endCall($user, $callId, 'normal');
        }

        if (in_array($call->status, ['ended', 'declined', 'missed', 'cancelled'], true)) {
            return $call;
        }

        $call->update([
            'status'           => 'cancelled',
            'ended_at'         => now(),
            'duration_seconds' => 0,
            'end_reason'       => $reason ?: 'cancelled',
        ]);

        $this->recordCallMessage($call, 'cancelled');

        $this->emitCallEvent(
            $call,
            'call_cancelled',
            'call.cancelled',
            [
                'call_id' => $call->id,
                'status'  => 'cancelled',
                'reason'  => $reason ?: 'cancelled',
            ],
            null,
            null,
            $user->id
        );

        return $call;
    }

    /**
     * Mark unanswered ringing call as timed out / missed.
     */
    public function timeoutCall(User $user, int $callId): ChatCall
    {
        $call = ChatCall::with(['caller', 'receiver', 'conversation'])->findOrFail($callId);

        if ($user->id !== $call->caller_id && $user->id !== $call->receiver_id) {
            throw new RuntimeException('Unauthorized.', 403);
        }

        if (in_array($call->status, ['connected', 'ended', 'declined', 'missed', 'cancelled'], true)) {
            return $call;
        }

        $call->update([
            'status'           => 'missed',
            'ended_at'         => now(),
            'duration_seconds' => 0,
            'end_reason'       => 'timeout',
        ]);

        $this->recordCallMessage($call, 'missed');

        $this->emitCallEvent(
            $call,
            'call_timeout',
            'call.timeout',
            [
                'call_id' => $call->id,
                'status'  => 'missed',
                'reason'  => 'timeout',
            ],
            null,
            null,
            $user->id
        );

        return $call;
    }

    /**
     * End active call or cancel/miss unanswered call.
     */
    public function endCall(User $user, int $callId, string $reason = 'normal'): ChatCall
    {
        $call = ChatCall::with(['caller', 'receiver', 'conversation'])->findOrFail($callId);

        if ($user->id !== $call->caller_id && $user->id !== $call->receiver_id) {
            throw new RuntimeException('Unauthorized.', 403);
        }

        if (in_array($call->status, ['ended', 'declined', 'missed', 'cancelled'], true)) {
            return $call;
        }

        if ($reason === 'timeout' && !$call->answered_at) {
            return $this->timeoutCall($user, $callId);
        }

        $duration = 0;
        if ($call->answered_at) {
            $duration = max(1, (int) abs(now()->diffInSeconds($call->answered_at)));
            $finalStatus = 'ended';
        } else {
            $finalStatus = ($user->id === $call->caller_id) ? 'cancelled' : 'missed';
        }

        $call->update([
            'status'           => $finalStatus,
            'ended_at'         => now(),
            'duration_seconds' => $duration,
            'end_reason'       => ($reason === 'failed') ? 'failed' : $reason,
        ]);

        $this->recordCallMessage($call, $finalStatus);

        $canonicalEvent = match (true) {
            $reason === 'failed'        => 'call.failed',
            $finalStatus === 'cancelled'=> 'call.cancelled',
            $finalStatus === 'missed'   => 'call.timeout',
            default                     => 'call.ended',
        };

        $this->emitCallEvent(
            $call,
            'call_ended',
            $canonicalEvent,
            [
                'call_id'          => $call->id,
                'status'           => $finalStatus,
                'duration_seconds' => $duration,
                'reason'           => $reason,
            ],
            null,
            null,
            $user->id
        );

        return $call;
    }

    /**
     * Check if user has an active or ringing call.
     */
    public function getActiveCall(User $user): ?array
    {
        $ringTimeout = max(30, (int) config('webrtc.call.ring_timeout_seconds', 45));

        ChatCall::whereIn('status', ['calling', 'ringing'])
            ->where('created_at', '<', now()->subSeconds($ringTimeout + 15))
            ->update([
                'status'     => 'missed',
                'ended_at'   => now(),
                'end_reason' => 'timeout',
            ]);

        $call = ChatCall::where(function ($q) use ($user) {
                $q->where('caller_id', $user->id)->orWhere('receiver_id', $user->id);
            })
            ->where(function ($sq) use ($ringTimeout) {
                $sq->where(function ($cq) {
                    $cq->where('status', 'connected')
                       ->where('created_at', '>=', now()->subHours(2));
                })->orWhere(function ($rq) use ($ringTimeout) {
                    $rq->whereIn('status', ['calling', 'ringing'])
                       ->where('created_at', '>=', now()->subSeconds($ringTimeout + 10));
                });
            })
            ->with(['caller', 'receiver', 'conversation'])
            ->latest('id')
            ->first();

        return $call ? $this->formatCall($call, $user) : null;
    }

    /**
     * Retrieve call history for the authenticated user (optionally scoped to a conversation).
     */
    public function getCallHistory(User $user, int $limit = 50, ?int $conversationId = null): array
    {
        $limit = min(max(1, $limit), 100);

        $query = ChatCall::where(function ($q) use ($user) {
                $q->where('caller_id', $user->id)
                  ->orWhere('receiver_id', $user->id);
            })
            ->with(['caller', 'receiver']);

        if ($conversationId) {
            $query->where('conversation_id', $conversationId);
        }

        return $query->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (ChatCall $call) => $this->formatCall($call, $user, false))
            ->values()
            ->all();
    }

    /**
     * Emit both legacy `.call.signal` and canonical `.call.*` private-channel events.
     */
    protected function emitCallEvent(
        ChatCall $call,
        string $legacyType,
        string $canonicalEvent,
        array $payload,
        ?int $targetUserId = null,
        ?int $signalId = null,
        ?int $senderId = null
    ): void {
        try {
            broadcast(new CallSignalingEvent(
                $call,
                $legacyType,
                array_merge($payload, ['canonical_event' => $canonicalEvent]),
                $targetUserId,
                $signalId,
                $senderId
            ))->toOthers();
        } catch (Exception $e) {
            Log::warning('WebRTC CallSignalingEvent broadcast warning: ' . $e->getMessage());
        }

        try {
            broadcast(new CallLifecycleEvent(
                $call,
                $canonicalEvent,
                $payload,
                $targetUserId,
                $signalId,
                $senderId
            ))->toOthers();
        } catch (Exception $e) {
            Log::warning('WebRTC CallLifecycleEvent broadcast warning: ' . $e->getMessage());
        }
    }

    /**
     * Record call event into chat message history (distinguishes Voice vs Video Call).
     */
    protected function recordCallMessage(ChatCall $call, string $status): void
    {
        try {
            $isVideo = $call->isVideo();
            $icon = $isVideo ? '📹' : '📞';
            $label = $isVideo ? 'Video Call' : 'Voice Call';

            $body = match ($status) {
                'ended'     => "{$icon} {$label} (" . $call->formatted_duration . ")",
                'declined'  => "{$icon} Declined {$label}",
                'cancelled' => "{$icon} Cancelled {$label}",
                'missed'    => "{$icon} Missed {$label}",
                'busy'      => "{$icon} Busy {$label}",
                default     => "{$icon} {$label}",
            };

            $msg = ChatMessage::create([
                'conversation_id' => $call->conversation_id,
                'user_id'         => $call->caller_id,
                'type'            => 'call_event',
                'content'         => $body,
                'call_id'         => $call->id,
            ]);

            $call->conversation?->update([
                'last_message_id' => $msg->id,
                'last_message_at' => now(),
            ]);

            broadcast(new ChatMessageSent($msg))->toOthers();
        } catch (Exception $e) {
            Log::debug('Failed to record call message: ' . $e->getMessage());
        }
    }

    /**
     * Format call model for frontend JSON responses.
     */
    public function formatCall(ChatCall $call, User $viewer, bool $includeIce = true): array
    {
        $call->loadMissing(['caller', 'receiver']);
        $isCaller = ((int) $call->caller_id === (int) $viewer->id);
        $otherUser = $isCaller ? $call->receiver : $call->caller;
        $caller = $call->caller;
        $receiver = $call->receiver;
        $callType = $call->isVideo() ? 'video' : 'voice';

        $formatted = [
            'id'                 => $call->id,
            'conversation_id'    => $call->conversation_id,
            'caller_id'          => (int) $call->caller_id,
            'receiver_id'        => (int) $call->receiver_id,
            'call_type'          => $callType,
            'is_video'           => $call->isVideo(),
            'direction'          => $isCaller ? 'outgoing' : 'incoming',
            'status'             => $call->status,
            'end_reason'         => $call->end_reason,
            'is_caller'          => $isCaller,
            'duration_seconds'   => (int) $call->duration_seconds,
            'formatted_duration' => $call->formatted_duration,
            'started_at'         => $call->started_at?->toIso8601String(),
            'answered_at'        => $call->answered_at?->toIso8601String(),
            'ended_at'           => $call->ended_at?->toIso8601String(),
            'created_at_human'   => $call->created_at?->diffForHumans(),
            'caller'             => [
                'id'         => $caller?->id,
                'name'       => $caller?->name ?? 'User',
                'avatar_url' => $caller?->avatar ? asset('storage/' . $caller->avatar) : null,
                'is_online'  => $caller?->isOnline() ?? false,
            ],
            'receiver'           => [
                'id'         => $receiver?->id,
                'name'       => $receiver?->name ?? 'User',
                'avatar_url' => $receiver?->avatar ? asset('storage/' . $receiver->avatar) : null,
                'is_online'  => $receiver?->isOnline() ?? false,
            ],
            'other_user'         => [
                'id'         => $otherUser?->id,
                'name'       => $otherUser?->name ?? 'User',
                'avatar_url' => $otherUser?->avatar ? asset('storage/' . $otherUser->avatar) : null,
                'is_online'  => $otherUser?->isOnline() ?? false,
            ],
        ];

        if ($includeIce) {
            $formatted['ice_servers'] = $this->getIceServers($viewer);
        }

        return $formatted;
    }
}
