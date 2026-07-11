<?php

namespace Tests\Feature;

use App\Models\CallReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CallReportTest extends TestCase
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

    public function test_a_standalone_call_can_be_logged_without_an_existing_lead(): void
    {
        $response = $this->actingAs($this->user)->post(route('crm.website.call-reports.store'), [
            'name' => 'Unknown Caller',
            'phone' => '077-000-111',
            'inquiry_type' => 'Wrong dial',
            'answered_by' => $this->user->id,
            'occurred_at' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('crm.website.call-reports.index'));

        $this->assertDatabaseHas('call_reports', [
            'name' => 'Unknown Caller',
            'inquiry_type' => 'Wrong dial',
            'answered_by' => $this->user->id,
        ]);
    }

    public function test_phone_is_required_but_name_is_optional(): void
    {
        // Missing phone is rejected.
        $this->actingAs($this->user)->post(route('crm.website.call-reports.store'), [
            'name' => 'No Phone Caller',
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => now()->toDateString(),
        ])->assertSessionHasErrors('phone');

        // Phone-only (no name) succeeds.
        $this->actingAs($this->user)->post(route('crm.website.call-reports.store'), [
            'phone' => '077-999-888',
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => now()->toDateString(),
        ])->assertRedirect(route('crm.website.call-reports.index'));

        $this->assertDatabaseHas('call_reports', ['phone' => '077-999-888', 'name' => null]);
    }

    public function test_invalid_inquiry_type_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('crm.website.call-reports.store'), [
            'name' => 'Bad Type',
            'inquiry_type' => 'Not A Real Type',
            'answered_by' => $this->user->id,
            'occurred_at' => now()->toDateString(),
        ])->assertSessionHasErrors('inquiry_type');

        $this->assertDatabaseMissing('call_reports', ['name' => 'Bad Type']);
    }

    public function test_call_reports_appear_on_the_dedicated_call_reports_page(): void
    {
        CallReport::create([
            'name' => 'Alice Chen',
            'phone' => '077-111-222',
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('crm.website.call-reports.index'))
            ->assertOk()
            ->assertSee('Alice Chen');
    }

    public function test_call_reports_no_longer_appear_on_the_leads_index(): void
    {
        CallReport::create([
            'name' => 'Should Not Be Here',
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('crm.website.index'))
            ->assertOk()
            ->assertDontSee('Should Not Be Here');
    }

    public function test_a_call_can_be_logged_with_details_and_the_details_are_optional(): void
    {
        $response = $this->actingAs($this->user)->post(route('crm.website.call-reports.store'), [
            'name' => 'Detailed Caller',
            'phone' => '077-000-222',
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => now()->toDateString(),
            'details' => 'Asked about delivery timeline for order #123.',
        ]);

        $response->assertRedirect(route('crm.website.call-reports.index'));
        $this->assertDatabaseHas('call_reports', [
            'name' => 'Detailed Caller',
            'details' => 'Asked about delivery timeline for order #123.',
        ]);

        // Logging without details still succeeds (nullable).
        $this->actingAs($this->user)->post(route('crm.website.call-reports.store'), [
            'name' => 'No Details Caller',
            'phone' => '077-000-333',
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => now()->toDateString(),
        ])->assertRedirect(route('crm.website.call-reports.index'));

        $this->assertDatabaseHas('call_reports', ['name' => 'No Details Caller', 'details' => null]);
    }

    public function test_call_report_details_are_shown_on_the_index_page(): void
    {
        CallReport::create([
            'name' => 'Note Visible Caller',
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => now(),
            'details' => 'Wants a callback tomorrow morning.',
        ]);

        $this->actingAs($this->user)
            ->get(route('crm.website.call-reports.index'))
            ->assertOk()
            ->assertSee('Wants a callback tomorrow morning.');
    }
}
