<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LeadDeleteCascadesCustomerTest extends TestCase
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

    public function test_deleting_a_lead_linked_to_a_customer_permanently_deletes_the_customer_too(): void
    {
        $customer = Customer::create([
            'name' => 'Cascade Target', 'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value, 'created_by' => $this->user->id,
        ]);
        $customer->interactions()->create([
            'user_id' => $this->user->id, 'type' => 'note', 'subject' => 'Note',
            'content' => 'Hello', 'outcome' => 'neutral', 'interacted_at' => now(),
        ]);
        $otherLead = Lead::create([
            'customer_id' => $customer->id, 'handled_by' => $this->user->id,
            'client_name' => 'Cascade Target', 'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewLead->value, 'received_at' => now(),
        ]);
        $leadToDelete = Lead::create([
            'customer_id' => $customer->id, 'handled_by' => $this->user->id,
            'client_name' => 'Cascade Target', 'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewLead->value, 'received_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->delete(route('crm.website.destroy', $leadToDelete))
            ->assertRedirect(route('crm.website.index'));

        // The customer and everything tied to them is permanently gone —
        // not soft-deleted — including the OTHER lead that wasn't the one
        // clicked, since the whole customer cascades.
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('leads', ['id' => $leadToDelete->id]);
        $this->assertDatabaseMissing('leads', ['id' => $otherLead->id]);
        $this->assertEquals(0, CustomerInteraction::where('customer_id', $customer->id)->count());
    }

    public function test_deleting_a_standalone_lead_with_no_customer_only_deletes_the_lead(): void
    {
        $lead = Lead::create([
            'handled_by' => $this->user->id, 'client_name' => 'No Customer Link',
            'source' => InquirySource::Website->value, 'status' => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->delete(route('crm.website.destroy', $lead))
            ->assertRedirect(route('crm.website.index'));

        $this->assertSoftDeleted($lead);
    }
}
