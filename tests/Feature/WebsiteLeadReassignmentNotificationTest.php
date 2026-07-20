<?php

namespace Tests\Feature;

use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Events\InstantNotificationBroadcast;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Reassigning a lead's handler used to be completely silent — the new
 * handler had no way to find out short of noticing it on the leads list.
 * See WebsiteCrmController::update().
 */
class WebsiteLeadReassignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;
    protected User $originalHandler;
    protected User $newHandler;
    protected Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->supervisor = User::factory()->create(['is_active' => true]);
        $this->supervisor->assignRole('sales-crm');
        $this->supervisor->update(['crm_role' => 'supervisor']);

        $this->originalHandler = User::factory()->create(['is_active' => true]);
        $this->originalHandler->assignRole('sales-crm');

        $this->newHandler = User::factory()->create(['is_active' => true]);
        $this->newHandler->assignRole('sales-crm');

        $this->lead = Lead::create([
            'handled_by'  => $this->originalHandler->id,
            'client_name' => 'Reassignable Lead',
            'source'      => InquirySource::Website->value,
            'status'      => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'client_name' => $this->lead->client_name,
            'source'      => InquirySource::Website->value,
            'status'      => $this->lead->status->value,
        ], $overrides);
    }

    public function test_reassigning_the_handler_notifies_the_new_handler_live(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $this->actingAs($this->supervisor)->put(route('crm.website.update', $this->lead), $this->updatePayload([
            'handled_by' => $this->newHandler->id,
        ]));

        $this->assertEquals(1, $this->newHandler->fresh()->notifications()->count());
        Event::assertDispatched(InstantNotificationBroadcast::class, function (InstantNotificationBroadcast $event) {
            return $event->userId === $this->newHandler->id
                && $event->payload['data']['type'] === 'lead_reassigned';
        });
    }

    public function test_reassigning_to_the_same_handler_does_not_notify(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $this->actingAs($this->supervisor)->put(route('crm.website.update', $this->lead), $this->updatePayload([
            'handled_by' => $this->originalHandler->id,
        ]));

        $this->assertEquals(0, $this->originalHandler->fresh()->notifications()->count());
        Event::assertNotDispatched(InstantNotificationBroadcast::class);
    }

    public function test_assigning_to_yourself_does_not_notify_yourself(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $this->actingAs($this->supervisor)->put(route('crm.website.update', $this->lead), $this->updatePayload([
            'handled_by' => $this->supervisor->id,
        ]));

        $this->assertEquals(0, $this->supervisor->fresh()->notifications()->count());
        Event::assertNotDispatched(InstantNotificationBroadcast::class);
    }

    /** A plain sales-crm rep can't reassign at all — handled_by is silently dropped, so nobody should be notified. */
    public function test_a_non_supervisor_cannot_trigger_reassignment_notifications(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $this->actingAs($this->originalHandler)->put(route('crm.website.update', $this->lead), $this->updatePayload([
            'handled_by' => $this->newHandler->id,
        ]));

        $this->assertEquals($this->originalHandler->id, $this->lead->fresh()->handled_by);
        $this->assertEquals(0, $this->newHandler->fresh()->notifications()->count());
        Event::assertNotDispatched(InstantNotificationBroadcast::class);
    }
}
