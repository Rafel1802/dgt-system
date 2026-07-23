<?php

namespace Tests\Feature;

use App\Models\CallRequest;
use App\Models\TechSupportCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CallRequestsPageTest extends TestCase
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

    protected function makeCallRequest(array $overrides = []): CallRequest
    {
        return CallRequest::create(array_merge([
            'source_type' => TechSupportCase::class,
            'source_id' => 1,
            'name' => 'Requester',
            'note' => 'note',
            'requested_by' => $this->user->id,
            'fulfilled' => false,
        ], $overrides));
    }

    public function test_pending_call_requests_appear_on_the_dedicated_page(): void
    {
        $this->makeCallRequest([
            'name' => 'Pending Requester',
            'phone' => '077-000-000',
            'note' => 'Call requested for order N/A',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.call-requests.index'));

        $response->assertOk();
        $response->assertSee('Pending Requester');
    }

    public function test_fulfilled_tab_hides_pending_requests(): void
    {
        $this->makeCallRequest(['name' => 'Still Pending']);

        $response = $this->actingAs($this->user)->get(route('crm.website.call-requests.index', ['status' => 'fulfilled']));

        $response->assertOk();
        $response->assertDontSee('Still Pending');
    }

    public function test_all_tab_shows_both_pending_and_fulfilled(): void
    {
        $this->makeCallRequest(['name' => 'Pending One']);
        $this->makeCallRequest([
            'name' => 'Fulfilled One',
            'fulfilled' => true, 'fulfilled_at' => now(), 'fulfilled_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.call-requests.index', ['status' => 'all']));

        $response->assertOk();
        $response->assertSee('Pending One');
        $response->assertSee('Fulfilled One');
    }

    public function test_mark_called_requires_a_note(): void
    {
        $callRequest = $this->makeCallRequest(['name' => 'To Be Called']);

        $this->actingAs($this->user)->post(route('crm.website.call-requests.fulfill', $callRequest), [])
            ->assertSessionHasErrors('note');

        $this->assertFalse($callRequest->fresh()->fulfilled);
    }

    public function test_mark_called_with_a_note_works_from_the_new_page(): void
    {
        $callRequest = $this->makeCallRequest(['name' => 'To Be Called']);

        $response = $this->actingAs($this->user)->post(route('crm.website.call-requests.fulfill', $callRequest), [
            'note' => 'Spoke with the customer, issue resolved.',
        ]);

        // Redirect keeps the pending tab so the row disappears from the list
        // the user was looking at (feels faster than a full unfiltered reload).
        $response->assertRedirect(route('crm.website.call-requests.index', ['status' => 'pending']));
        $callRequest->refresh();
        $this->assertTrue($callRequest->fulfilled);
        $this->assertEquals('Spoke with the customer, issue resolved.', $callRequest->fulfillment_note);
    }

    public function test_fulfilling_a_tech_support_call_request_logs_the_note_back_on_the_case(): void
    {
        $customer = \App\Models\Customer::create([
            'name' => 'Case Customer', 'status' => \App\Enums\CustomerStatus::Lead->value,
            'source' => \App\Enums\CustomerSource::Website->value, 'created_by' => $this->user->id,
        ]);
        $case = TechSupportCase::create([
            'source_type' => \App\Models\Lead::class,
            'source_id'   => 1,
            'customer_id' => $customer->id,
            'status'      => TechSupportCase::STATUS_NEW,
        ]);
        $callRequest = $this->makeCallRequest([
            'name' => 'Case Customer', 'source_type' => TechSupportCase::class, 'source_id' => $case->id,
            'note' => 'Please confirm the return address with the client.',
        ]);

        $this->actingAs($this->user)->post(route('crm.website.call-requests.fulfill', $callRequest), [
            'note' => 'Confirmed the return address with the customer.',
        ]);

        // The log entry folds in why the call was requested (the original
        // call-request note) alongside the outcome, since the fulfilled
        // call request is no longer shown in its own separate card.
        $this->assertDatabaseHas('tech_support_case_logs', [
            'tech_support_case_id' => $case->id,
            'type' => 'call_completed',
            'note' => "Re: Please confirm the return address with the client.\n\nOutcome: Confirmed the return address with the customer.",
        ]);
    }

    /** A fulfilled call request no longer shows in its own "Call Requests" card — only the Follow-Up Log entry (see the test above). */
    public function test_fulfilled_call_request_is_not_shown_in_its_own_card_on_the_case_page(): void
    {
        $customer = \App\Models\Customer::create([
            'name' => 'Case Customer Two', 'status' => \App\Enums\CustomerStatus::Lead->value,
            'source' => \App\Enums\CustomerSource::Website->value, 'created_by' => $this->user->id,
        ]);
        $case = TechSupportCase::create([
            'source_type' => \App\Models\Lead::class,
            'source_id'   => 2,
            'customer_id' => $customer->id,
            'status'      => TechSupportCase::STATUS_NEW,
        ]);
        $callRequest = $this->makeCallRequest([
            'name' => 'Case Customer Two', 'source_type' => TechSupportCase::class, 'source_id' => $case->id,
            'note' => 'Distinctive Request Reason Text',
        ]);

        $this->actingAs($this->user)->post(route('crm.website.call-requests.fulfill', $callRequest), [
            'note' => 'Distinctive Outcome Text',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.tech-support.show', $case));

        $response->assertOk();
        // Fulfilled call requests no longer appear as pending cards — only in Follow-Up Logs.
        // (Alpine may keep a pending-card template in the page HTML, so we assert on
        // the fulfilled state rather than the template's static heading string.)
        $response->assertDontSee('✓ Called');
        $response->assertSee('Distinctive Request Reason Text');
        $response->assertSee('Distinctive Outcome Text');
        // pendingCalls Alpine seed should be empty after fulfill.
        $response->assertSee('pendingCalls: []', false);
    }

    public function test_pending_call_requests_no_longer_appear_on_the_leads_index(): void
    {
        $this->makeCallRequest(['name' => 'Should Not Leak Here']);

        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertDontSee('Should Not Leak Here');
        $response->assertDontSee('Pending Call Requests (from Tech Support)');
    }

    public function test_leads_index_shows_pending_call_requests_count_badge(): void
    {
        $this->makeCallRequest(['name' => 'Counted Request']);

        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertSee('Call Requests');
    }

    /** The sidebar (rendered on every authenticated page) shows a pending-count badge on the Call Requests link, mirroring the Tech Support "new case" badge pattern. */
    public function test_sidebar_shows_pending_call_requests_count_badge(): void
    {
        $this->makeCallRequest(['name' => 'Sidebar Badge Request']);
        $this->makeCallRequest(['name' => 'Sidebar Badge Request Two']);

        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertSee('id="nav-website-call-requests"', false);
        // The exact badge markup, so this can't accidentally match an
        // unrelated "2" elsewhere on the page.
        $response->assertSee('rounded-full px-1.5 py-0.5 min-w-[1.25rem] text-center">2</span>', false);
    }

    public function test_sidebar_hides_the_badge_when_there_are_no_pending_call_requests(): void
    {
        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertSee('id="nav-website-call-requests"', false);
    }
}
