<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\EbayStore;
use App\Models\Lead;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class IssueDateRequirementTest extends TestCase
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

    public function test_website_crm_requires_issue_date_for_technical_support(): void
    {
        $lead = Lead::create([
            'client_name' => 'John Doe',
            'client_phone' => '123456',
            'source' => 'website',
            'status' => 'new_lead',
            'handled_by' => $this->user->id,
        ]);

        // Attempting without issue_date should fail validation (422)
        $failResponse = $this->actingAs($this->user)->patchJson(route('crm.website.status', $lead), [
            'status' => 'technical_support',
            'note' => 'Hardware malfunction',
        ]);

        $failResponse->assertStatus(422);
        $failResponse->assertJsonFragment(['message' => 'The issue date field is required when status is technical_support.']);

        // Providing issue_date should succeed (200)
        $successResponse = $this->actingAs($this->user)->patchJson(route('crm.website.status', $lead), [
            'status' => 'technical_support',
            'note' => 'Hardware malfunction',
            'issue_date' => '2026-08-15',
        ]);

        $successResponse->assertOk();
        $this->assertEquals('technical_support', $lead->fresh()->status->value);
    }

    public function test_ebay_crm_requires_date_for_technical_and_negative_feedback(): void
    {
        $store = EbayStore::create(['store_name' => 'Test Store']);

        // Technical Issues without date should fail
        $failResponse = $this->actingAs($this->user)->post(route('crm.ebay.customers.store'), [
            'tab_type' => EbayCustomerRecord::TAB_TECHNICAL,
            'username' => 'buyer123',
            'ebay_store_id' => $store->id,
            'informations' => 'Broken screen',
        ]);

        $failResponse->assertSessionHasErrors(['date']);

        // Technical Issues with date should succeed
        $successResponse = $this->actingAs($this->user)->post(route('crm.ebay.customers.store'), [
            'tab_type' => EbayCustomerRecord::TAB_TECHNICAL,
            'username' => 'buyer123',
            'ebay_store_id' => $store->id,
            'informations' => 'Broken screen',
            'date' => '2026-08-16',
        ]);

        $successResponse->assertRedirect(route('crm.ebay.customers.index', ['tab_type' => EbayCustomerRecord::TAB_TECHNICAL]));
        $record = EbayCustomerRecord::where('username', 'buyer123')->first();
        $this->assertNotNull($record);
        $this->assertStringContainsString('2026-08-16', (string) $record->date);
    }

    public function test_logistics_crm_requires_issue_date_for_problem_status(): void
    {
        $shipment = Shipment::create([
            'shipment_code' => 'SHIP-100',
            'status' => 'processing',
        ]);

        $customer = ShipmentCustomer::create([
            'shipment_id' => $shipment->id,
            'recipient_name' => 'Jane Smith',
            'shipping_address' => '123 Main St',
            'status' => 'pending',
        ]);

        // Attempting without issue_date should fail
        $failResponse = $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'Jane Smith',
            'shipping_address' => '123 Main St',
            'status' => 'problem',
            'notes' => 'Damaged box',
        ]);

        $failResponse->assertSessionHasErrors(['issue_date']);

        // Providing issue_date should succeed
        $successResponse = $this->actingAs($this->user)->put(route('crm.logistics.shipments.customers.update', [$shipment, $customer]), [
            'recipient_name' => 'Jane Smith',
            'shipping_address' => '123 Main St',
            'status' => 'problem',
            'notes' => 'Damaged box',
            'issue_date' => '2026-08-17',
        ]);

        $successResponse->assertRedirect(route('crm.logistics.shipments.show', $shipment));
        $this->assertEquals('problem', $customer->fresh()->status);
        $this->assertStringContainsString('Issue Date: 17 Aug 2026', $customer->fresh()->notes);
    }
}
