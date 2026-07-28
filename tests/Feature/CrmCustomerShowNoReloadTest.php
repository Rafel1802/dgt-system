<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CrmCustomerShowNoReloadTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');

        $this->customer = Customer::create([
            'name' => 'Jane Buyer',
            'created_by' => $this->user->id,
            'status' => 'lead',
            'lifetime_value' => 0,
            'has_purchased' => false,
        ]);
    }

    public function test_show_page_renders_ids_used_for_local_updates_and_has_no_reload(): void
    {
        $response = $this->actingAs($this->user)->get(route('crm.customers.show', $this->customer));

        $response->assertOk();
        $response->assertSee('id="status-badge"', false);
        $response->assertSee('id="purchased-badge"', false);
        $response->assertSee('id="stat-lifetime-value"', false);
        $response->assertSee('id="stat-total-orders"', false);
        $response->assertSee('id="interactions-list"', false);
        $response->assertDontSee('location.reload()', false);
    }

    public function test_record_purchase_returns_data_needed_for_local_update(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            route('crm.customers.purchase', $this->customer),
            ['value' => 150]
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'lifetime_value' => '$150.00',
            'total_orders' => 1,
            'has_purchased' => true,
            'status_label' => 'Active Customer',
            'status_badge_class' => 'badge-emerald',
        ]);
        $response->assertJsonStructure(['interaction' => ['id', 'content', 'user' => ['name']]]);

        $this->customer->refresh();
        $this->assertTrue($this->customer->has_purchased);
        $this->assertEquals(1, $this->customer->total_orders);
    }

    public function test_log_interaction_returns_interaction_with_user_for_local_prepend(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            route('crm.customers.interactions', $this->customer),
            ['type' => 'call', 'outcome' => 'positive', 'content' => 'Discussed pricing.']
        );

        $response->assertCreated();
        $response->assertJsonPath('interaction.content', 'Discussed pricing.');
        $response->assertJsonPath('interaction.user.name', $this->user->name);
    }
}
