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
}
