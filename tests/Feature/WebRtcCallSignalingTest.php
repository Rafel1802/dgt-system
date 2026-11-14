<?php

namespace Tests\Feature;

use App\Events\Chat\CallLifecycleEvent;
use App\Events\Chat\CallSignalingEvent;
use App\Models\Chat\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebRtcCallSignalingTest extends TestCase
{
    use RefreshDatabase;

    protected User $caller;
    protected User $callee;
    protected User $outsider;
    protected int $conversationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caller = User::factory()->create([
            'name'         => 'Caller Alpha',
            'is_active'    => true,
            'can_use_chat' => true,
        ]);

        $this->callee = User::factory()->create([
            'name'         => 'Callee Beta',
            'is_active'    => true,
            'can_use_chat' => true,
        ]);

        $this->outsider = User::factory()->create([
            'name'         => 'Outsider Gamma',
            'is_active'    => true,
            'can_use_chat' => true,
        ]);

        $convResponse = $this->actingAs($this->caller)->postJson('/chat/conversations/private', [
            'user_id' => $this->callee->id,
        ]);
        $convResponse->assertOk();
        $this->conversationId = (int) $convResponse->json('conversation.id');
    }

    public function test_ice_config_generates_stun_and_expiring_hmac_turn_credentials(): void
    {
        config([
            'webrtc.stun_urls' => ['stun:stun.l.google.com:19302'],
            'webrtc.turn.urls' => [
                'turn:turn.kiuq.example.com:3478?transport=udp',
                'turns:turn.kiuq.example.com:5349?transport=tcp',
            ],
            'webrtc.turn.secret' => 'super-secret-coturn-hmac-key',
            'webrtc.turn.ttl'    => 3600,
        ]);

        $response = $this->actingAs($this->caller)->getJson('/chat/calls/ice-config');
        $response->assertOk()->assertJson(['success' => true]);

        $servers = $response->json('iceServers');
        $this->assertNotEmpty($servers);
        $this->assertCount(2, $servers);

        $turnEntry = $servers[1];
        $this->assertStringContainsString(':user_' . $this->caller->id, $turnEntry['username']);
        $expectedHmac = base64_encode(hash_hmac('sha1', $turnEntry['username'], 'super-secret-coturn-hmac-key', true));
        $this->assertSame($expectedHmac, $turnEntry['credential']);
    }

    public function test_full_video_call_lifecycle_sdp_ice_signaling_and_call_history(): void
    {
        Event::fake([CallSignalingEvent::class, CallLifecycleEvent::class]);

        // 1. Caller initiates a Video Call
        $initRes = $this->actingAs($this->caller)->postJson('/chat/calls/initiate', [
            'receiver_id'     => $this->callee->id,
            'conversation_id' => $this->conversationId,
            'call_type'       => 'video',
        ]);

        $initRes->assertOk()
            ->assertJson([
                'success' => true,
                'call'    => [
                    'call_type' => 'video',
                    'is_video'  => true,
                ],
            ]);

        $callId = (int) $initRes->json('call.id');
        $this->assertGreaterThan(0, $callId);

        Event::assertDispatched(CallLifecycleEvent::class, function (CallLifecycleEvent $e) use ($callId) {
            return $e->targetUserId === $this->callee->id
                && $e->eventName === 'call.invited'
                && ($e->payload['call_id'] ?? 0) === $callId
                && ($e->payload['call_type'] ?? '') === 'video';
        });

        // 2. Outsider is strictly forbidden from sending or reading signals on this call
        $this->actingAs($this->outsider)->postJson("/chat/calls/{$callId}/signal", [
            'type'    => 'offer',
            'payload' => ['type' => 'offer', 'sdp' => 'v=0...'],
        ])->assertStatus(403);

        $this->actingAs($this->outsider)->getJson("/chat/calls/{$callId}/signals")->assertStatus(403);

        // 3. Callee marks ringing & Caller sends SDP Offer + ICE candidate
        $ringRes = $this->actingAs($this->callee)->postJson("/chat/calls/{$callId}/ringing");
        $ringRes->assertOk()->assertJson([
            'success' => true,
            'call'    => ['status' => 'ringing'],
        ]);

        $this->actingAs($this->caller)->postJson("/chat/calls/{$callId}/signal", [
            'type'    => 'offer',
            'payload' => ['type' => 'offer', 'sdp' => 'v=0-mock-video-offer'],
        ])->assertOk();

        $this->actingAs($this->caller)->postJson("/chat/calls/{$callId}/signal", [
            'type'    => 'ice_candidate',
            'payload' => ['candidate' => 'candidate:1 1 UDP 2122252543 192.0.2.1 54400 typ host', 'sdpMid' => '0', 'sdpMLineIndex' => 0],
        ])->assertOk();

        // 4. Callee polls pending signals cleanly
        $pollRes = $this->actingAs($this->callee)->getJson("/chat/calls/{$callId}/signals?after_id=0");
        $pollRes->assertOk()->assertJsonCount(2, 'signals');

        // 5. Callee accepts the Video Call & sends SDP Answer
        $acceptRes = $this->actingAs($this->callee)->postJson("/chat/calls/{$callId}/accept");
        $acceptRes->assertOk()->assertJson([
            'success' => true,
            'call'    => [
                'id'        => $callId,
                'status'    => 'connected',
                'call_type' => 'video',
            ],
        ]);

        $this->actingAs($this->callee)->postJson("/chat/calls/{$callId}/signal", [
            'type'    => 'answer',
            'payload' => ['type' => 'answer', 'sdp' => 'v=0-mock-video-answer'],
        ])->assertOk();

        // 6. End active call -> records 📹 Video Call event message & appears in Call History
        $endRes = $this->actingAs($this->caller)->postJson("/chat/calls/{$callId}/end");
        $endRes->assertOk()->assertJson([
            'success' => true,
            'call'    => [
                'id'     => $callId,
                'status' => 'ended',
            ],
        ]);

        $callEventMsg = ChatMessage::where('conversation_id', $this->conversationId)
            ->where('type', 'call_event')
            ->latest('id')
            ->first();

        $this->assertNotNull($callEventMsg);
        $this->assertStringContainsString('📹 Video Call', $callEventMsg->body);

        $historyRes = $this->actingAs($this->caller)->getJson('/chat/calls/history');
        $historyRes->assertOk()->assertJsonCount(1, 'calls');
        $this->assertSame('video', $historyRes->json('calls.0.call_type'));
    }

    public function test_busy_detection_returns_409_when_recipient_is_already_on_another_call(): void
    {
        Event::fake([CallSignalingEvent::class, CallLifecycleEvent::class]);

        // Caller Alpha calls Callee Beta
        $firstCall = $this->actingAs($this->caller)->postJson('/chat/calls/initiate', [
            'receiver_id'     => $this->callee->id,
            'conversation_id' => $this->conversationId,
            'call_type'       => 'voice',
        ]);
        $firstCall->assertOk();

        // Outsider Gamma opens a private chat with Callee Beta and tries to call while Beta is ringing
        $secondConv = $this->actingAs($this->outsider)->postJson('/chat/conversations/private', [
            'user_id' => $this->callee->id,
        ]);
        $secondConvId = (int) $secondConv->json('conversation.id');

        $busyRes = $this->actingAs($this->outsider)->postJson('/chat/calls/initiate', [
            'receiver_id'     => $this->callee->id,
            'conversation_id' => $secondConvId,
            'call_type'       => 'voice',
        ]);

        $busyRes->assertStatus(409)->assertJson([
            'success' => false,
            'busy'    => true,
        ]);

        // Cancel original call cleanly
        $callId = (int) $firstCall->json('call.id');
        $cancelRes = $this->actingAs($this->caller)->postJson("/chat/calls/{$callId}/cancel");
        $cancelRes->assertOk()->assertJson([
            'success' => true,
            'call'    => ['status' => 'cancelled'],
        ]);
    }
}
