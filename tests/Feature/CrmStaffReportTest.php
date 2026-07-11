<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Models\CallReport;
use App\Models\Customer;
use App\Models\EbayCustomerOrder;
use App\Models\EbayCustomerRecord;
use App\Models\EbayCustomerHandlerHistory;
use App\Models\Lead;
use App\Models\LeadProduct;
use App\Models\Product;
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

        $response = $this->actingAs($this->user)->get(route('crm.reports.staff'));

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

        $response = $this->actingAs($this->user)->get(route('crm.reports.staff'));

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
        $response->assertSee('Activity Breakdown by Domain');
    }

    public function test_export_streams_a_csv_for_the_current_period(): void
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
        $this->assertCount(2, $lines); // header + 1 row for "Today"
        $this->assertStringStartsWith('Today,', $lines[1]);
    }

    public function test_switching_period_changes_the_reported_totals(): void
    {
        // Guaranteed to fall within "This Month" but never "Today", regardless
        // of what day of the month the test happens to run on.
        $earlierThisMonth = now()->startOfMonth();
        if ($earlierThisMonth->isToday()) {
            $earlierThisMonth = $earlierThisMonth->copy()->addDay();
        }

        $case = TechSupportCase::create([
            'source_type' => EbayCustomerRecord::class,
            'source_id'   => 1,
            'assigned_to' => $this->user->id,
            'status'      => TechSupportCase::STATUS_NEW,
        ]);
        // created_at isn't mass-assignable, so it's auto-stamped "now" above —
        // force it back to earlier in the month for this test.
        $case->forceFill(['created_at' => $earlierThisMonth])->saveQuietly();

        $today = $this->actingAs($this->user)->get(route('crm.reports.export', ['user' => $this->user, 'period' => 'day']));
        $month = $this->actingAs($this->user)->get(route('crm.reports.export', ['user' => $this->user, 'period' => 'month']));

        $todayLine = array_filter(explode("\n", trim($today->streamedContent())))[1];
        $monthLine = array_filter(explode("\n", trim($month->streamedContent())))[1];

        $this->assertEquals(['Today', '0', '0', '0', '0', '0'], str_getcsv($todayLine));
        $this->assertEquals(['This Month', '0', '0', '1', '0', '1'], str_getcsv($monthLine));
    }

    public function test_team_reports_sales_tiles_are_computed_from_real_order_data(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'buyer_name' => 'Grace Lee',
        ]);
        $order = EbayCustomerOrder::create([
            'ebay_customer_record_id' => $record->id,
            'order_id' => 'ORD-1',
        ]);
        $order->items()->create(['product_name' => 'Excavator Part', 'price' => 150.50]);
        $order->items()->create(['product_name' => 'Filter Kit', 'price' => 49.50]);

        $customer = Customer::create([
            'name' => 'Successful Customer', 'status' => CustomerStatus::Active->value,
            'source' => CustomerSource::Website->value, 'created_by' => $this->user->id,
        ]);
        $lead = Lead::create([
            'customer_id' => $customer->id, 'handled_by' => $this->user->id,
            'client_name' => 'Successful Customer', 'source' => InquirySource::Website->value,
            'status' => WebsiteLeadStatus::Successful->value, 'received_at' => now(),
        ]);
        LeadProduct::create([
            'lead_id' => $lead->id, 'product_name' => 'Skid Steer', 'price' => 500, 'quantity' => 2,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.reports.index', ['period' => 'month']));

        $response->assertOk();
        $response->assertSee('$200.00'); // eBay: 150.50 + 49.50
        $response->assertSee('$1,000.00'); // Website: 500 * 2
        $response->assertSee('$1,200.00'); // Total
    }

    /**
     * A logged order is a real recorded sale regardless of the lead's
     * current pipeline status — same "unconditional" rule eBay sales
     * already follow (not gated by tab_type). A lead can log repeat
     * orders independent of "Successful" via storeOrder(), so gating the
     * total on current status would hide real sales.
     */
    public function test_website_sales_counts_regardless_of_current_lead_status(): void
    {
        $lead = Lead::create([
            'handled_by' => $this->user->id, 'client_name' => 'Reverted Lead',
            'source' => InquirySource::Website->value, 'status' => WebsiteLeadStatus::Contacted->value,
            'received_at' => now(),
        ]);
        LeadProduct::create([
            'lead_id' => $lead->id, 'product_name' => 'Old Sale', 'price' => 999, 'quantity' => 1,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.reports.index', ['period' => 'month']));

        $response->assertOk();
        $response->assertSee('$999.00');
    }

    public function test_team_reports_period_tabs_default_to_month(): void
    {
        $response = $this->actingAs($this->user)->get(route('crm.reports.index'));

        $response->assertOk();
        $response->assertSee('This Month');
    }

    public function test_individual_card_totals_change_with_the_selected_period(): void
    {
        $earlierThisMonth = now()->startOfMonth();
        if ($earlierThisMonth->isToday()) {
            $earlierThisMonth = $earlierThisMonth->copy()->addDay();
        }

        $lead = Lead::create([
            'handled_by' => $this->user->id, 'client_name' => 'Earlier Lead',
            'source' => InquirySource::Website->value, 'status' => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);
        $lead->forceFill(['created_at' => $earlierThisMonth])->saveQuietly();

        $todayResponse = $this->actingAs($this->user)->get(route('crm.reports.show', ['user' => $this->user, 'period' => 'day']));
        $monthResponse = $this->actingAs($this->user)->get(route('crm.reports.show', ['user' => $this->user, 'period' => 'month']));

        // The lead was created earlier this month, not today — the card is
        // still shown (lifetime activity check), but its "Handled" count
        // must reflect only the selected period.
        $todayResponse->assertOk();
        $monthResponse->assertOk();
        $todayResponse->assertViewHas('summary', fn ($summary) => $summary['website']['crm_handled'] === 0);
        $monthResponse->assertViewHas('summary', fn ($summary) => $summary['website']['crm_handled'] === 1);
    }

    public function test_team_reports_has_a_profile_tab_per_domain_plus_general_report(): void
    {
        $response = $this->actingAs($this->user)->get(route('crm.reports.index'));

        $response->assertOk();
        $response->assertSee('📋 General Report', false);
        $response->assertSee('🚚 Logistic', false);
        $response->assertSee('🛒 eBay', false);
        $response->assertSee('🌐 Website', false);
        $response->assertSee('🛠️ Technical Support', false);
    }

    public function test_general_report_tab_includes_every_domains_metrics_and_the_combined_total(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER, 'buyer_name' => 'Grace Lee']);
        $order = EbayCustomerOrder::create(['ebay_customer_record_id' => $record->id, 'order_id' => 'ORD-2']);
        $order->items()->create(['product_name' => 'Part', 'price' => 100]);

        $lead = Lead::create([
            'handled_by' => $this->user->id, 'client_name' => 'Buyer',
            'source' => InquirySource::Website->value, 'status' => WebsiteLeadStatus::Successful->value,
            'received_at' => now(),
        ]);
        LeadProduct::create(['lead_id' => $lead->id, 'product_name' => 'Item', 'price' => 50, 'quantity' => 1]);

        $response = $this->actingAs($this->user)->get(route('crm.reports.index', ['period' => 'month']));

        $response->assertOk();
        // Each domain gets its own heading, and its metric labels appear underneath it.
        $response->assertSeeInOrder(['🛒 eBay', 'Sales']);
        $response->assertSeeInOrder(['🌐 Website', 'Sales']);
        $response->assertSeeInOrder(['🚚 Logistic', 'Shipments Assigned']);
        $response->assertSeeInOrder(['🛠️ Technical Support', 'Cases Assigned']);
        // Combined total: 100 (eBay) + 50 (website) = 150.
        $response->assertSee('$150.00');
    }

    public function test_team_and_staff_reports_are_separate_pages(): void
    {
        $teamResponse = $this->actingAs($this->user)->get(route('crm.reports.index'));
        $staffResponse = $this->actingAs($this->user)->get(route('crm.reports.staff'));

        $teamResponse->assertOk()->assertDontSee('No staff activity recorded for this team yet.');
        $staffResponse->assertOk()->assertSee('No staff activity recorded for this team yet.');

        // Each page links to the other.
        $teamResponse->assertSee(route('crm.reports.staff'), false);
        $staffResponse->assertSee(route('crm.reports.index'), false);
    }
}
