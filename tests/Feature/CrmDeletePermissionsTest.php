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
use App\Models\TruckingCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CrmDeletePermissionsTest extends TestCase
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

    private function makeEbayRecord(): EbayCustomerRecord
    {
        return EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'buyer_name' => 'Delete Target',
            'username' => 'deletetarget',
        ]);
    }

    private function makeShipment(): Shipment
    {
        return Shipment::create(['shipment_code' => 'SHP-DEL-1', 'status' => Shipment::STATUS_PENDING]);
    }

    private function makeLead(User $handler): Lead
    {
        return Lead::create([
            'handled_by' => $handler->id,
            'client_name' => 'Delete Target Lead',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);
    }

    // ── eBay domain ──────────────────────────────────────────────────────

    public function test_plain_sales_crm_cannot_delete_an_ebay_record(): void
    {
        $user = $this->makeUser('sales-crm');
        $record = $this->makeEbayRecord();

        $this->actingAs($user)->delete(route('crm.ebay.customers.destroy', $record))->assertForbidden();
        $this->assertModelExists($record);
    }

    public function test_ebay_supervisor_can_delete_an_ebay_record_but_not_a_shipment(): void
    {
        $user = $this->makeUser('ebay-supervisor');
        $record = $this->makeEbayRecord();
        $shipment = $this->makeShipment();

        $this->actingAs($user)->delete(route('crm.ebay.customers.destroy', $record))->assertRedirect();
        $this->assertSoftDeleted($record);

        $this->actingAs($user)->delete(route('crm.logistics.shipments.destroy', $shipment))->assertForbidden();
        $this->assertModelExists($shipment);
    }

    // ── Logistics domain ─────────────────────────────────────────────────

    public function test_plain_sales_crm_cannot_delete_a_shipment(): void
    {
        $user = $this->makeUser('sales-crm');
        $shipment = $this->makeShipment();

        $this->actingAs($user)->delete(route('crm.logistics.shipments.destroy', $shipment))->assertForbidden();
        $this->assertModelExists($shipment);
    }

    public function test_logistic_supervisor_can_delete_a_shipment_but_not_an_ebay_record(): void
    {
        $user = $this->makeUser('logistic-supervisor');
        $shipment = $this->makeShipment();
        $record = $this->makeEbayRecord();

        $this->actingAs($user)->delete(route('crm.logistics.shipments.destroy', $shipment))->assertRedirect();
        $this->assertSoftDeleted($shipment);

        $this->actingAs($user)->delete(route('crm.ebay.customers.destroy', $record))->assertForbidden();
        $this->assertModelExists($record);
    }

    public function test_logistic_supervisor_can_delete_a_trucking_company(): void
    {
        $user = $this->makeUser('logistic-supervisor');
        $company = TruckingCompany::create(['company_name' => 'Delete Co', 'is_active' => true]);

        $this->actingAs($user)->delete(route('crm.logistics.trucking.destroy', $company))->assertRedirect();
        $this->assertSoftDeleted($company);
    }

    // ── In-page removal stays open (not gated) ──────────────────────────

    public function test_removing_a_customer_from_a_shipment_still_works_for_a_plain_sales_crm_user(): void
    {
        $user = $this->makeUser('sales-crm');
        $shipment = $this->makeShipment();
        $shipmentCustomer = $shipment->shipmentCustomers()->create([
            'recipient_name' => 'Roster Row', 'status' => ShipmentCustomer::STATUS_PENDING, 'shipping_address' => '',
        ]);

        $response = $this->actingAs($user)->delete(route('crm.logistics.shipments.customers.remove', [$shipment, $shipmentCustomer]));

        $response->assertRedirect(route('crm.logistics.shipments.show', $shipment));
        $this->assertModelMissing($shipmentCustomer);
    }

    // ── Website / general CRM domain ─────────────────────────────────────

    public function test_plain_sales_crm_cannot_delete_a_lead(): void
    {
        $user = $this->makeUser('sales-crm');
        $lead = $this->makeLead($user);

        $this->actingAs($user)->delete(route('crm.website.destroy', $lead))->assertForbidden();
        $this->assertModelExists($lead);
    }

    public function test_plain_sales_crm_cannot_delete_a_customer(): void
    {
        $user = $this->makeUser('sales-crm');
        $customer = Customer::create([
            'name' => 'Delete Target Customer', 'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value, 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->delete(route('crm.customers.destroy', $customer))->assertForbidden();
        $this->assertModelExists($customer);
    }

    public function test_crm_supervisor_via_sales_crm_role_can_delete_across_all_three_domains(): void
    {
        $user = $this->makeUser('sales-crm');
        $user->update(['crm_role' => 'supervisor']);

        $lead = $this->makeLead($user);
        $record = $this->makeEbayRecord();
        $shipment = $this->makeShipment();

        $this->actingAs($user)->delete(route('crm.website.destroy', $lead))->assertRedirect();
        $this->actingAs($user)->delete(route('crm.ebay.customers.destroy', $record))->assertRedirect();
        $this->actingAs($user)->delete(route('crm.logistics.shipments.destroy', $shipment))->assertRedirect();

        $this->assertSoftDeleted($lead);
        $this->assertSoftDeleted($record);
        $this->assertSoftDeleted($shipment);
    }

    public function test_admin_crm_can_delete_across_all_three_domains(): void
    {
        $user = $this->makeUser('admin-crm');

        $lead = $this->makeLead($user);
        $record = $this->makeEbayRecord();
        $shipment = $this->makeShipment();

        $this->actingAs($user)->delete(route('crm.website.destroy', $lead))->assertRedirect();
        $this->actingAs($user)->delete(route('crm.ebay.customers.destroy', $record))->assertRedirect();
        $this->actingAs($user)->delete(route('crm.logistics.shipments.destroy', $shipment))->assertRedirect();
    }

    // ── Top-of-hierarchy tiers ────────────────────────────────────────────

    public function test_boss_can_delete_across_all_three_domains(): void
    {
        $user = $this->makeUser('boss');

        $lead = $this->makeLead($user);
        $record = $this->makeEbayRecord();
        $shipment = $this->makeShipment();

        $this->actingAs($user)->delete(route('crm.website.destroy', $lead))->assertRedirect();
        $this->actingAs($user)->delete(route('crm.ebay.customers.destroy', $record))->assertRedirect();
        $this->actingAs($user)->delete(route('crm.logistics.shipments.destroy', $shipment))->assertRedirect();
    }

    public function test_super_admin_can_delete_across_all_three_domains(): void
    {
        $user = $this->makeUser('super-admin');

        $lead = $this->makeLead($user);
        $record = $this->makeEbayRecord();
        $shipment = $this->makeShipment();

        $this->actingAs($user)->delete(route('crm.website.destroy', $lead))->assertRedirect();
        $this->actingAs($user)->delete(route('crm.ebay.customers.destroy', $record))->assertRedirect();
        $this->actingAs($user)->delete(route('crm.logistics.shipments.destroy', $shipment))->assertRedirect();
    }

    // ── Tech Support is blocked from all deletes ─────────────────────────

    public function test_tech_support_is_blocked_from_all_three_domains(): void
    {
        $user = $this->makeUser('tech-support');

        $lead = $this->makeLead($user);
        $record = $this->makeEbayRecord();
        $shipment = $this->makeShipment();

        $this->actingAs($user)->delete(route('crm.website.destroy', $lead))->assertForbidden();
        $this->actingAs($user)->delete(route('crm.ebay.customers.destroy', $record))->assertForbidden();
        $this->actingAs($user)->delete(route('crm.logistics.shipments.destroy', $shipment))->assertForbidden();
    }

    // ── Blade UI gating ───────────────────────────────────────────────────

    public function test_delete_button_is_hidden_from_a_plain_sales_crm_user_but_shown_to_an_ebay_supervisor(): void
    {
        // Route URL alone won't do — update (PUT) and destroy (DELETE) share
        // the identical RESTful URL, so check the delete form's unique id instead.
        $record = $this->makeEbayRecord();

        $plain = $this->makeUser('sales-crm');
        $this->actingAs($plain)->get(route('crm.ebay.customers.edit', $record))
            ->assertOk()->assertDontSee('delete-ebay-record-form', false);

        $supervisor = $this->makeUser('ebay-supervisor');
        $this->actingAs($supervisor)->get(route('crm.ebay.customers.edit', $record))
            ->assertOk()->assertSee('delete-ebay-record-form', false);
    }
}
