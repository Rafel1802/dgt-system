<?php

namespace Tests\Feature;

use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
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
            'status' => WebsiteLeadStatus::Successful->value,
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
            'status' => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('crm.customers.index'))
            ->assertOk()
            ->assertSee('1 unique customer');
    }

    public function test_directory_route_redirects_to_customers_index(): void
    {
        $this->actingAs($this->user)
            ->get(route('crm.directory.index'))
            ->assertRedirect(route('crm.customers.index'));
    }
}
