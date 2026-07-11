<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\TruckingCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ShipmentControllerTest extends TestCase
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

    public function test_all_filter_shows_every_shipment_regardless_of_status(): void
    {
        Shipment::create(['status' => Shipment::STATUS_PENDING]);
        Shipment::create(['status' => Shipment::STATUS_COMPLETE]);
        Shipment::create(['status' => Shipment::STATUS_PROBLEM]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.shipments.index', ['status' => 'all']));

        $response->assertOk();
        $response->assertViewHas('shipments', fn ($shipments) => $shipments->total() === 3);
    }

    public function test_default_active_filter_excludes_complete_shipments(): void
    {
        Shipment::create(['status' => Shipment::STATUS_PENDING]);
        Shipment::create(['status' => Shipment::STATUS_COMPLETE]);
        Shipment::create(['status' => Shipment::STATUS_PROBLEM]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.shipments.index'));

        $response->assertOk();
        $response->assertViewHas('shipments', fn ($shipments) => $shipments->total() === 2);
    }

    public function test_status_specific_filter_still_works(): void
    {
        Shipment::create(['status' => Shipment::STATUS_PENDING]);
        Shipment::create(['status' => Shipment::STATUS_COMPLETE]);

        $response = $this->actingAs($this->user)->get(route('crm.logistics.shipments.index', ['status' => Shipment::STATUS_COMPLETE]));

        $response->assertOk();
        $response->assertViewHas('shipments', fn ($shipments) => $shipments->total() === 1);
    }

    public function test_a_driver_can_be_assigned_to_a_shipment_from_its_trucking_company(): void
    {
        $company = TruckingCompany::create(['company_name' => 'FWD', 'is_active' => true]);
        $driver = $company->drivers()->create(['name' => 'Sok Dara', 'phone' => '012-345-678']);

        $response = $this->actingAs($this->user)->post(route('crm.logistics.shipments.store'), [
            'status'              => Shipment::STATUS_PENDING,
            'trucking_company_id' => $company->id,
            'driver_id'           => $driver->id,
        ]);

        $shipment = Shipment::firstOrFail();
        $response->assertRedirect(route('crm.logistics.shipments.show', $shipment));
        $this->assertEquals($driver->id, $shipment->driver_id);
    }

    public function test_a_driver_from_a_different_company_cannot_be_assigned(): void
    {
        $company = TruckingCompany::create(['company_name' => 'FWD', 'is_active' => true]);
        $otherCompany = TruckingCompany::create(['company_name' => 'Other Co', 'is_active' => true]);
        $otherDriver = $otherCompany->drivers()->create(['name' => 'Someone Else']);

        $response = $this->actingAs($this->user)->post(route('crm.logistics.shipments.store'), [
            'status'              => Shipment::STATUS_PENDING,
            'trucking_company_id' => $company->id,
            'driver_id'           => $otherDriver->id,
        ]);

        $response->assertSessionHasErrors('driver_id');
        $this->assertEquals(0, Shipment::count());
    }

    public function test_shipment_auto_completes_once_every_customer_is_delivered(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_IN_PROGRESS]);
        $c1 = $shipment->shipmentCustomers()->create(['recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PENDING, 'shipping_address' => '']);
        $c2 = $shipment->shipmentCustomers()->create(['recipient_name' => 'B', 'status' => ShipmentCustomer::STATUS_PENDING, 'shipping_address' => '']);

        $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $c1]), [
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_DELIVERED, 'notes' => 'Delivered on time.',
        ]);
        $this->assertEquals(Shipment::STATUS_IN_PROGRESS, $shipment->fresh()->status);

        $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $c2]), [
            'recipient_name' => 'B', 'status' => ShipmentCustomer::STATUS_DELIVERED, 'notes' => 'Delivered on time.',
        ]);
        $this->assertEquals(Shipment::STATUS_COMPLETE, $shipment->fresh()->status);
        $this->assertNotNull($shipment->fresh()->actual_arrival);
    }

    public function test_shipment_status_is_left_untouched_once_customers_become_mixed(): void
    {
        // Per the "only change when every customer shares the same status"
        // rule, adding a not-yet-delivered customer to an all-delivered
        // shipment does NOT force the status back to In Progress — it's
        // simply left as-is, and the UI shows a per-status count breakdown.
        $shipment = Shipment::create(['status' => Shipment::STATUS_COMPLETE]);
        $shipment->shipmentCustomers()->create(['recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_DELIVERED, 'shipping_address' => '']);

        $this->actingAs($this->user)->post(route('crm.logistics.shipments.customers.add', $shipment), [
            'recipient_name' => 'New Customer',
        ]);

        $this->assertEquals(Shipment::STATUS_COMPLETE, $shipment->fresh()->status);
        $this->assertEquals(['delivered' => 1, 'pending' => 1], $shipment->fresh()->customerStatusCounts());
    }

    public function test_a_customer_marked_problem_puts_the_whole_shipment_in_problem(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_IN_PROGRESS]);
        $customer = $shipment->shipmentCustomers()->create(['recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PENDING, 'shipping_address' => '']);

        $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PROBLEM, 'notes' => 'Customer reported a damaged item.',
        ]);

        $this->assertEquals(Shipment::STATUS_PROBLEM, $shipment->fresh()->status);
    }

    public function test_a_shipment_recovers_from_problem_once_no_customer_is_still_in_problem(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_PROBLEM]);
        $customer = $shipment->shipmentCustomers()->create(['recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PROBLEM, 'shipping_address' => '']);

        $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_DELIVERED, 'notes' => 'Issue resolved, redelivered successfully.',
        ]);

        $this->assertEquals(Shipment::STATUS_COMPLETE, $shipment->fresh()->status);
    }

    public function test_propagation_uses_the_direct_customer_link_even_when_recipient_contact_info_is_wrong(): void
    {
        $company = TruckingCompany::create(['company_name' => 'FWD', 'is_active' => true]);
        $customer = \App\Models\Customer::create([
            'name' => 'Marady', 'email' => 'marady@example.com', 'phone' => '099-000-000',
            'status' => \App\Enums\CustomerStatus::Lead->value, 'source' => \App\Enums\CustomerSource::Ebay->value,
            'created_by' => $this->user->id,
        ]);
        $ebayRecord = \App\Models\EbayCustomerRecord::create([
            'tab_type' => \App\Models\EbayCustomerRecord::TAB_RESOLVED,
            'username' => 'marady', 'email' => 'marady@example.com', 'customer_id' => $customer->id,
        ]);

        $shipment = Shipment::create(['status' => Shipment::STATUS_IN_PROGRESS, 'trucking_company_id' => $company->id]);
        // Deliberately mismatched recipient contact info — only customer_id is reliable here.
        $shipmentCustomer = $shipment->shipmentCustomers()->create([
            'recipient_name' => 'Marady', 'customer_id' => $customer->id,
            'recipient_email' => 'someone-else@example.com', 'shipping_address' => '',
            'status' => ShipmentCustomer::STATUS_PENDING,
        ]);

        $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $shipmentCustomer]), [
            'recipient_name' => 'Marady', 'customer_id' => $customer->id,
            'recipient_email' => 'someone-else@example.com', 'status' => ShipmentCustomer::STATUS_PROBLEM,
            'notes' => 'Package damaged in transit.',
        ]);

        $this->assertTrue($ebayRecord->fresh()->shipment_delay);
        $this->assertTrue($customer->fresh()->shipment_delay);
    }

    public function test_a_shipment_customer_marked_problem_appears_in_the_unified_logistic_issues_list(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_IN_PROGRESS]);
        $customer = $shipment->shipmentCustomers()->create([
            'recipient_name'   => 'Problem Customer',
            'recipient_email'  => 'problem@example.com',
            'status'           => ShipmentCustomer::STATUS_PENDING,
            'shipping_address' => '',
        ]);

        $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'Problem Customer',
            'recipient_email' => 'problem@example.com',
            'status' => ShipmentCustomer::STATUS_PROBLEM,
            'notes' => 'Address could not be located.',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.customers.index', ['status_filter' => 'Logistic issues']));

        $response->assertOk();
        $response->assertSee('Problem Customer');
    }

    public function test_marking_a_customer_as_problem_without_a_note_is_rejected(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_IN_PROGRESS]);
        $customer = $shipment->shipmentCustomers()->create(['recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PENDING, 'shipping_address' => '']);

        $response = $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PROBLEM,
        ]);

        $response->assertSessionHasErrors('notes');
        $this->assertEquals(ShipmentCustomer::STATUS_PENDING, $customer->fresh()->status);
    }

    public function test_routine_status_changes_do_not_require_a_note(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_IN_PROGRESS]);
        $customer = $shipment->shipmentCustomers()->create(['recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PENDING, 'shipping_address' => '']);

        $response = $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_IN_TRANSIT,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(ShipmentCustomer::STATUS_IN_TRANSIT, $customer->fresh()->status);
    }

    public function test_saving_a_customer_without_changing_status_does_not_require_a_note(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_IN_PROGRESS]);
        $customer = $shipment->shipmentCustomers()->create(['recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PENDING, 'shipping_address' => '']);

        $response = $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'A Updated', 'status' => ShipmentCustomer::STATUS_PENDING,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals('A Updated', $customer->fresh()->recipient_name);
    }

    public function test_resolving_a_problem_to_delivered_does_not_require_a_note(): void
    {
        $shipment = Shipment::create(['status' => Shipment::STATUS_PROBLEM]);
        $customer = $shipment->shipmentCustomers()->create(['recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_PROBLEM, 'shipping_address' => '']);

        $response = $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'A', 'status' => ShipmentCustomer::STATUS_DELIVERED,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(ShipmentCustomer::STATUS_DELIVERED, $customer->fresh()->status);
    }
}
