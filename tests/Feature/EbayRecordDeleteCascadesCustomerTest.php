<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\EbayCustomerRecord;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EbayRecordDeleteCascadesCustomerTest extends TestCase
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

    public function test_deleting_an_ebay_record_linked_to_a_customer_permanently_deletes_the_customer_too(): void
    {
        $customer = Customer::create([
            'name' => 'Ebay Cascade Target', 'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Ebay->value, 'created_by' => $this->user->id,
        ]);
        $customer->interactions()->create([
            'user_id' => $this->user->id, 'type' => 'note', 'subject' => 'Note',
            'content' => 'Hello', 'outcome' => 'neutral', 'interacted_at' => now(),
        ]);
        $lead = Lead::create([
            'customer_id' => $customer->id, 'handled_by' => $this->user->id,
            'client_name' => 'Ebay Cascade Target', 'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewLead->value, 'received_at' => now(),
        ]);
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'buyer_name' => 'Ebay Cascade Target',
            'username' => 'ebaycascadetarget',
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('crm.ebay.customers.destroy', $record))
            ->assertRedirect(route('crm.ebay.customers.index', ['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER]));

        // The customer and everything tied to them is permanently gone —
        // including the lead, which wasn't the record that was clicked —
        // since the whole customer cascades, matching Lead::destroy().
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('ebay_customer_records', ['id' => $record->id]);
        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
        $this->assertEquals(0, CustomerInteraction::where('customer_id', $customer->id)->count());
    }

    public function test_deleting_a_standalone_ebay_record_with_no_customer_only_deletes_the_record(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'buyer_name' => 'No Customer Link',
            'username' => 'nocustomerlink',
        ]);

        $this->actingAs($this->user)
            ->delete(route('crm.ebay.customers.destroy', $record))
            ->assertRedirect(route('crm.ebay.customers.index', ['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER]));

        $this->assertSoftDeleted($record);
    }
}
