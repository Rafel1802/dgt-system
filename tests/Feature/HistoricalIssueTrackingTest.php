<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\EbayCustomerHandlerHistory;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\TechSupportCase;
use App\Models\User;
use App\Services\CrmReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HistoricalIssueTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CrmReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');
        
        $this->reportService = app(CrmReportService::class);
    }

    public function test_problem_occurrences_increment_on_shipment_customer()
    {
        $customer = Customer::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'status' => 'active',
            'source' => 'website',
            'created_by' => $this->user->id,
        ]);
        $shipment = Shipment::create(['shipment_code' => 'SHP-TEST']);
        
        $shipmentCustomer = ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'customer_id' => $customer->id,
            'recipient_name' => 'Test',
            'shipping_address' => 'Test',
            'status' => ShipmentCustomer::STATUS_PENDING,
            'problem_occurrences' => 0,
        ]);

        // Trigger update to problem
        $response = $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment->id, $shipmentCustomer->id]), [
            'status' => ShipmentCustomer::STATUS_PROBLEM,
            'recipient_name' => 'Test',
            'shipping_address' => 'Test',
            'notes' => 'Problem occurred',
            'issue_date' => now()->toDateString(),
        ]);
        if ($response->status() !== 302) {
            $response->dump();
        }
        $response->assertSessionHasNoErrors();

        $this->assertEquals(1, $shipmentCustomer->fresh()->problem_occurrences);

        // Update back to pending
        $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment->id, $shipmentCustomer->id]), [
            'status' => ShipmentCustomer::STATUS_PENDING,
            'recipient_name' => 'Test',
            'shipping_address' => 'Test',
        ]);

        // Update to problem again
        $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment->id, $shipmentCustomer->id]), [
            'status' => ShipmentCustomer::STATUS_PROBLEM,
            'recipient_name' => 'Test',
            'shipping_address' => 'Test',
            'notes' => 'Problem occurred again',
            'issue_date' => now()->toDateString(),
        ]);

        $this->assertEquals(2, $shipmentCustomer->fresh()->problem_occurrences);
    }

    public function test_logistic_total_issues_metrics_in_report()
    {
        $logisticUser = User::factory()->create(['is_active' => true]);
        $logisticUser->assignRole('logistic-team');

        $customer = Customer::create([
            'name' => 'Test',
            'email' => 'test@test.com',
            'status' => 'active',
            'source' => 'website',
            'created_by' => $logisticUser->id,
        ]);
        $shipment = Shipment::create(['shipment_code' => 'SHP-TEST2']);
        
        // Create an issue that happened twice
        ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'customer_id' => $customer->id,
            'recipient_name' => 'Test',
            'shipping_address' => 'Test',
            'status' => ShipmentCustomer::STATUS_DELIVERED,
            'handled_by' => $logisticUser->id,
            'problem_occurrences' => 2,
            'created_at' => now(),
        ]);

        // Logistic negative feedback
        $ebayRecord = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'negative_feedback_causes' => ['Logistic issues'],
            'created_at' => now(),
            'buyer_name' => 'Test',
            'status' => 'open'
        ]);
        EbayCustomerHandlerHistory::create([
            'user_id' => $logisticUser->id,
            'ebay_customer_record_id' => $ebayRecord->id,
            'started_at' => now(),
        ]);

        // Domain report
        $reports = $this->reportService->buildDomainReports(now()->subDay(), now()->addDay());
        $logisticMetrics = $reports['logistic']['metrics'];
        
        if ($logisticMetrics['Total Issues'] == 0) {
            dump(User::role(['logistic-team'])->pluck('id')->toArray());
            dump(\App\Models\ShipmentCustomer::all()->toArray());
        }
        
        $this->assertEquals(2, $logisticMetrics['Total Issues']);
        $this->assertEquals(1, $logisticMetrics['Negative Feedback Caused by Logistic Issues']);

        // Team report
        $logisticSummary = $this->reportService->logisticSummary($logisticUser, now()->subDay(), now()->addDay());
        
        $this->assertEquals(2, $logisticSummary['Total Issues']);
        $this->assertEquals(1, $logisticSummary['Negative Feedback']);
    }

    public function test_tech_support_total_issues_metrics_in_report()
    {
        $techUser = User::factory()->create(['is_active' => true]);
        $techUser->assignRole('tech-support');

        // Create tech support case
        TechSupportCase::create([
            'assigned_to' => $techUser->id,
            'status' => TechSupportCase::STATUS_RESOLVED,
            'occurrence_count' => 3,
            'created_at' => now(),
            'client_name' => 'Test',
            'source_type' => 'website',
            'source_id' => 1,
        ]);

        // Tech support negative feedback
        $ebayRecord = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'negative_feedback_causes' => ['Technical'],
            'created_at' => now(),
            'buyer_name' => 'Test',
            'status' => 'open'
        ]);
        EbayCustomerHandlerHistory::create([
            'user_id' => $techUser->id,
            'ebay_customer_record_id' => $ebayRecord->id,
            'started_at' => now(),
        ]);

        // Domain report
        $reports = $this->reportService->buildDomainReports(now()->subDay(), now()->addDay());
        $techMetrics = $reports['tech_support']['metrics'];
        
        $this->assertEquals(4, $techMetrics['Total Issues']);
        $this->assertEquals(1, $techMetrics['Negative Feedback Caused by Technical Issues']);

        // Team report
        $techSummary = $this->reportService->techSupportSummary($techUser, now()->subDay(), now()->addDay());
        
        $this->assertEquals(3, $techSummary['Total Issues']);
        $this->assertEquals(1, $techSummary['Negative Feedback']);
    }
}
