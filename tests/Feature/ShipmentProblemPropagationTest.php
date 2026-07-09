<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\Lead;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ShipmentProblemPropagationTest extends TestCase
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

    public function test_marking_a_shipment_customer_as_problem_flips_matching_lead_and_ebay_record(): void
    {
        $lead = Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Alice Chen',
            'client_email' => 'alice@email.com',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::Successful->value,
            'received_at' => now(),
        ]);

        $ebayRecord = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'buyer_name' => 'Alice Chen',
            'email' => 'alice@email.com',
            'status' => 'open',
        ]);

        $customer = Customer::create([
            'name' => 'Alice Chen',
            'email' => 'alice@email.com',
            'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value,
            'created_by' => $this->user->id,
        ]);

        $shipment = Shipment::create(['shipment_code' => 'SHP-TEST-1']);
        $shipmentCustomer = ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'recipient_name' => 'Alice Chen',
            'recipient_email' => 'alice@email.com',
            'shipping_address' => 'Phnom Penh, St 210',
            'status' => ShipmentCustomer::STATUS_PENDING,
        ]);

        $this->actingAs($this->user)->put(
            route('crm.logistics.shipments.customers.update', [$shipment, $shipmentCustomer]),
            [
                'recipient_name' => 'Alice Chen',
                'recipient_email' => 'alice@email.com',
                'shipping_address' => 'Phnom Penh, St 210',
                'status' => ShipmentCustomer::STATUS_PROBLEM,
                'notes' => 'Delivery issue reported.',
            ]
        )->assertRedirect(route('crm.logistics.shipments.show', $shipment));

        $this->assertEquals(WebsiteLeadStatus::DelayedShipment, $lead->fresh()->status);
        $this->assertTrue($ebayRecord->fresh()->shipment_delay);
        $this->assertTrue($customer->fresh()->shipment_delay);

        // Lead's status change should be recorded in its follow-up/status history timeline
        $this->assertDatabaseHas('lead_follow_ups', [
            'lead_id' => $lead->id,
            'status_changed_to' => WebsiteLeadStatus::DelayedShipment->value,
        ]);
    }

    public function test_propagation_prefers_the_direct_customer_link_over_contact_matching(): void
    {
        $customer = Customer::create([
            'name' => 'Bora Kim',
            'email' => 'old-email@example.com', // deliberately does NOT match the shipment customer's email
            'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value,
            'created_by' => $this->user->id,
        ]);

        $shipment = Shipment::create(['shipment_code' => 'SHP-TEST-3']);
        $shipmentCustomer = ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'customer_id' => $customer->id,
            'recipient_name' => 'Bora Kim',
            'recipient_email' => 'different-email@example.com',
            'shipping_address' => 'Siem Reap',
            'status' => ShipmentCustomer::STATUS_PENDING,
        ]);

        $this->actingAs($this->user)->put(
            route('crm.logistics.shipments.customers.update', [$shipment, $shipmentCustomer]),
            [
                'recipient_name' => 'Bora Kim',
                'recipient_email' => 'different-email@example.com',
                'shipping_address' => 'Siem Reap',
                'status' => ShipmentCustomer::STATUS_PROBLEM,
                'notes' => 'Delivery issue reported.',
                'customer_id' => $customer->id,
            ]
        )->assertRedirect(route('crm.logistics.shipments.show', $shipment));

        $this->assertTrue($customer->fresh()->shipment_delay);
    }

    public function test_customer_profile_page_shows_the_logistic_issues_badge_after_propagation(): void
    {
        $customer = Customer::create([
            'name' => 'Chan Vuthy',
            'email' => 'chan@example.com',
            'shipment_delay' => true,
            'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Logistic Issues');
    }

    public function test_no_match_means_no_propagation(): void
    {
        $shipment = Shipment::create(['shipment_code' => 'SHP-TEST-2']);
        $shipmentCustomer = ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'recipient_name' => 'Unmatched Customer',
            'recipient_email' => 'nobody@nowhere.com',
            'shipping_address' => 'Somewhere',
            'status' => ShipmentCustomer::STATUS_PENDING,
        ]);

        $this->actingAs($this->user)->put(
            route('crm.logistics.shipments.customers.update', [$shipment, $shipmentCustomer]),
            [
                'recipient_name' => 'Unmatched Customer',
                'recipient_email' => 'nobody@nowhere.com',
                'shipping_address' => 'Somewhere',
                'status' => ShipmentCustomer::STATUS_PROBLEM,
                'notes' => 'Delivery issue reported.',
            ]
        )->assertRedirect(route('crm.logistics.shipments.show', $shipment));

        $this->assertEquals(ShipmentCustomer::STATUS_PROBLEM, $shipmentCustomer->fresh()->status);
        $this->assertDatabaseCount('lead_follow_ups', 0);
        $this->assertDatabaseCount('ebay_customer_status_history', 0);
    }
}
