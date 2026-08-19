<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\Lead;
use App\Models\Shipment;
use App\Models\TechSupportCase;
use App\Models\User;
use App\Enums\WebsiteLeadStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TechSupportViewOnlyRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected User $techSupportUser;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole('super-admin');

        $this->techSupportUser = User::factory()->create(['is_active' => true]);
        $this->techSupportUser->assignRole('tech-support');
    }

    /** @test */
    public function test_tech_support_users_can_view_general_crm_pages_but_are_blocked_from_creation_and_editing()
    {
        $this->actingAs($this->techSupportUser);

        // 1. Customers Database
        $customer = Customer::create([
            'name' => 'John Doe',
            'created_by' => $this->adminUser->id
        ]);
        $this->get(route('crm.customers.index'))->assertStatus(200);
        $this->get(route('crm.customers.show', $customer))->assertStatus(200);
        $this->get(route('crm.customers.create'))->assertStatus(403);
        $this->get(route('crm.customers.edit', $customer))->assertStatus(403);

        // 2. Website CRM
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'client_name' => 'John Doe',
            'source' => 'whatsapp',
            'status' => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
            'handled_by' => $this->adminUser->id
        ]);
        $this->get(route('crm.website.index'))->assertStatus(200);
        $this->get(route('crm.website.show', $lead))->assertStatus(200);
        $this->get(route('crm.website.create'))->assertStatus(403);
        $this->get(route('crm.website.edit', $lead))->assertStatus(403);

        // 3. eBay CRM Customers
        $ebayRecord = EbayCustomerRecord::create([
            'username' => 'john_ebay',
            'buyer_name' => 'John Doe',
            'tab_type' => 'follow-up'
        ]);
        $this->get(route('crm.ebay.customers.index'))->assertStatus(200);
        $this->get(route('crm.ebay.customers.show', $ebayRecord))->assertStatus(200);
        $this->get(route('crm.ebay.customers.create'))->assertStatus(403);
        $this->get(route('crm.ebay.customers.edit', $ebayRecord))->assertStatus(403);

        // 4. Logistics Shipments
        $shipment = Shipment::create([
            'shipment_code' => 'SHIP123',
            'status' => 'pending'
        ]);
        $this->get(route('crm.logistics.shipments.index'))->assertStatus(200);
        $this->get(route('crm.logistics.shipments.show', $shipment))->assertStatus(200);
        $this->get(route('crm.logistics.shipments.create'))->assertStatus(403);
        $this->get(route('crm.logistics.shipments.edit', $shipment))->assertStatus(403);
    }

    /** @test */
    public function test_tech_support_users_are_blocked_from_writing_data_on_general_crm_pages()
    {
        $this->actingAs($this->techSupportUser);

        $customer = Customer::create([
            'name' => 'John Doe',
            'created_by' => $this->adminUser->id
        ]);

        // Attempting to route customer queue should fail
        $this->post(route('crm.customers.route', $customer), [
            'to_queue' => 'ebay',
            'feedback_category' => 'Technical Issue',
            'reason' => 'Test route'
        ])->assertStatus(403);

        // Attempting to update customer should fail
        $this->put(route('crm.customers.update', $customer), [
            'name' => 'Modified Name'
        ])->assertStatus(403);

        // Attempting to delete customer should fail
        $this->delete(route('crm.customers.destroy', $customer))->assertStatus(403);
    }

    /** @test */
    public function test_tech_support_users_retain_full_write_and_read_access_to_technical_support_cases()
    {
        $this->actingAs($this->techSupportUser);

        $customer = Customer::create([
            'name' => 'John Doe',
            'created_by' => $this->adminUser->id
        ]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'client_name' => 'John Doe',
            'source' => 'whatsapp',
            'status' => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
            'handled_by' => $this->adminUser->id
        ]);
        $case = TechSupportCase::create([
            'customer_id' => $customer->id,
            'status' => 'unassigned',
            'inquiry_source' => 'website',
            'source_type' => Lead::class,
            'source_id' => $lead->id
        ]);

        // Tech support can view the index and detail of support cases
        $this->get(route('crm.tech-support.index'))->assertStatus(200);
        $this->get(route('crm.tech-support.show', $case))->assertStatus(200);

        // Tech support can post status updates on tech-support cases
        $this->patch(route('crm.tech-support.status', $case), [
            'status' => 'assigned',
            'note' => 'Assigning case'
        ])->assertStatus(302); // Redirects on success
    }
}
