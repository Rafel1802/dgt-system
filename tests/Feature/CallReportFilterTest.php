<?php

namespace Tests\Feature;

use App\Models\CallReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CallReportFilterTest extends TestCase
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

    protected function makeCallReport(string $name, string $occurredAt): CallReport
    {
        return CallReport::create([
            'name' => $name,
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => $occurredAt,
        ]);
    }

    public function test_date_range_narrows_results(): void
    {
        $this->makeCallReport('Old Caller', '2026-01-01');
        $this->makeCallReport('In Range Caller', '2026-07-05');
        $this->makeCallReport('Future Caller', '2026-12-31');

        $response = $this->actingAs($this->user)->get(route('crm.website.call-reports.index', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-10',
        ]));

        $response->assertOk();
        $response->assertSee('In Range Caller');
        $response->assertDontSee('Old Caller');
        $response->assertDontSee('Future Caller');
    }

    public function test_date_filter_combines_with_search(): void
    {
        $this->makeCallReport('Alice In Range', '2026-07-05');
        CallReport::create([
            'name' => 'Bob In Range',
            'inquiry_type' => 'Inquiry',
            'answered_by' => $this->user->id,
            'occurred_at' => '2026-07-05',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.call-reports.index', [
            'search' => 'Alice',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-10',
        ]));

        $response->assertOk();
        $response->assertSee('Alice In Range');
        $response->assertDontSee('Bob In Range');
    }

    public function test_no_date_filter_shows_all_reports(): void
    {
        $this->makeCallReport('Old Caller', '2026-01-01');
        $this->makeCallReport('New Caller', '2026-07-05');

        $response = $this->actingAs($this->user)->get(route('crm.website.call-reports.index'));

        $response->assertOk();
        $response->assertSee('Old Caller');
        $response->assertSee('New Caller');
    }
}
