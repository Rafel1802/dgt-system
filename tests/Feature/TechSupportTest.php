<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\Lead;
use App\Models\TechSupportCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TechSupportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $tech;
    protected User $salesOnly;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        $this->tech = User::factory()->create(['is_active' => true]);
        $this->tech->assignRole('tech-support');

        $this->salesOnly = User::factory()->create(['is_active' => true]);
        $this->salesOnly->assignRole('sales-crm');

        // Default actor for raw model-level status changes below (mirrors real
        // usage, where these always happen inside an authenticated request);
        // individual HTTP calls still override this via their own actingAs().
        $this->actingAs($this->admin);
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'name'       => 'Test Customer',
            'status'     => CustomerStatus::Lead->value,
            'source'     => CustomerSource::Website->value,
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    public function test_lead_entering_technical_support_status_creates_a_case_and_notifies_technicians(): void
    {
        $customer = $this->makeCustomer(['name' => 'Maria Lopez', 'email' => 'maria@example.com']);

        $lead = Lead::create([
            'customer_id'  => $customer->id,
            'handled_by'   => $this->admin->id,
            'client_name'  => 'Maria Lopez',
            'client_email' => 'maria@example.com',
            'source'       => InquirySource::WhatsApp->value,
            'status'       => WebsiteLeadStatus::NewLead->value,
            'received_at'  => now(),
        ]);

        $lead->update(['status' => WebsiteLeadStatus::TechnicalSupport]);

        $case = TechSupportCase::firstOrFail();
        $this->assertEquals(Lead::class, $case->source_type);
        $this->assertEquals($lead->id, $case->source_id);
        $this->assertEquals(TechSupportCase::STATUS_NEW, $case->status);
        $this->assertEquals($customer->id, $case->customer_id);

        $this->assertDatabaseHas('customer_interactions', [
            'customer_id' => $customer->id,
            'subject'     => 'Technical Case Created',
        ]);
        $this->assertEquals(1, $this->tech->fresh()->notifications()->count());
    }

    public function test_flipping_status_back_and_forth_does_not_create_duplicate_open_cases(): void
    {
        $lead = Lead::create([
            'handled_by'  => $this->admin->id,
            'client_name' => 'Maria Lopez',
            'source'      => InquirySource::WhatsApp->value,
            'status'      => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);

        $lead->update(['status' => WebsiteLeadStatus::TechnicalSupport]);
        $lead->update(['status' => WebsiteLeadStatus::Contacted]);
        $lead->update(['status' => WebsiteLeadStatus::TechnicalSupport]);

        $this->assertEquals(1, TechSupportCase::count());
    }

    public function test_ebay_record_entering_technical_issues_creates_a_case(): void
    {
        $customer = $this->makeCustomer(['name' => 'Kevin Wu']);

        $record = EbayCustomerRecord::create([
            'tab_type'    => EbayCustomerRecord::TAB_URGENT,
            'username'    => 'kevin_w',
            'customer_id' => $customer->id,
        ]);

        $record->update(['tab_type' => EbayCustomerRecord::TAB_TECHNICAL]);

        $case = TechSupportCase::firstOrFail();
        $this->assertEquals(EbayCustomerRecord::class, $case->source_type);
        $this->assertEquals($record->id, $case->source_id);
    }

    public function test_moving_a_case_to_in_progress_acknowledges_it_and_marks_the_new_case_notification_read(): void
    {
        // Go through the real auto-creation flow so a "new case" notification actually exists to be cleared.
        $lead = Lead::create([
            'handled_by'  => $this->admin->id,
            'client_name' => 'Maria Lopez',
            'source'      => InquirySource::WhatsApp->value,
            'status'      => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);
        $lead->update(['status' => WebsiteLeadStatus::TechnicalSupport]);

        $case = TechSupportCase::firstOrFail();
        $this->assertEquals(1, $this->tech->fresh()->unreadNotifications()->count());

        $response = $this->actingAs($this->tech)->patchJson(
            route('crm.tech-support.status', $case),
            ['status' => TechSupportCase::STATUS_IN_PROGRESS]
        );

        $response->assertOk();
        $case->refresh();
        $this->assertEquals(TechSupportCase::STATUS_IN_PROGRESS, $case->status);
        $this->assertNotNull($case->acknowledged_at);
        $this->assertEquals(0, $this->tech->fresh()->unreadNotifications()->count());
    }

    public function test_sales_role_can_view_but_not_manage_cases(): void
    {
        $case = $this->makeOpenCase();

        $this->actingAs($this->salesOnly)->get(route('crm.tech-support.index'))->assertOk();
        $this->actingAs($this->salesOnly)->get(route('crm.tech-support.show', $case))->assertOk();

        $this->actingAs($this->salesOnly)->patchJson(
            route('crm.tech-support.status', $case),
            ['status' => TechSupportCase::STATUS_IN_PROGRESS]
        )->assertForbidden();
    }

    public function test_a_follow_up_log_can_be_added_and_is_recorded_on_the_customer_timeline(): void
    {
        $case = $this->makeOpenCase();

        $response = $this->actingAs($this->tech)->postJson(
            route('crm.tech-support.follow-up', $case),
            ['note' => 'Checked the machine, replaced the part.']
        );

        $response->assertOk();
        $this->assertDatabaseHas('tech_support_case_logs', [
            'tech_support_case_id' => $case->id,
            'note'                 => 'Checked the machine, replaced the part.',
        ]);
        $this->assertDatabaseHas('customer_interactions', [
            'customer_id' => $case->customer_id,
            'subject'     => 'Follow-up Added',
        ]);
    }

    public function test_request_call_requires_a_note(): void
    {
        $case = $this->makeOpenCase();

        $this->actingAs($this->admin)->postJson(route('crm.tech-support.request-call', $case), [])
            ->assertStatus(422);

        $this->assertDatabaseMissing('call_requests', ['source_id' => $case->id]);
    }

    public function test_request_call_creates_a_call_request_and_notifies_the_assigned_technician(): void
    {
        $case = $this->makeOpenCase();
        $case->update(['assigned_to' => $this->tech->id]);

        $response = $this->actingAs($this->admin)->postJson(route('crm.tech-support.request-call', $case), [
            'note' => 'Customer needs to confirm the return address.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('call_requests', [
            'source_type' => TechSupportCase::class,
            'source_id'   => $case->id,
            'note'        => 'Customer needs to confirm the return address.',
        ]);
        $this->assertEquals(1, $this->tech->fresh()->notifications()->count());
    }

    public function test_tech_support_can_no_longer_complete_calls_themselves(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('crm.tech-support.complete-call'));
    }

    public function test_resolving_a_case_syncs_the_linked_ebay_record(): void
    {
        $customer = $this->makeCustomer(['name' => 'Kevin Wu', 'email' => 'kevin@example.com']);

        $ebayRecord = EbayCustomerRecord::create([
            'tab_type'    => EbayCustomerRecord::TAB_URGENT,
            'username'    => 'kevin_w',
            'email'       => 'kevin@example.com',
            'customer_id' => $customer->id,
        ]);

        $lead = Lead::create([
            'customer_id'  => $customer->id,
            'handled_by'   => $this->admin->id,
            'client_name'  => 'Kevin Wu',
            'client_email' => 'kevin@example.com',
            'source'       => InquirySource::WhatsApp->value,
            'status'       => WebsiteLeadStatus::NewLead->value,
            'received_at'  => now(),
        ]);
        $lead->update(['status' => WebsiteLeadStatus::TechnicalSupport]);

        $case = TechSupportCase::where('source_type', Lead::class)->firstOrFail();

        $this->actingAs($this->tech)->patchJson(
            route('crm.tech-support.status', $case),
            ['status' => TechSupportCase::STATUS_RESOLVED]
        )->assertOk();

        $this->assertTrue($ebayRecord->fresh()->tech_resolved);
        $this->assertEquals(EbayCustomerRecord::TAB_RESOLVED, $ebayRecord->fresh()->tab_type);
        $this->assertDatabaseHas('ebay_customer_status_history', [
            'ebay_customer_record_id' => $ebayRecord->id,
            'status'                  => EbayCustomerRecord::TAB_RESOLVED,
        ]);
        $this->assertNotNull($case->fresh()->ebay_synced_at);
        $this->assertDatabaseHas('customer_interactions', [
            'customer_id' => $customer->id,
            'subject'     => 'eBay Synchronization Completed',
        ]);
    }

    /**
     * Resolving a case doesn't revert Lead.status away from
     * TechnicalSupport (that would lose the "this lead once had a
     * technical issue" history) — tech_resolved is the real signal. Every
     * page showing the lead's status badge (Website CRM Leads list, the
     * Lead profile page) needs to read Lead::display_status_label instead
     * of the raw status, or it keeps showing "Technical Support" forever.
     */
    public function test_resolving_a_case_shows_resolved_status_on_the_website_crm_leads_pages(): void
    {
        $lead = Lead::create([
            'handled_by'  => $this->admin->id,
            'client_name' => 'Status Display Lead',
            'source'      => InquirySource::WhatsApp->value,
            'status'      => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);
        $lead->update(['status' => WebsiteLeadStatus::TechnicalSupport]);

        $case = TechSupportCase::where('source_type', Lead::class)->where('source_id', $lead->id)->firstOrFail();

        $this->actingAs($this->tech)->patchJson(
            route('crm.tech-support.status', $case),
            ['status' => TechSupportCase::STATUS_RESOLVED]
        )->assertOk();

        $lead->refresh();
        $this->assertTrue($lead->tech_resolved);
        $this->assertEquals(WebsiteLeadStatus::TechnicalSupport, $lead->status);
        $this->assertEquals('Resolved', $lead->display_status_label);

        // Note: "Technical Support" legitimately still appears elsewhere on
        // this page (the status filter dropdown lists every possible
        // status), so this only checks the resolved badge text is present.
        $indexResponse = $this->actingAs($this->admin)->get(route('crm.website.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Status Display Lead');
        $indexResponse->assertSee('Resolved');

        $showResponse = $this->actingAs($this->admin)->get(route('crm.website.show', $lead));
        $showResponse->assertOk();
        $showResponse->assertSee('Resolved');
    }

    /**
     * reopenCase() bumps the occurrence count and reopens the case, but
     * previously never reset the source's tech_resolved flag — so a
     * second, brand-new technical issue kept showing "Resolved" on the
     * Lead profile page (carried over from the *first* resolution) instead
     * of the real "Technical Support" status.
     */
    public function test_marking_a_lead_technical_support_a_second_time_clears_the_stale_resolved_badge(): void
    {
        $lead = Lead::create([
            'handled_by'  => $this->admin->id,
            'client_name' => 'Repeat Issue Lead',
            'source'      => InquirySource::WhatsApp->value,
            'status'      => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);

        // First occurrence, resolved.
        $lead->update(['status' => WebsiteLeadStatus::TechnicalSupport]);
        $case = TechSupportCase::where('source_type', Lead::class)->where('source_id', $lead->id)->firstOrFail();
        $this->actingAs($this->tech)->patchJson(
            route('crm.tech-support.status', $case),
            ['status' => TechSupportCase::STATUS_RESOLVED]
        )->assertOk();
        $this->assertTrue($lead->fresh()->tech_resolved);

        // Lead moves elsewhere, then re-enters Technical Support — a second, unresolved occurrence.
        $lead->update(['status' => WebsiteLeadStatus::Contacted]);
        $lead->update(['status' => WebsiteLeadStatus::TechnicalSupport]);

        $lead->refresh();
        $this->assertFalse($lead->tech_resolved);
        $this->assertEquals('Technical Support', $lead->display_status_label);
        $this->assertEquals(2, $case->fresh()->occurrence_count);
        $this->assertEquals(1, TechSupportCase::where('source_type', Lead::class)->where('source_id', $lead->id)->count());

        $showResponse = $this->actingAs($this->admin)->get(route('crm.website.show', $lead));
        $showResponse->assertOk();
        $showResponse->assertDontSee('Resolved');
    }

    private function makeOpenCase(): TechSupportCase
    {
        $customer = $this->makeCustomer(['name' => 'Maria Lopez']);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'handled_by'  => $this->admin->id,
            'client_name' => 'Maria Lopez',
            'source'      => InquirySource::WhatsApp->value,
            'status'      => WebsiteLeadStatus::NewLead->value,
            'received_at' => now(),
        ]);

        return TechSupportCase::create([
            'source_type' => Lead::class,
            'source_id'   => $lead->id,
            'customer_id' => $customer->id,
            'order_id'    => 'ORD-1',
            'status'      => TechSupportCase::STATUS_NEW,
        ]);
    }
}
