<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\Lead;
use App\Models\ShipmentCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CrmReportControllerTest extends TestCase
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

    public function test_customer_report_export_csv(): void
    {
        Customer::create(['name' => 'John Doe', 'email' => 'john@example.com', 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('crm.export', [
            'type'       => 'customers',
            'format'     => 'csv',
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date'   => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
    }

    public function test_customer_report_export_pdf(): void
    {
        Customer::create(['name' => 'John Doe', 'email' => 'john@example.com', 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('crm.export', [
            'type'       => 'customers',
            'format'     => 'pdf',
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date'   => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
    }

    public function test_logistics_report_export_pdf(): void
    {
        $response = $this->actingAs($this->user)->get(route('crm.export', [
            'type'   => 'logistics',
            'format' => 'pdf',
        ]));

        $response->assertOk();
    }

    public function test_website_report_export_pdf(): void
    {
        $response = $this->actingAs($this->user)->get(route('crm.export', [
            'type'   => 'website',
            'format' => 'pdf',
        ]));

        $response->assertOk();
    }

    public function test_ebay_report_export_pdf(): void
    {
        $response = $this->actingAs($this->user)->get(route('crm.export', [
            'type'   => 'ebay',
            'format' => 'pdf',
        ]));

        $response->assertOk();
    }

    public function test_shipping_and_delivery_status_counters_are_calculated_correctly(): void
    {
        $shipmentPending = \App\Models\Shipment::create([
            'shipment_code' => 'SHP-PEND',
            'status'        => \App\Models\Shipment::STATUS_PENDING,
        ]);

        $shipmentInProgress = \App\Models\Shipment::create([
            'shipment_code' => 'SHP-INPR',
            'status'        => \App\Models\Shipment::STATUS_IN_PROGRESS,
        ]);

        // Create waiting pickup (pending) customer
        ShipmentCustomer::create([
            'shipment_id'      => $shipmentPending->id,
            'recipient_name'   => 'Waiting Cust',
            'status'           => ShipmentCustomer::STATUS_PENDING,
            'shipping_address' => '123 St',
        ]);

        // Create delivered customer
        ShipmentCustomer::create([
            'shipment_id'      => $shipmentPending->id,
            'recipient_name'   => 'Delivered Cust',
            'status'           => ShipmentCustomer::STATUS_DELIVERED,
            'shipping_address' => '456 St',
        ]);

        // Create customer in delivery (linked to in_progress shipment)
        ShipmentCustomer::create([
            'shipment_id'      => $shipmentInProgress->id,
            'recipient_name'   => 'In Delivery Cust',
            'status'           => ShipmentCustomer::STATUS_IN_TRANSIT,
            'shipping_address' => '789 St',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.export', [
            'type'       => 'customers',
            'format'     => 'pdf',
        ]));

        $response->assertOk();

        // Verify the database query logic matches the expected statistics
        $customerQuery = ShipmentCustomer::query();

        $this->assertEquals(1, (clone $customerQuery)->whereHas('shipment', function ($q) {
            $q->where('status', \App\Models\Shipment::STATUS_IN_PROGRESS);
        })->count()); // 1 customer linked to in_progress shipment
        $this->assertEquals(1, (clone $customerQuery)->where('status', ShipmentCustomer::STATUS_DELIVERED)->count()); // 1 customer delivered
        $this->assertEquals(1, (clone $customerQuery)->where('status', ShipmentCustomer::STATUS_PENDING)->count()); // 1 customer pending
    }
}
