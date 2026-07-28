<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LogisticIssuesPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');
    }

    public function test_page_lists_customers_flagged_with_a_shipment_delay(): void
    {
        $customer = Customer::create([
            'name' => 'Flagged Customer', 'shipment_delay' => true,
            'status' => CustomerStatus::Active->value, 'source' => CustomerSource::Website->value,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.issues.index'));

        $response->assertOk();
        $response->assertSee('Flagged Customer');
    }

    public function test_page_lists_problem_shipment_customers(): void
    {
        $shipment = Shipment::create(['shipment_code' => 'SHP-ISSUE-1']);
        $shipment->shipmentCustomers()->create([
            'recipient_name' => 'Problem Recipient', 'status' => ShipmentCustomer::STATUS_PROBLEM,
            'shipping_address' => '',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.issues.index'));

        $response->assertOk();
        $response->assertSee('Problem Recipient');
    }

    public function test_page_excludes_customers_without_a_logistic_issue(): void
    {
        Customer::create([
            'name' => 'Fine Customer', 'shipment_delay' => false,
            'status' => CustomerStatus::Active->value, 'source' => CustomerSource::Website->value,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.issues.index'));

        $response->assertOk();
        $response->assertDontSee('Fine Customer');
    }

    public function test_page_is_reachable_from_the_sidebar_route(): void
    {
        $response = $this->actingAs($this->user)->get(route('crm.logistics.issues.index'));

        $response->assertOk();
    }
}
