<?php

namespace App\Services\Chat;

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

class DgtChatCallService
{
    /**
     * Resilient STUN + TURN relay server configuration for WebRTC peer connection.
     * Includes port 80 and 443 fallback to bypass strict firewalls and symmetric NATs.
     */
    public function getIceServers(): array
    {
        return [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302'],
            ['urls' => 'stun:stun2.l.google.com:19302'],
            ['urls' => 'stun:stun3.l.google.com:19302'],
            ['urls' => 'stun:stun4.l.google.com:19302'],
            ['urls' => 'stun:stun.cloudflare.com:3478'],
            ['urls' => 'stun:openrelay.metered.ca:80'],
            [
                'urls' => [
                    'turn:openrelay.metered.ca:80',
                    'turn:openrelay.metered.ca:443',
                    'turn:openrelay.metered.ca:443?transport=tcp',
                    'turns:openrelay.metered.ca:443?transport=tcp',
                ],
                'username'   => 'openrelayproject',
                'credential' => 'openrelayproject',
            ],
        ];
    }

    /**
     * Start a new WebRTC voice or video call.
     */
    public function initiateCall(User $caller, int $conversationId, int $receiverId, string $callType = 'voice'): ChatCall
    {
        $conv = ChatConversation::findOrFail($conversationId);

        // Security check: both must belong to conversation
        if (!$conv->hasMember($caller->id) || !$conv->hasMember($receiverId)) {
            throw new Exception("Unauthorized to call in this conversation.");
        }

        if ($caller->id === $receiverId) {
            throw new Exception("You cannot call yourself.");
        }

        // Auto-expire stale unanswered calls older than 60 seconds so users are never falsely reported as busy
        ChatCall::whereIn('status', ['calling', 'ringing'])
            ->where('created_at', '<', now()->subSeconds(60))
            ->update([
                'status'     => 'missed',
                'ended_at'   => now(),
                'end_reason' => 'timeout',
            ]);

        // Auto-close any stale/previous unended calls between these same two users so re-calling never gets stuck
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

        // Check if receiver is currently connected to another call or ringing within last 45s
        $receiverActiveCall = ChatCall::where(function ($q) use ($receiverId) {
                $q->where('caller_id', $receiverId)->orWhere('receiver_id', $receiverId);
            })
            ->where(function ($sq) {
                $sq->where('status', 'connected')
                   ->orWhere(function ($rq) {
                       $rq->whereIn('status', ['calling', 'ringing'])
                          ->where('created_at', '>=', now()->subSeconds(45));
                   });
            })
            ->first();

        if ($receiverActiveCall) {
            throw new Exception("User is currently busy on another call.");
        }

        $call = ChatCall::create([
            'conversation_id'  => $conv->id,
            'caller_id'        => $caller->id,
            'receiver_id'      => $receiverId,
            'status'           => 'calling',
            'started_at'       => now(),
            'duration_seconds' => 0,
            'end_reason'       => ($callType === 'video') ? 'video' : null,
        ]);

        $call->load(['caller', 'receiver']);

        try {
            broadcast(new CallSignalingEvent($call, 'incoming_call', [
                'call_id'         => $call->id,
                'conversation_id' => $conv->id,
                'call_type'       => $callType,
            ], $receiverId))->toOthers();
        } catch (Exception $e) {
            Log::debug("Signaling broadcast error: " . $e->getMessage());
        }

        return $call;
    }

    /**
     * Send WebRTC signal (SDP Offer, SDP Answer, or ICE candidate).
     */
    public function sendSignal(User $user, int $callId, string $type, array $payload): ChatCallSignal
    {
        $call = ChatCall::with(['caller', 'receiver'])->findOrFail($callId);

        if ($user->id !== $call->caller_id && $user->id !== $call->receiver_id) {
            throw new Exception("Unauthorized to signal on this call.");
        }

        $targetUserId = ($user->id === $call->caller_id) ? $call->receiver_id : $call->caller_id;

        // If offer received, update status to ringing
        if ($type === 'offer' && $call->status === 'calling') {
            $call->update(['status' => 'ringing']);
        }

        $signal = ChatCallSignal::create([
            'call_id'     => $call->id,
            'sender_id'   => $user->id,
            'receiver_id' => $targetUserId,
            'type'        => $type,
            'payload'     => $payload,
            'is_read'     => false,
        ]);

        try {
            broadcast(new CallSignalingEvent($call, $type, $payload, $targetUserId, $signal->id, $user->id))->toOthers();
        } catch (Exception $e) {
            Log::debug("Signal broadcast error: " . $e->getMessage());
        }

        return $signal;
    }

    /**
     * Get pending signals for polling fallback.
     */
    public function getPendingSignals(User $user, int $callId, int $afterId = 0): array
    {
        $call = ChatCall::findOrFail($callId);
        if ($user->id !== $call->caller_id && $user->id !== $call->receiver_id) {
            throw new Exception("Unauthorized.");
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
            'type'      => $s->type,
            'payload'   => $s->payload,
            'sender_id' => $s->sender_id,
            'timestamp' => $s->created_at->toIso8601String(),
        ])->all();
    }

    /**
     * Recipient accepts call.
     */
    public function acceptCall(User $user, int $callId): ChatCall
    {
        $call = ChatCall::with(['caller', 'receiver'])->findOrFail($callId);

        if ($user->id !== $call->receiver_id) {
            throw new Exception("Only the call recipient can accept the call.");
        }

        if ($call->status === 'connected') {
            return $call;
        }

        if ($call->status === 'declined' || $call->status === 'cancelled') {
            throw new Exception("This call has already ended.");
        }

        if ($call->status === 'ended') {
            // If ended very recently due to 'replaced', allow acceptance
            if ($call->ended_at && $call->ended_at->diffInSeconds(now()) < 15 && $call->end_reason === 'replaced') {
                // allow reconnection
            } else {
                throw new Exception("This call has already ended.");
            }
        }

        $call->update([
            'status'      => 'connected',
            'answered_at' => $call->answered_at ?? now(),
        ]);

        try {
            broadcast(new CallSignalingEvent($call, 'call_accepted', [
                'call_id' => $call->id,
            ], $call->caller_id))->toOthers();
        } catch (Exception $e) {
            Log::debug("Call accept broadcast error: " . $e->getMessage());
        }

        return $call;
    }

    /**
     * Recipient declines call.
     */
    public function declineCall(User $user, int $callId, string $reason = 'declined'): ChatCall
    {
        $call = ChatCall::with(['caller', 'receiver', 'conversation'])->findOrFail($callId);

        if ($user->id !== $call->receiver_id && $user->id !== $call->caller_id) {
            throw new Exception("Unauthorized.");
        }

        $call->update([
            'status'     => 'declined',
            'ended_at'   => now(),
            'end_reason' => $reason,
        ]);

        // Record call event message in chat
        $this->recordCallMessage($call, 'declined');

        try {
            broadcast(new CallSignalingEvent($call, 'call_declined', [
                'call_id' => $call->id,
                'reason'  => $reason,
            ]))->toOthers();
        } catch (Exception $e) {
            Log::debug("Call decline broadcast error: " . $e->getMessage());
        }

        return $call;
    }

    /**
     * End active call or cancel outgoing call.
     */
    public function endCall(User $user, int $callId, string $reason = 'normal'): ChatCall
    {
        $call = ChatCall::with(['caller', 'receiver', 'conversation'])->findOrFail($callId);

        if ($user->id !== $call->caller_id && $user->id !== $call->receiver_id) {
            throw new Exception("Unauthorized.");
        }

        if (in_array($call->status, ['ended', 'declined', 'missed', 'cancelled'])) {
            return $call;
        }

        $duration = 0;
        $finalStatus = 'ended';

        if ($call->answered_at) {
            $duration = max(1, now()->diffInSeconds($call->answered_at));
            $finalStatus = 'ended';
        } else {
            // Never answered
            if ($user->id === $call->caller_id) {
                $finalStatus = 'cancelled';
            } else {
                $finalStatus = 'missed';
            }
        }

        $call->update([
            'status'           => $finalStatus,
            'ended_at'         => now(),
            'duration_seconds' => $duration,
            'end_reason'       => $reason,
        ]);

        // Log call record in conversation messages
        $this->recordCallMessage($call, $finalStatus);

        try {
            broadcast(new CallSignalingEvent($call, 'call_ended', [
                'call_id'          => $call->id,
                'status'           => $finalStatus,
                'duration_seconds' => $duration,
                'reason'           => $reason,
            ]))->toOthers();
        } catch (Exception $e) {
            Log::debug("Call end broadcast error: " . $e->getMessage());
        }

        return $call;
    }

    /**
     * Check if user has an active or ringing call.
     */
    public function getActiveCall(User $user): ?array
    {
        // Auto-expire stale unanswered calls older than 60s so page reloads never inherit zombie calls
        ChatCall::whereIn('status', ['calling', 'ringing'])
            ->where('created_at', '<', now()->subSeconds(60))
            ->update([
                'status'     => 'missed',
                'ended_at'   => now(),
                'end_reason' => 'timeout',
            ]);

        $call = ChatCall::where(function ($q) use ($user) {
                $q->where('caller_id', $user->id)->orWhere('receiver_id', $user->id);
            })
            ->where(function ($sq) {
                $sq->where(function ($cq) {
                    $cq->where('status', 'connected')
                       ->where('created_at', '>=', now()->subHours(1));
                })->orWhere(function ($rq) {
                    $rq->whereIn('status', ['calling', 'ringing'])
                       ->where('created_at', '>=', now()->subSeconds(55));
                });
            })
            ->with(['caller', 'receiver', 'conversation'])
            ->latest('id')
            ->first();

        return $call ? $this->formatCall($call, $user) : null;
    }

    /**
     * Record call event into chat message history.
     */
    protected function recordCallMessage(ChatCall $call, string $status): void
    {
        try {
            $body = match ($status) {
                'ended'     => "📞 Voice Call (" . $call->formatted_duration . ")",
                'declined'  => "📞 Declined Voice Call",
                'cancelled' => "📞 Cancelled Voice Call",
                'missed'    => "📞 Missed Voice Call",
                default     => "📞 Voice Call",
            };

            $msg = ChatMessage::create([
                'conversation_id' => $call->conversation_id,
                'user_id'         => $call->caller_id,
                'type'            => 'call_event',
                'content'         => $body,
                'call_id'         => $call->id,
            ]);

            // Update conversation last message
            $call->conversation?->update([
                'last_message_id' => $msg->id,
                'last_message_at' => now(),
            ]);

            broadcast(new ChatMessageSent($msg))->toOthers();
        } catch (Exception $e) {
            Log::debug("Failed to record call message: " . $e->getMessage());
        }
    }

    /**
     * Format call model for frontend JSON responses.
     */
    public function formatCall(ChatCall $call, User $viewer): array
    {
        $call->loadMissing(['caller', 'receiver']);
        $isCaller = ($call->caller_id === $viewer->id);
        $otherUser = $isCaller ? $call->receiver : $call->caller;
        $caller = $call->caller;
        $receiver = $call->receiver;

        return [
            'id'                 => $call->id,
            'conversation_id'    => $call->conversation_id,
            'caller_id'          => (int) $call->caller_id,
            'receiver_id'        => (int) $call->receiver_id,
            'call_type'          => ($call->end_reason === 'video') ? 'video' : 'voice',
            'direction'          => $isCaller ? 'outgoing' : 'incoming',
            'status'             => $call->status,
            'is_caller'          => $isCaller,
            'duration_seconds'   => $call->duration_seconds,
            'formatted_duration' => $call->formatted_duration,
            'started_at'         => $call->started_at?->toIso8601String(),
            'answered_at'        => $call->answered_at?->toIso8601String(),
            'ended_at'           => $call->ended_at?->toIso8601String(),
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
            'ice_servers'        => $this->getIceServers(),
        ];
    }
}
