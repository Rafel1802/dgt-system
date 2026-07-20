<?php

namespace Tests\Feature;

use App\Events\InstantNotificationBroadcast;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Support\InstantNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InstantNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_writes_the_database_notification_and_broadcasts_it_live(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $user = User::factory()->create();

        InstantNotifier::send($user, new GenericDatabaseNotification([
            'module'  => 'crm',
            'type'    => 'tech_case_new',
            'message' => 'Test message',
            'link'    => '/somewhere',
        ]));

        $this->assertEquals(1, $user->notifications()->count());
        $stored = $user->notifications()->first();

        Event::assertDispatched(InstantNotificationBroadcast::class, function (InstantNotificationBroadcast $event) use ($user, $stored) {
            return $event->userId === $user->id
                && $event->payload['id'] === $stored->id
                && $event->payload['data']['message'] === 'Test message';
        });
    }

    /** The live push must carry the SAME id Laravel wrote to the notifications table, not a second freshly-minted one, or the frontend's dedupe-by-id check breaks. */
    public function test_broadcast_payload_id_matches_the_stored_notification_id(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $user = User::factory()->create();

        InstantNotifier::send($user, new GenericDatabaseNotification(['module' => 'crm', 'type' => 'tech_case_new', 'message' => 'x']));

        $storedId = $user->notifications()->first()->id;

        Event::assertDispatched(InstantNotificationBroadcast::class, fn (InstantNotificationBroadcast $event) => $event->payload['id'] === $storedId);
    }
}
