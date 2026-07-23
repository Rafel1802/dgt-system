<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DeletedRecordFriendlyErrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    /**
     * Simulates the reported scenario: staff A is viewing a customer's edit
     * page when staff B deletes that same customer, then staff A submits.
     */
    public function test_editing_a_customer_deleted_by_someone_else_redirects_with_a_friendly_message(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin-crm');

        $customer = Customer::create([
            'name' => 'Deleted Mid-Edit', 'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value, 'created_by' => $user->id,
        ]);
        $customerId = $customer->id;
        $customer->forceDelete();

        $response = $this->actingAs($user)->get(route('crm.customers.edit', $customerId));

        $response->assertRedirect(route('crm.customers.index'));
        $response->assertSessionHas('error', fn ($msg) => str_contains($msg, 'no longer exists'));
    }

    public function test_an_ajax_action_on_a_deleted_customer_returns_a_friendly_json_message(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin-crm');

        $customer = Customer::create([
            'name' => 'Deleted Mid-Purchase', 'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value, 'created_by' => $user->id,
        ]);
        $customerId = $customer->id;
        $customer->forceDelete();

        $response = $this->actingAs($user)->postJson(route('crm.customers.purchase', $customerId), [
            'value' => 100,
        ]);

        $response->assertStatus(404);
        $response->assertJson(fn ($json) => $json->where('message', 'This customer no longer exists — it may have been deleted by another user.')->etc());
    }
}
