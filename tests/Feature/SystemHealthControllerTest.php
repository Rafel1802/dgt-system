<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SystemHealthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['url']->forceRootUrl('http://localhost');

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        $this->regularUser = User::factory()->create(['is_active' => true]);
    }

    public function test_access_is_forbidden_for_regular_users(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('system.health.index'))
            ->assertStatus(403);

        $this->actingAs($this->regularUser)
            ->post(route('system.health.optimize'))
            ->assertStatus(403);

        $this->actingAs($this->regularUser)
            ->post(route('system.health.repair'))
            ->assertStatus(403);
    }

    public function test_admin_can_view_system_health_dashboard(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('system.health.index'));

        $response->assertStatus(200);
        $response->assertSee('System Health &amp; Maintenance', false);
        $response->assertSee('Clear Cache & Optimize', false);
        $response->assertSee('Run Auto-Repair', false);
        $response->assertSee('Re-scan', false);
        $response->assertSee('Copy for AI Assistant', false);
    }

    public function test_admin_can_optimize_speed_via_json(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('system.health.optimize'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'actions_taken',
            'duration',
            'performance' => [
                'config_cached',
                'routes_cached',
                'compiled_views_count',
                'log_size',
            ],
        ]);
        $this->assertTrue($response->json('success'));
    }

    public function test_admin_can_optimize_speed_via_redirect(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('system.health.optimize'));

        $response->assertStatus(303);
        $response->assertRedirect(route('system.health.index'));
        $response->assertSessionHas('optimize_success');
        $response->assertSessionHas('optimize_actions');
    }

    public function test_admin_can_run_auto_repair_via_json(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('system.health.repair'), [
                'scope' => 'social_media',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'scope' => 'social_media',
        ]);
        $response->assertJsonStructure([
            'success',
            'scope',
            'message',
            'actions_taken',
        ]);
    }

    public function test_admin_can_run_auto_repair_via_redirect(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('system.health.repair'), [
                'scope' => 'all',
            ]);

        $response->assertStatus(303);
        $response->assertRedirect(route('system.health.index'));
        $response->assertSessionHas('repair_success');
        $response->assertSessionHas('repair_actions');
    }

    public function test_admin_can_copy_ai_report_json(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('system.health.copy-report'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertNotEmpty($response->json('markdown'));
        $this->assertStringContainsString('System Health & Maintenance Diagnostics Report', $response->json('markdown'));
    }

    public function test_admin_can_clear_logs_via_json(): void
    {
        $logPath = storage_path('logs/laravel.log');
        File::put($logPath, 'Sample test log content');

        $response = $this->actingAs($this->admin)
            ->postJson(route('system.health.clear-logs'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'System log file cleared successfully.',
        ]);

        $this->assertSame('', File::get($logPath));
    }
}
