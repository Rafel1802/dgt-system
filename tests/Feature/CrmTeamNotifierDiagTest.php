<?php
namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Events\InstantNotificationBroadcast;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\Lead;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\TechSupportCase;
use App\Models\TruckingCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CrmTeamNotifierDiagTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $ebayUser;
    protected User $salesUser;
    protected User $tech;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        $this->ebayUser = User::factory()->create(['is_active' => true]);
        $this->ebayUser->assignRole('ebay-team');

        $this->salesUser = User::factory()->create(['is_active' => true]);
        $this->salesUser->assignRole('sales-crm');

        $this->tech = User::factory()->create(['is_active' => true]);
        $this->tech->assignRole('tech-support');
    }

    public function test_tech_case_status_change_notifies_ebay_and_sales_teams(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $customer = Customer::create(['name' => 'Notify Test', 'status' => 'lead', 'source' => 'website', 'created_by' => $this->admin->id]);
        $case = TechSupportCase::create([
            'source_type' => Customer::class,
            'source_id'   => $customer->id,
            'customer_id' => $customer->id,
            'status'      => TechSupportCase::STATUS_NEW,
            'occurrence_count' => 1,
        ]);

        $this->actingAs($this->tech)->patchJson(
            route('crm.tech-support.status', $case),
            ['status' => TechSupportCase::STATUS_IN_PROGRESS]
        )->assertOk();

        Event::assertDispatched(InstantNotificationBroadcast::class, fn ($e) => $e->userId === $this->ebayUser->id && $e->payload['data']['type'] === 'tech_case_status_changed');
        Event::assertDispatched(InstantNotificationBroadcast::class, fn ($e) => $e->userId === $this->salesUser->id && $e->payload['data']['type'] === 'tech_case_status_changed');
        // The actor (tech-support) shouldn't get their own action's notification via this path.
        Event::assertNotDispatched(InstantNotificationBroadcast::class, fn ($e) => $e->userId === $this->tech->id && $e->payload['data']['type'] === 'tech_case_status_changed');
    }

    public function test_ebay_negative_feedback_notifies_ebay_and_sales_teams(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_TECHNICAL,
            'username' => 'neg_buyer',
        ]);

        $response = $this->actingAs($this->admin)->put(route('crm.ebay.customers.update', $record), [
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'username' => 'neg_buyer',
            'informations' => 'Customer left negative feedback about shipping delay.',
        ]);
        $response->assertSessionDoesntHaveErrors();

        Event::assertDispatched(InstantNotificationBroadcast::class, fn ($e) => $e->userId === $this->ebayUser->id && $e->payload['data']['type'] === 'ebay_negative_feedback');
        Event::assertDispatched(InstantNotificationBroadcast::class, fn ($e) => $e->userId === $this->salesUser->id && $e->payload['data']['type'] === 'ebay_negative_feedback');
    }

    public function test_logistic_problem_notifies_ebay_and_sales_teams(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $company = TruckingCompany::create(['company_name' => 'FWD', 'is_active' => true]);
        $shipment = Shipment::create(['status' => Shipment::STATUS_IN_PROGRESS, 'trucking_company_id' => $company->id]);
        $shipmentCustomer = $shipment->shipmentCustomers()->create([
            'recipient_name' => 'Logistic Test', 'recipient_email' => 'logistic-test@example.com', 'shipping_address' => '', 'status' => ShipmentCustomer::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)->put(route('crm.logistics.shipments.customers.update', [$shipment, $shipmentCustomer]), [
            'recipient_name' => 'Logistic Test', 'recipient_email' => 'logistic-test@example.com', 'status' => ShipmentCustomer::STATUS_PROBLEM, 'notes' => 'Truck broke down.',
        ]);
        $response->assertSessionDoesntHaveErrors();

        Event::assertDispatched(InstantNotificationBroadcast::class, fn ($e) => $e->userId === $this->ebayUser->id && $e->payload['data']['type'] === 'logistic_problem');
        Event::assertDispatched(InstantNotificationBroadcast::class, fn ($e) => $e->userId === $this->salesUser->id && $e->payload['data']['type'] === 'logistic_problem');
    }

    public function test_logistic_problem_does_not_renotify_on_repeat_save(): void
    {
        Event::fake([InstantNotificationBroadcast::class]);

        $company = TruckingCompany::create(['company_name' => 'FWD2', 'is_active' => true]);
        $shipment = Shipment::create(['status' => Shipment::STATUS_IN_PROGRESS, 'trucking_company_id' => $company->id]);
        $shipmentCustomer = $shipment->shipmentCustomers()->create([
            'recipient_name' => 'Repeat Save Test', 'recipient_email' => 'repeat-save@example.com', 'shipping_address' => '', 'status' => ShipmentCustomer::STATUS_PROBLEM, 'notes' => 'Already flagged.',
        ]);
        app(\App\Services\CrmCustomerMatchService::class)->syncShipmentDelayFlags($shipmentCustomer);

        Event::fake([InstantNotificationBroadcast::class]);

        // Saving again while still Problem shouldn't re-notify.
        $this->actingAs($this->admin)->put(route('crm.logistics.shipments.customers.update', [$shipment, $shipmentCustomer]), [
            'recipient_name' => 'Repeat Save Test', 'recipient_email' => 'repeat-save@example.com', 'status' => ShipmentCustomer::STATUS_PROBLEM, 'notes' => 'Still broken.',
        ]);

        Event::assertNotDispatched(InstantNotificationBroadcast::class, fn ($e) => $e->payload['data']['type'] === 'logistic_problem');
    }
}
