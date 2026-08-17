<?php

namespace Tests\Feature;

use App\Models\CallReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CallReportEditDeleteTest extends TestCase
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

    public function test_can_update_call_report(): void
    {
        $report = CallReport::create([
            'name' => 'Original Name',
            'phone' => '+1 (206) 578-6999',
            'email' => 'original@kiuq.com',
            'inquiry_type' => 'Technical',
            'answered_by' => $this->user->id,
            'occurred_at' => now(),
            'details' => 'Original details',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->put(route('crm.website.call-reports.update', $report), [
            'name' => 'Updated Name',
            'phone' => '+1 (206) 578-9999',
            'email' => 'updated@kiuq.com',
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => '2026-08-17',
            'occurred_at_time' => '14:30',
            'details' => 'Updated details note',
        ]);

        $response->assertRedirect(route('crm.website.call-reports.index'));
        $response->assertSessionHas('success', 'Call report updated.');

        $this->assertDatabaseHas('call_reports', [
            'id' => $report->id,
            'name' => 'Updated Name',
            'phone' => '+1 (206) 578-9999',
            'email' => 'updated@kiuq.com',
            'inquiry_type' => 'Inquiry',
            'details' => 'Updated details note',
        ]);
    }

    public function test_can_delete_call_report(): void
    {
        $report = CallReport::create([
            'name' => 'To Be Deleted',
            'phone' => '+1 (567) 862-7664',
            'email' => 'delete@kiuq.com',
            'inquiry_type' => 'Technical',
            'answered_by' => $this->user->id,
            'occurred_at' => now(),
            'details' => 'Temporary report',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('crm.website.call-reports.destroy', $report));

        $response->assertRedirect(route('crm.website.call-reports.index'));
        $response->assertSessionHas('success', 'Call report deleted.');

        $this->assertDatabaseMissing('call_reports', [
            'id' => $report->id,
        ]);
    }

    public function test_can_bulk_delete_call_reports(): void
    {
        $r1 = CallReport::create([
            'name' => 'Report 1',
            'phone' => '111',
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => now(),
            'created_by' => $this->user->id,
        ]);
        $r2 = CallReport::create([
            'name' => 'Report 2',
            'phone' => '222',
            'inquiry_type' => 'Technical',
            'answered_by' => $this->user->id,
            'occurred_at' => now(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('crm.website.call-reports.bulk-destroy'), [
            'report_ids' => [$r1->id, $r2->id],
        ]);

        $response->assertRedirect(route('crm.website.call-reports.index'));
        $response->assertSessionHas('success', '2 call report(s) deleted.');

        $this->assertDatabaseMissing('call_reports', ['id' => $r1->id]);
        $this->assertDatabaseMissing('call_reports', ['id' => $r2->id]);
    }
}
