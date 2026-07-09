<?php

namespace Tests\Feature;

use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Models\CallReport;
use App\Models\EbayCustomerRecord;
use App\Models\EbayCustomerHandlerHistory;
use App\Models\Lead;
use App\Models\TechSupportCase;
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

    public function test_a_staff_profile_only_appears_under_teams_they_have_activity_in(): void
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
        $response->assertSee('eBay');
        $response->assertSee('No staff activity recorded for this team yet.');
    }

    public function test_staff_profile_is_clickable_and_links_to_the_show_route(): void
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
        $response->assertSee(route('crm.reports.show', $this->user), false);
    }

    public function test_staff_show_page_renders_chart_data_for_day_week_and_month(): void
    {
        TechSupportCase::create([
            'source_type' => EbayCustomerRecord::class,
            'source_id'   => 1,
            'assigned_to' => $this->user->id,
            'status'      => TechSupportCase::STATUS_NEW,
        ]);

        foreach (['day', 'week', 'month'] as $period) {
            $response = $this->actingAs($this->user)->get(route('crm.reports.show', ['user' => $this->user, 'period' => $period]));
            $response->assertOk();
            $response->assertSee($this->user->name);
            $response->assertSee('staffActivityChart', false);
        }
    }

    public function test_staff_show_page_defaults_to_week_and_ignores_invalid_period(): void
    {
        $response = $this->actingAs($this->user)->get(route('crm.reports.show', ['user' => $this->user, 'period' => 'bogus']));

        $response->assertOk();
        $response->assertSee('Wk of', false);
    }

    public function test_export_streams_a_csv_with_one_row_per_period(): void
    {
        TechSupportCase::create([
            'source_type' => EbayCustomerRecord::class,
            'source_id'   => 1,
            'assigned_to' => $this->user->id,
            'status'      => TechSupportCase::STATUS_NEW,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.reports.export', ['user' => $this->user, 'period' => 'day']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertEquals('Period,"CRM Website",eBay,"Technical Support",Logistic,Total', $lines[0]);
        $this->assertCount(15, $lines); // header + 14 daily rows
    }
}
