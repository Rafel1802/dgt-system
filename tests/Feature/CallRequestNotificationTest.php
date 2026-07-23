<?php

namespace Tests\Feature;

use App\Events\InstantNotificationBroadcast;
use App\Models\CallRequest;
use App\Models\Customer;
use App\Models\TechSupportCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CallRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    public function test_requesting_a_call_creates_the_record_and_notifies_sales_crm(): void
    {
        $tech = User::factory()->create(['is_active' => true]);
        $tech->assignRole('tech-support');

        $websiteRep = User::factory()->create(['is_active' => true]);
        $websiteRep->assignRole('sales-crm');

        $customer = Customer::create(['name' => 'Call Req Target', 'status' => 'lead', 'source' => 'website', 'created_by' => $tech->id]);
        $case = TechSupportCase::create([
            'source_type' => Customer::class,
            'source_id'   => $customer->id,
            'customer_id' => $customer->id,
            'status'      => TechSupportCase::STATUS_NEW,
            'occurrence_count' => 1,
        ]);

        Event::fake([InstantNotificationBroadcast::class]);

        $response = $this->actingAs($tech)->postJson(route('crm.tech-support.request-call', $case), [
            'note' => 'Customer wants a callback about pricing.',
        ]);

        $response->assertOk();
        $this->assertSame(1, CallRequest::count());

        Event::assertDispatched(InstantNotificationBroadcast::class, fn ($e) => $e->userId === $websiteRep->id && $e->payload['data']['type'] === 'call_request_new');
    }
}
