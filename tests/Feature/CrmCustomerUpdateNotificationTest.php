<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Events\InstantNotificationBroadcast;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CrmCustomerUpdateNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_updating_a_customer_notifies_the_assigned_rep_exactly_once(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $admin = $this->makeUser('admin-crm');
        $rep = $this->makeUser('sales-crm');

        $customer = Customer::create([
            'name' => 'Dedup Target', 'status' => CustomerStatus::Lead->value,
            'source' => CustomerSource::Website->value, 'created_by' => $admin->id,
            'assigned_to' => $rep->id,
        ]);

        $this->actingAs($admin)->put(route('crm.customers.update', $customer), [
            'name'   => 'Dedup Target',
            'status' => CustomerStatus::Active->value,
        ])->assertRedirect();

        $toRep = collect(Event::dispatched(InstantNotificationBroadcast::class))
            ->map(fn ($args) => $args[0])
            ->filter(fn ($e) => $e->userId === $rep->id);

        $this->assertCount(1, $toRep, 'Assigned rep should get exactly one notification per edit, not one from each notifier.');
    }

    public function test_updating_a_customer_also_notifies_the_crm_supervisor(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $admin = $this->makeUser('admin-crm');
        $rep = $this->makeUser('sales-crm');
        $supervisor = $this->makeUser('sales-crm');
        $supervisor->update(['crm_role' => 'supervisor']);

        $customer = Customer::create([
            'name' => 'Supervisor Notify Target', 'status' => CustomerStatus::Lead->value,
            'source' => CustomerSource::Website->value, 'created_by' => $admin->id,
            'assigned_to' => $rep->id,
        ]);

        $this->actingAs($admin)->put(route('crm.customers.update', $customer), [
            'name'   => 'Supervisor Notify Target',
            'status' => CustomerStatus::Active->value,
        ])->assertRedirect();

        Event::assertDispatched(InstantNotificationBroadcast::class, fn ($e) => $e->userId === $supervisor->id && $e->payload['data']['type'] === 'customer_updated');
    }
}
