<?php

namespace Tests\Feature;

use App\Enums\WebsiteLeadStatus;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\Lead;
use App\Models\MachineReturn;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\TechSupportCase;
use App\Models\User;
use App\Events\CustomerStatusUpdatedLive;
use App\Events\CustomerDataUpdatedLive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CrmRealTimeUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
        
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');
    }

    public function test_main_dashboard_customer_update_triggers_real_time_event()
    {
        Event::fake([CustomerStatusUpdatedLive::class]);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@test.com',
            'status' => 'prospect',
            'source' => 'website',
            'pipeline_stage' => \App\Enums\DealStage::NewLead->value,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('crm.customers.update', $customer), [
            'name' => 'Updated Name',
            'status' => 'active',
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        Event::assertDispatched(CustomerStatusUpdatedLive::class, function ($event) use ($customer) {
            return $event->customerId === $customer->id && $event->customerType === 'customer';
        });
    }

    public function test_ebay_customer_status_update_triggers_real_time_event()
    {
        $this->withoutExceptionHandling();
        Event::fake([CustomerStatusUpdatedLive::class]);

        $ebayRecord = EbayCustomerRecord::create([
            'order_id' => 'EBAY-123',
            'buyer_username' => 'buyer123',
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
        ]);

        try {
            $response = $this->actingAs($this->admin)->patchJson(route('crm.ebay.customers.status.update', $ebayRecord), [
                'tab_type' => EbayCustomerRecord::TAB_TECHNICAL,
                'note' => 'Issue found',
            ]);
            $response->assertOk();
        } catch (\Throwable $e) {
            dump($e->getFile() . ':' . $e->getLine());
            dump($e->getTraceAsString());
            throw $e;
        }

        Event::assertDispatched(CustomerStatusUpdatedLive::class, function ($event) use ($ebayRecord) {
            return $event->customerId === $ebayRecord->id && $event->customerType === 'ebay';
        });
    }

    public function test_tech_support_status_update_triggers_real_time_event()
    {
        Event::fake([CustomerStatusUpdatedLive::class]);

        $lead = Lead::create([
            'client_name' => 'Test Lead',
            'email' => 'lead@test.com',
            'status' => WebsiteLeadStatus::NewInquiry->value,
            'source' => \App\Enums\InquirySource::Website->value,
            'handled_by' => $this->admin->id,
        ]);
        
        $case = TechSupportCase::create([
            'source_type' => Lead::class,
            'source_id'   => $lead->id,
            'status'      => TechSupportCase::STATUS_NEW,
            'occurrence_count' => 1,
        ]);

        $response = $this->actingAs($this->admin)->patchJson(route('crm.tech-support.status', $case), [
            'status' => TechSupportCase::STATUS_RESOLVED,
            'note' => 'Resolved the issue',
        ]);

        $response->assertOk();

        Event::assertDispatched(CustomerStatusUpdatedLive::class, function ($event) use ($case) {
            return $event->customerId === $case->id && $event->customerType === 'tech';
        });
    }

    public function test_website_leads_status_update_triggers_real_time_event()
    {
        Event::fake([CustomerDataUpdatedLive::class, CustomerStatusUpdatedLive::class]);

        $lead = Lead::create([
            'client_name' => 'Test Lead',
            'email' => 'lead2@test.com',
            'status' => WebsiteLeadStatus::NewInquiry->value,
            'source' => \App\Enums\InquirySource::Website->value,
            'handled_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patchJson(route('crm.website.status', $lead), [
            'status' => WebsiteLeadStatus::Contacted->value,
        ]);

        $response->assertOk();

        Event::assertDispatched(CustomerDataUpdatedLive::class, function ($event) use ($lead) {
            return $event->customerId === $lead->id && $event->customerType === 'lead';
        });
        
        Event::assertDispatched(CustomerStatusUpdatedLive::class, function ($event) use ($lead) {
            return $event->customerId === $lead->id && $event->customerType === 'website';
        });
    }

    public function test_logistics_status_update_triggers_real_time_event()
    {
        Event::fake([CustomerStatusUpdatedLive::class]);

        $customer = Customer::create([
            'name' => 'Logistics Customer',
            'email' => 'logistics@test.com',
            'status' => 'prospect',
            'source' => 'website',
            'created_by' => $this->admin->id,
        ]);
        
        $shipment = Shipment::create(['shipment_code' => 'SHP-999']);
        
        $shipmentCustomer = ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'customer_id' => $customer->id,
            'recipient_name' => 'Test Name',
            'recipient_phone' => '12345678',
            'shipping_address' => 'Test Address',
            'status'      => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->put(route('crm.logistics.shipments.customers.updateDirect', $shipmentCustomer), [
            'status' => 'in_transit',
            'note' => 'Loaded into truck',
            'shipping_address' => '123 Test St',
            'recipient_name' => 'Test Recipient',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        Event::assertDispatched(CustomerStatusUpdatedLive::class, function ($event) use ($shipmentCustomer) {
            return $event->customerId === $shipmentCustomer->id && $event->customerType === 'logistic';
        });
    }
}
