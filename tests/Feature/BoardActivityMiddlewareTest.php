<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BoardActivityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    public function test_visiting_boards_or_viewing_cards_does_not_log_activity(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        // Visit boards workspace index
        $response = $this->actingAs($admin)->get(route('boards.workspaces'));
        $response->assertOk();

        // Check that no board visit activity log was created
        $boardVisitLogs = ActivityLog::where('description', 'like', 'Visited boards%')->count();
        $this->assertSame(0, $boardVisitLogs);
    }
}
