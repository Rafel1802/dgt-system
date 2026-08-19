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

class UnifiedCustomerDirectoryTest extends TestCase
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

    public function test_records_sharing_an_email_are_deduplicated(): void
    {
        Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Alice Chen',
            'client_email' => 'alice@email.com',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::SuccessfulLead->value,
            'received_at' => now(),
        ]);

        // Same email as the lead above — should collapse into one directory row.
        EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'buyer_name' => 'Alice Chen',
            'email' => 'alice@email.com',
        ]);

        // Distinct customer — should appear as its own row.
        Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Bora Kim',
            'client_email' => 'bora@email.com',
            'source' => InquirySource::Facebook->value,
            'status' => WebsiteLeadStatus::Contacted->value,
            'received_at' => now(),
        ]);

        $service = app(\App\Services\CrmCustomerMatchService::class);
        $directory = $service->buildUnifiedDirectory();

        $this->assertEquals(2, $directory->count());
    }

    public function test_shipment_problem_customers_appear_and_filter_by_category(): void
    {
        $shipment = Shipment::create(['shipment_code' => 'SHP-DIR-1']);
        ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'recipient_name' => 'Chea Pisach',
            'recipient_email' => 'chea@email.com',
            'shipping_address' => 'Siem Reap',
            'status' => ShipmentCustomer::STATUS_PROBLEM,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.customers.index', [
            'status_filter' => 'Logistic issues',
        ]));

        $response->assertOk();
        $response->assertSee('Chea Pisach');
    }

    public function test_customer_database_page_renders_dedup_count(): void
    {
        Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Solo Customer',
            'client_email' => 'solo@email.com',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewInquiry->value,
            'received_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('crm.customers.index'))
            ->assertOk()
            ->assertSee('1 unique customer');
    }

    public function test_a_customer_flagged_with_shipment_delay_shows_as_logistic_issues_even_without_a_lead_or_ebay_match(): void
    {
        Customer::create([
            'name' => 'Flagged Customer',
            'email' => 'flagged@email.com',
            'shipment_delay' => true,
            'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.customers.index', [
            'status_filter' => 'Logistic issues',
        ]));

        $response->assertOk();
        $response->assertSee('Flagged Customer');
    }

    public function test_records_linked_via_customer_id_are_deduplicated_even_with_mismatched_contact_info(): void
    {
        // Reproduces the real "Marady" duplicate: an eBay record and a
        // ShipmentCustomer both linked to the same Customer via customer_id,
        // but with DIFFERENT emails on file (the shipment recipient's email
        // was mistyped/stale) — should still collapse into one row because
        // the customer_id link is more trustworthy than the contact fields.
        $customer = Customer::create([
            'name' => 'Marady',
            'email' => 'marady@gmail.com',
            'phone' => '09384383242',
            'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Ebay->value,
            'created_by' => $this->user->id,
        ]);

        EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_RESOLVED,
            'buyer_name' => 'Marady',
            'username' => 'marady',
            'email' => 'marady@gmail.com',
            'phone' => '09384383242',
            'shipment_delay' => true,
            'customer_id' => $customer->id,
        ]);

        $shipment = Shipment::create(['shipment_code' => 'SHP-DIR-2']);
        ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'customer_id' => $customer->id,
            'recipient_name' => 'Marady',
            'recipient_email' => 'totally-different@example.com', // mismatched on purpose
            'recipient_phone' => '09384383242',
            'shipping_address' => '',
            'status' => ShipmentCustomer::STATUS_PROBLEM,
        ]);

        $service = app(\App\Services\CrmCustomerMatchService::class);
        $directory = $service->buildUnifiedDirectory();

        $marodyRows = $directory->filter(fn ($row) => $row['name'] === 'Marady');
        $this->assertCount(1, $marodyRows);
    }

    public function test_ebay_rows_link_to_the_detail_page_not_the_edit_form(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'buyer_name' => 'Ebay Row',
            'username' => 'ebayrow',
        ]);

        $service = app(\App\Services\CrmCustomerMatchService::class);
        $row = $service->buildUnifiedDirectory()->firstWhere('name', 'Ebay Row');

        $this->assertEquals(route('crm.ebay.customers.show', $record), $row['link']);
    }

    public function test_logistics_rows_with_a_linked_customer_link_to_that_customer(): void
    {
        $customer = Customer::create([
            'name' => 'Linked Recipient',
            'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value,
            'created_by' => $this->user->id,
        ]);

        $shipment = Shipment::create(['shipment_code' => 'SHP-DIR-3']);
        $shipment->shipmentCustomers()->create([
            'customer_id' => $customer->id,
            'recipient_name' => 'Linked Recipient',
            'shipping_address' => '',
            'status' => ShipmentCustomer::STATUS_PROBLEM,
        ]);

        $service = app(\App\Services\CrmCustomerMatchService::class);
        $row = $service->buildUnifiedDirectory()->firstWhere('name', 'Linked Recipient');

        $this->assertEquals(route('crm.customers.show', $customer), $row['link']);
    }

    public function test_logistics_rows_without_a_linked_customer_fall_back_to_the_shipment_page(): void
    {
        $shipment = Shipment::create(['shipment_code' => 'SHP-DIR-4']);
        $shipment->shipmentCustomers()->create([
            'recipient_name' => 'Unlinked Recipient',
            'shipping_address' => '',
            'status' => ShipmentCustomer::STATUS_PROBLEM,
        ]);

        $service = app(\App\Services\CrmCustomerMatchService::class);
        $row = $service->buildUnifiedDirectory()->firstWhere('name', 'Unlinked Recipient');

        $this->assertEquals(route('crm.logistics.shipments.show', $shipment), $row['link']);
    }

    public function test_directory_route_redirects_to_customers_index(): void
    {
        $this->actingAs($this->user)
            ->get(route('crm.directory.index'))
            ->assertRedirect(route('crm.customers.index'));
    }

    public function test_customer_database_sorts_newest_first_by_default(): void
    {
        Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Older Lead',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewInquiry->value,
            'received_at' => now()->subDays(5),
        ]);
        Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Newer Lead',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewInquiry->value,
            'received_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.customers.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['Newer Lead', 'Older Lead']);
    }

    public function test_customer_database_can_be_filtered_by_created_date_range(): void
    {
        Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'In Range Lead',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewInquiry->value,
            'received_at' => now()->subDays(2),
        ]);
        Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Out Of Range Lead',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewInquiry->value,
            'received_at' => now()->subDays(20),
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.customers.index', [
            'created_from' => now()->subDays(5)->toDateString(),
            'created_to'   => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('In Range Lead');
        $response->assertDontSee('Out Of Range Lead');
    }

    /**
     * Purchase Date and Created Date are separate filters — a lead with no
     * order yet has no purchase date at all, so it must never match a
     * purchase-date filter just because it was created in range.
     */
    public function test_customer_database_can_be_filtered_by_purchase_date_range(): void
    {
        $purchased = Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Purchased In Range',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::SuccessfulLead->value,
            'received_at' => now()->subDays(30),
        ]);
        $purchased->orders()->create(['order_date' => now()->subDays(2), 'created_by' => $this->user->id]);

        $purchasedOutOfRange = Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Purchased Out Of Range',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::SuccessfulLead->value,
            'received_at' => now()->subDays(30),
        ]);
        $purchasedOutOfRange->orders()->create(['order_date' => now()->subDays(20), 'created_by' => $this->user->id]);

        Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'No Purchase Yet',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewInquiry->value,
            'received_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.customers.index', [
            'date_from' => now()->subDays(5)->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Purchased In Range');
        $response->assertDontSee('Purchased Out Of Range');
        $response->assertDontSee('No Purchase Yet');
    }

    public function test_website_and_ebay_customers_sort_on_top_of_logistics_customers_by_default(): void
    {
        $logisticsCustomer = Customer::create([
            'name' => 'Logistics First Customer',
            'email' => 'logistics@example.com',
            'source' => CustomerSource::Logistic->value,
            'status' => CustomerStatus::Active->value,
            'created_by' => $this->user->id,
            'created_at' => now(),
        ]);

        $websiteLead = Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Website Second Customer',
            'client_email' => 'website@example.com',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewInquiry->value,
            'received_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.customers.index'));
        $response->assertOk();
        $response->assertSeeInOrder(['Website Second Customer', 'Logistics First Customer']);
    }

    public function test_logistics_status_syncs_to_customer_directory(): void
    {
        $customer = Customer::create([
            'name' => 'In Transit Recipient',
            'email' => 'intransit@example.com',
            'source' => CustomerSource::Logistic->value,
            'status' => CustomerStatus::Active->value,
            'created_by' => $this->user->id,
        ]);

        $shipment = Shipment::create(['shipment_code' => 'SHP-TRANSIT-1']);
        $shipmentCustomer = $shipment->shipmentCustomers()->create([
            'customer_id' => $customer->id,
            'recipient_name' => 'In Transit Recipient',
            'recipient_email' => 'intransit@example.com',
            'shipping_address' => '',
            'status' => ShipmentCustomer::STATUS_IN_TRANSIT,
        ]);

        \App\Services\CrmCustomerMatchService::forgetUnifiedDirectoryCache();

        $response = $this->actingAs($this->user)->get(route('crm.customers.index'));
        $response->assertOk();
        $response->assertSee('In Transit');
    }

    public function test_ajax_search_and_filter_returns_json_response(): void
    {
        Customer::create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'phone' => '123456789',
            'source' => CustomerSource::Website->value,
            'status' => CustomerStatus::Lead->value,
            'created_by' => $this->user->id,
        ]);

        Customer::create([
            'name' => 'Jane Smith',
            'email' => 'janesmith@example.com',
            'phone' => '987654321',
            'source' => CustomerSource::Ebay->value,
            'status' => CustomerStatus::Lead->value,
            'created_by' => $this->user->id,
        ]);

        \App\Services\CrmCustomerMatchService::forgetUnifiedDirectoryCache();

        // Query with AJAX header and 'John' as search term
        $response = $this->actingAs($this->user)->get(route('crm.customers.index', [
            'search' => 'John',
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'rows',
            'pagination',
            'stats',
            'totalUnique',
            'totalRevenue',
        ]);

        $data = $response->json();
        $this->assertStringContainsString('John Doe', $data['rows']);
        $this->assertStringNotContainsString('Jane Smith', $data['rows']);
        $this->assertStringContainsString('Showing <strong>1</strong> of <strong>1</strong> filtered customer(s)', $data['stats']);
    }

    public function test_filter_by_technical_issues(): void
    {
        $lead = Lead::create([
            'client_name' => 'Technical User',
            'client_email' => 'tech@example.com',
            'client_phone' => '111111',
            'source' => InquirySource::Website->value,
            'status' => \App\Enums\WebsiteLeadStatus::TechnicalIssues,
            'handled_by' => $this->user->id,
        ]);

        $lead2 = Lead::create([
            'client_name' => 'Normal User',
            'client_email' => 'normal@example.com',
            'client_phone' => '222222',
            'source' => InquirySource::Website->value,
            'status' => \App\Enums\WebsiteLeadStatus::NewInquiry,
            'handled_by' => $this->user->id,
        ]);

        \App\Services\CrmCustomerMatchService::forgetUnifiedDirectoryCache();

        $response = $this->actingAs($this->user)->get(route('crm.customers.index', [
            'status_filter' => 'Technical issues',
        ]));

        $response->assertOk();
        $response->assertSee('Technical User');
        $response->assertDontSee('Normal User');
    }
}
