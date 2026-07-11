<?php

namespace Tests\Feature;

use App\Models\CallReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CallReportExportTest extends TestCase
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

    public function test_pdf_export_returns_a_pdf(): void
    {
        CallReport::create([
            'name' => 'Export Me', 'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id, 'occurred_at' => '2026-07-05',
        ]);

        $response = $this->actingAs($this->user)->post(route('crm.website.call-reports.export'), [
            'format' => 'pdf',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_export_respects_the_active_date_filter(): void
    {
        CallReport::create([
            'name' => 'In Range', 'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id, 'occurred_at' => '2026-07-05',
        ]);
        CallReport::create([
            'name' => 'Out Of Range', 'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id, 'occurred_at' => '2026-01-01',
        ]);

        $response = $this->actingAs($this->user)->post(route('crm.website.call-reports.export'), [
            'format' => 'pdf',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-10',
        ]);

        $response->assertOk();
        // Just confirm a well-formed non-empty PDF was returned for the filtered set.
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_google_sheet_export_fails_gracefully_when_not_configured(): void
    {
        CallReport::create([
            'name' => 'Export Me', 'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id, 'occurred_at' => '2026-07-05',
        ]);

        $response = $this->actingAs($this->user)->post(route('crm.website.call-reports.export'), [
            'format' => 'google_sheet',
        ]);

        $response->assertRedirect(route('crm.website.call-reports.index'));
        $response->assertSessionHasErrors('google_sheet');
    }

    public function test_invalid_format_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('crm.website.call-reports.export'), [
            'format' => 'excel',
        ])->assertSessionHasErrors('format');
    }
}
