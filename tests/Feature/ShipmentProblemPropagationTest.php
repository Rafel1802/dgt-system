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

    public function test_resolving_the_only_problem_shipment_clears_the_flag_on_customer_and_ebay_record(): void
    {
        $customer = Customer::create([
            'name' => 'Marady', 'email' => 'marady@email.com',
            'shipment_delay' => true,
            'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Ebay->value,
            'created_by' => $this->user->id,
        ]);
        $ebayRecord = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_RESOLVED,
            'buyer_name' => 'Marady',
            'email' => 'marady@email.com',
            'shipment_delay' => true,
            'customer_id' => $customer->id,
        ]);

        $shipment = Shipment::create(['shipment_code' => 'SHP-RESOLVE-1']);
        $shipmentCustomer = ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'customer_id' => $customer->id,
            'recipient_name' => 'Marady',
            'recipient_email' => 'marady@email.com',
            'shipping_address' => 'Phnom Penh',
            'status' => ShipmentCustomer::STATUS_PROBLEM,
        ]);

        $this->actingAs($this->user)->put(
            route('crm.logistics.shipments.customers.update', [$shipment, $shipmentCustomer]),
            [
                'recipient_name' => 'Marady',
                'recipient_email' => 'marady@email.com',
                'shipping_address' => 'Phnom Penh',
                'status' => ShipmentCustomer::STATUS_DELIVERED,
                'customer_id' => $customer->id,
            ]
        )->assertRedirect(route('crm.logistics.shipments.show', $shipment));

        $this->assertFalse($customer->fresh()->shipment_delay);
        $this->assertFalse($ebayRecord->fresh()->shipment_delay);
    }

    /**
     * Resolving straight to Delivered should land the lead on Delivered,
     * not the generic "no longer delayed" InDelivery fallback — the
     * shipment-delay sync alone would only get as far as InDelivery, but
     * syncDeliveryStatus() (run right after) then correctly finishes the
     * job since the shipment-customer's own status really is Delivered.
     */
    public function test_resolving_a_problem_shipment_by_marking_delivered_sets_lead_status_to_delivered(): void
    {
        $lead = Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Delayed Lead',
            'client_email' => 'delayed@email.com',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::DelayedShipment->value,
            'received_at' => now(),
        ]);

        $shipment = Shipment::create(['shipment_code' => 'SHP-RESOLVE-LEAD']);
        $shipmentCustomer = ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'recipient_name' => 'Delayed Lead',
            'recipient_email' => 'delayed@email.com',
            'shipping_address' => 'Phnom Penh',
            'status' => ShipmentCustomer::STATUS_PROBLEM,
        ]);

        $this->actingAs($this->user)->put(
            route('crm.logistics.shipments.customers.update', [$shipment, $shipmentCustomer]),
            [
                'recipient_name' => 'Delayed Lead',
                'recipient_email' => 'delayed@email.com',
                'shipping_address' => 'Phnom Penh',
                'status' => ShipmentCustomer::STATUS_DELIVERED,
            ]
        )->assertRedirect(route('crm.logistics.shipments.show', $shipment));

        $this->assertEquals(WebsiteLeadStatus::Delivered, $lead->fresh()->status);
    }

    /** Resolving a Problem shipment to a non-Delivered status (e.g. back to Pending) still reverts to the generic InDelivery fallback. */
    public function test_resolving_a_problem_shipment_by_marking_pending_reverts_lead_to_in_delivery(): void
    {
        $lead = Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Delayed Lead Two',
            'client_email' => 'delayed2@email.com',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::DelayedShipment->value,
            'received_at' => now(),
        ]);

        $shipment = Shipment::create(['shipment_code' => 'SHP-RESOLVE-LEAD-2']);
        $shipmentCustomer = ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'recipient_name' => 'Delayed Lead Two',
            'recipient_email' => 'delayed2@email.com',
            'shipping_address' => 'Phnom Penh',
            'status' => ShipmentCustomer::STATUS_PROBLEM,
        ]);

        $this->actingAs($this->user)->put(
            route('crm.logistics.shipments.customers.update', [$shipment, $shipmentCustomer]),
            [
                'recipient_name' => 'Delayed Lead Two',
                'recipient_email' => 'delayed2@email.com',
                'shipping_address' => 'Phnom Penh',
                'status' => ShipmentCustomer::STATUS_PENDING,
            ]
        )->assertRedirect(route('crm.logistics.shipments.show', $shipment));

        $this->assertEquals(WebsiteLeadStatus::InDelivery, $lead->fresh()->status);
    }

    public function test_resolving_one_of_two_problem_shipments_keeps_the_flag_set(): void
    {
        $customer = Customer::create([
            'name' => 'Multi Shipment Customer', 'email' => 'multi@email.com',
            'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value,
            'created_by' => $this->user->id,
        ]);

        $shipmentA = Shipment::create(['shipment_code' => 'SHP-MULTI-A']);
        $scA = $shipmentA->shipmentCustomers()->create([
            'customer_id' => $customer->id, 'recipient_name' => 'Multi Shipment Customer',
            'shipping_address' => 'Address A', 'status' => ShipmentCustomer::STATUS_PROBLEM,
        ]);

        $shipmentB = Shipment::create(['shipment_code' => 'SHP-MULTI-B']);
        $shipmentB->shipmentCustomers()->create([
            'customer_id' => $customer->id, 'recipient_name' => 'Multi Shipment Customer',
            'shipping_address' => 'Address B', 'status' => ShipmentCustomer::STATUS_PROBLEM,
        ]);

        // Resolve only shipment A — shipment B is still a Problem.
        $this->actingAs($this->user)->put(
            route('crm.logistics.shipments.customers.update', [$shipmentA, $scA]),
            [
                'recipient_name' => 'Multi Shipment Customer',
                'shipping_address' => 'Address A',
                'status' => ShipmentCustomer::STATUS_DELIVERED,
                'customer_id' => $customer->id,
            ]
        )->assertRedirect(route('crm.logistics.shipments.show', $shipmentA));

        $this->assertTrue($customer->fresh()->shipment_delay);
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

    /**
     * WebsiteLeadStatus::Delivered existed in the enum (excluded from the
     * Active/Follow-Up-Due scopes) but nothing ever actually set it, so a
     * lead stayed on whatever status it had before the shipment finished
     * (e.g. "In Delivery") and the Customer Database page kept showing the
     * stale status even after delivery was complete.
     */
    public function test_marking_a_shipment_customer_delivered_flips_the_matched_lead_status_to_delivered(): void
    {
        $lead = Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Delivery Sync Lead',
            'client_email' => 'delivered@example.com',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::InDelivery->value,
            'received_at' => now(),
        ]);

        $shipment = Shipment::create(['shipment_code' => 'SHP-DELIVERED-1']);
        $shipmentCustomer = ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'recipient_name' => 'Delivery Sync Lead',
            'recipient_email' => 'delivered@example.com',
            'shipping_address' => 'Phnom Penh',
            'status' => ShipmentCustomer::STATUS_IN_TRANSIT,
        ]);

        $this->actingAs($this->user)->put(
            route('crm.logistics.shipments.customers.update', [$shipment, $shipmentCustomer]),
            [
                'recipient_name' => 'Delivery Sync Lead',
                'recipient_email' => 'delivered@example.com',
                'shipping_address' => 'Phnom Penh',
                'status' => ShipmentCustomer::STATUS_DELIVERED,
            ]
        )->assertRedirect(route('crm.logistics.shipments.show', $shipment));

        $this->assertEquals(WebsiteLeadStatus::Delivered, $lead->fresh()->status);
        $this->assertDatabaseHas('lead_follow_ups', [
            'lead_id' => $lead->id,
            'status_changed_to' => WebsiteLeadStatus::Delivered->value,
        ]);
    }

    /** A lead a staff member deliberately marked Lost shouldn't be resurrected by a shipment sync. */
    public function test_marking_a_shipment_customer_delivered_does_not_resurrect_a_lost_lead(): void
    {
        $lead = Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Already Lost Lead',
            'client_email' => 'lost@example.com',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::Lost->value,
            'received_at' => now(),
        ]);

        $shipment = Shipment::create(['shipment_code' => 'SHP-DELIVERED-2']);
        $shipmentCustomer = ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'recipient_name' => 'Already Lost Lead',
            'recipient_email' => 'lost@example.com',
            'shipping_address' => 'Phnom Penh',
            'status' => ShipmentCustomer::STATUS_IN_TRANSIT,
        ]);

        $this->actingAs($this->user)->put(
            route('crm.logistics.shipments.customers.update', [$shipment, $shipmentCustomer]),
            [
                'recipient_name' => 'Already Lost Lead',
                'recipient_email' => 'lost@example.com',
                'shipping_address' => 'Phnom Penh',
                'status' => ShipmentCustomer::STATUS_DELIVERED,
            ]
        )->assertRedirect(route('crm.logistics.shipments.show', $shipment));

        $this->assertEquals(WebsiteLeadStatus::Lost, $lead->fresh()->status);
    }

    /** /crm/customers should show "Delivered" as the status once the matched lead updates. */
    public function test_customer_database_page_shows_delivered_status_after_propagation(): void
    {
        $lead = Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Visible Delivery Lead',
            'client_email' => 'visible-delivery@example.com',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::Delivered->value,
            'received_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.customers.index'));

        $response->assertOk();
        $response->assertSee('Visible Delivery Lead');
        $response->assertSee('Delivered');
    }
}
