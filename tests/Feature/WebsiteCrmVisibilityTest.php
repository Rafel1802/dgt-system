<?php

namespace Tests\Feature;

use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WebsiteCrmVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    public function test_sales_crm_only_sees_leads_they_handle(): void
    {
        $salesRep = User::factory()->create(['is_active' => true]);
        $salesRep->assignRole('sales-crm');

        $otherRep = User::factory()->create(['is_active' => true]);
        $otherRep->assignRole('sales-crm');

        Lead::create([
            'handled_by' => $salesRep->id,
            'client_name' => 'My Own Lead',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);
        Lead::create([
            'handled_by' => $otherRep->id,
            'client_name' => 'Someone Elses Lead',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);

        $response = $this->actingAs($salesRep)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertSee('My Own Lead');
        $response->assertDontSee('Someone Elses Lead');
    }

    public function test_super_admin_sees_all_leads(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $rep = User::factory()->create(['is_active' => true]);
        $rep->assignRole('sales-crm');

        Lead::create([
            'handled_by' => $rep->id,
            'client_name' => 'Reps Lead',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertSee('Reps Lead');
    }
}
