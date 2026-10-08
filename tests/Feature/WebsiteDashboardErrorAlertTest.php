<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WebsiteDashboardErrorAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    public function test_sidebar_shows_red_circle_error_count_when_website_errors_exist()
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin-digital');

        Website::create([
            'name' => 'Error Site 1',
            'url' => 'https://error1.com',
            'status' => Website::STATUS_QC_ERROR,
            'error_note' => 'Header logo is blurry and missing links',
        ]);

        Website::create([
            'name' => 'Error Site 2',
            'url' => 'https://error2.com',
            'status' => Website::STATUS_SUPERVISOR_ERROR,
            'error_note' => 'Color scheme does not match Figma',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        // Submenu Website Status shows red circle badge with count 2
        $response->assertSee('Website Status');
        $response->assertSee('website error(s) flagged');
    }

    public function test_dashboard_displays_website_errors_alert_with_names_and_history_links()
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin-digital');

        $web1 = Website::create([
            'name' => 'minexcavator.com',
            'url' => 'https://minexcavator.com',
            'status' => Website::STATUS_QC_ERROR,
            'error_note' => 'Everything done except blog post section',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Website Errors Requiring Action');
        $response->assertSee('minexcavator.com');
        $response->assertSee('Everything done except blog post section');
        $response->assertSee('QC Error');
        $response->assertSee('View Error');
        $response->assertSee('open_history=' . $web1->id);
    }
}
