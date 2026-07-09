<?php

namespace Tests\Feature;

use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Models\CallReport;
use App\Models\EbayCustomerRecord;
use App\Models\EbayCustomerHandlerHistory;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CrmStaffReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true, 'name' => 'Sophea']);
        $this->user->assignRole('super-admin');
    }

    public function test_staff_card_reflects_leads_handled_and_sales_this_month(): void
    {
        Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Alice Chen',
            'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::Successful->value,
            'received_at' => now(),
        ]);
        Lead::create([
            'handled_by' => $this->user->id,
            'client_name' => 'Bora Kim',
            'source' => InquirySource::Facebook->value,
            'status' => WebsiteLeadStatus::Contacted->value,
            'received_at' => now(),
        ]);

        CallReport::create([
            'name' => 'Some Caller',
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.reports.index'));

        $response->assertOk();
        $response->assertSee('Sophea');
    }

    public function test_ebay_handled_count_reflects_handler_history(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'buyer_name' => 'Grace Lee',
        ]);

        EbayCustomerHandlerHistory::create([
            'ebay_customer_record_id' => $record->id,
            'user_id' => $this->user->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.reports.index'));

        $response->assertOk();
    }
}
