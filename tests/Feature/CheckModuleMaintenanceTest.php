<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckModuleMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $daraUser;
    protected User $regularUser;
    protected User $otherAdminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin-digital']);
        Role::firstOrCreate(['name' => 'digital-team']);

        // 1. User "dara"
        $this->daraUser = User::factory()->create([
            'name'     => 'Mr. Dara Dhevil',
            'username' => 'dara',
            'email'    => 'dara@kiuq.com',
            'is_active' => true,
        ]);
        $this->daraUser->assignRole('super-admin');

        // 2. Regular staff user
        $this->regularUser = User::factory()->create([
            'name'     => 'Regular Staff',
            'username' => 'staff123',
            'email'    => 'staff123@kiuq.com',
            'is_active' => true,
        ]);
        $this->regularUser->assignRole('digital-team');

        // 3. Other admin user who is NOT Dara
        $this->otherAdminUser = User::factory()->create([
            'name'     => 'Other Administrator',
            'username' => 'otheradmin',
            'email'    => 'otheradmin@kiuq.com',
            'is_active' => true,
        ]);
        $this->otherAdminUser->assignRole('super-admin');
    }

    public function test_regular_user_and_other_admin_see_maintenance_when_google_search_console_is_locked(): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode(['google_search_console'])]
        );

        // Regular user should see 503
        $response = $this->actingAs($this->regularUser)->get(route('websites.search-console.index'));
        $response->assertStatus(503);
        $response->assertSee('Google Search Console');

        // Other admin should ALSO see 503
        $responseAdmin = $this->actingAs($this->otherAdminUser)->get(route('websites.search-console.index'));
        $responseAdmin->assertStatus(503);

        // API request should receive JSON 503
        $responseJson = $this->actingAs($this->regularUser)->getJson(route('websites.search-console.properties'));
        $responseJson->assertStatus(503);
        $responseJson->assertJson([
            'status' => 'maintenance',
            'maintenance' => true,
        ]);
    }

    public function test_dara_is_strictly_exempt_and_sees_google_search_console_normally(): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode(['google_search_console', 'all_websites'])]
        );

        // Dara should NOT be blocked by 503
        $response = $this->actingAs($this->daraUser)->get(route('websites.search-console.index'));
        $this->assertNotEquals(503, $response->getStatusCode());
    }

    public function test_tasks_count_maintenance_blocks_others_but_allows_dara(): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode(['tasks_count'])]
        );

        // Regular user gets 503
        $response = $this->actingAs($this->regularUser)->get(route('tasks.count'));
        $response->assertStatus(503);
        $response->assertSee('Tasks Count');

        // Dara bypasses and sees normally
        $responseDara = $this->actingAs($this->daraUser)->get(route('tasks.count'));
        $this->assertNotEquals(503, $responseDara->getStatusCode());
    }

    public function test_dashboard_maintenance_blocks_others_but_allows_dara(): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode(['dashboard'])]
        );

        // Regular user gets 503
        $response = $this->actingAs($this->regularUser)->get(route('dashboard'));
        $response->assertStatus(503);

        // Dara bypasses and sees normally
        $responseDara = $this->actingAs($this->daraUser)->get(route('dashboard'));
        $this->assertNotEquals(503, $responseDara->getStatusCode());
    }

    public function test_members_maintenance_blocks_others_but_allows_dara(): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode(['members'])]
        );

        // Regular user gets 503
        $response = $this->actingAs($this->regularUser)->get(route('members.index'));
        $response->assertStatus(503);

        // Dara bypasses and sees normally
        $responseDara = $this->actingAs($this->daraUser)->get(route('members.index'));
        $this->assertNotEquals(503, $responseDara->getStatusCode());
    }

    public function test_personal_report_maintenance_blocks_others_but_allows_dara(): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode(['personal_report'])]
        );

        // Other admin gets 503
        $response = $this->actingAs($this->otherAdminUser)->get(route('boards.reports.personal'));
        $response->assertStatus(503);

        // Dara bypasses and sees normally
        $responseDara = $this->actingAs($this->daraUser)->get(route('boards.reports.personal'));
        $this->assertNotEquals(503, $responseDara->getStatusCode());
    }

    public function test_kpi_maintenance_blocks_others_but_allows_dara(): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode(['kpi'])]
        );

        // Other admin gets 503
        $response = $this->actingAs($this->otherAdminUser)->get(route('kpi.index'));
        $response->assertStatus(503);
        $response->assertSee('Staff KPI');

        // Dara bypasses and sees normally
        $responseDara = $this->actingAs($this->daraUser)->get(route('kpi.index'));
        $this->assertNotEquals(503, $responseDara->getStatusCode());
    }

    public function test_notes_maintenance_blocks_others_but_allows_dara(): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode(['notes'])]
        );

        $response = $this->actingAs($this->regularUser)->get(route('notes.team'));
        $response->assertStatus(503);

        $responseDara = $this->actingAs($this->daraUser)->get(route('notes.team'));
        $this->assertNotEquals(503, $responseDara->getStatusCode());
    }

    public function test_social_media_planning_blocks_smm_boards_specifically(): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode(['social_media_planning'])]
        );

        $response = $this->actingAs($this->otherAdminUser)->get(route('smm-boards.index'));
        $response->assertStatus(503);
        $response->assertSee('SMM Planning Board');

        $responseDara = $this->actingAs($this->daraUser)->get(route('smm-boards.index'));
        $this->assertNotEquals(503, $responseDara->getStatusCode());
    }

    public function test_dara_can_always_access_maintenance_controller(): void
    {
        $response = $this->actingAs($this->daraUser)->get(route('admin.maintenance.index'));
        $response->assertStatus(200);
        $response->assertSee('Maintenance System');
        $response->assertSee('Google Search Console');
        $response->assertSee('Tasks Count Panel');
    }

    public function test_when_no_maintenance_is_active_all_users_see_normally(): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode([])]
        );

        $response = $this->actingAs($this->regularUser)->get(route('tasks.count'));
        $this->assertNotEquals(503, $response->getStatusCode());
    }

    public function test_maintenance_page_displays_selected_title_and_kiuq_copyright(): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode(['tasks_count'])]
        );
        Setting::updateOrCreate(['key' => 'maintenance_template_style'], ['value' => 'custom']);
        Setting::updateOrCreate(['key' => 'maintenance_title_preset'], ['value' => 'Under Integrate']);
        Setting::updateOrCreate(['key' => 'maintenance_title'], ['value' => 'Under Integrate']);

        $response = $this->actingAs($this->regularUser)->get(route('tasks.count'));
        $response->assertStatus(503);
        $response->assertSee('Under');
        $response->assertSee('Integrate');
        $response->assertSee('KiuQ.com');
        $response->assertSee('All rights reserved');
    }

    public function test_admin_can_save_custom_typed_title_and_see_it_on_maintenance_page(): void
    {
        $response = $this->actingAs($this->daraUser)->post(route('admin.maintenance.store'), [
            'template_style' => 'custom',
            'title_preset'   => 'other',
            'title_custom'   => 'Under Integration With Google Search Console',
            'site_name'      => 'KiuQ.com',
            'email'          => 'digital@typhonmachinery.com',
            'modules'        => ['google_search_console' => '1'],
        ]);

        $response->assertRedirect(route('admin.maintenance.index'));

        $this->assertEquals('other', Setting::where('key', 'maintenance_title_preset')->value('value'));
        $this->assertEquals('Under Integration With Google Search Console', Setting::where('key', 'maintenance_title_custom')->value('value'));
        $this->assertEquals('Under Integration With Google Search Console', Setting::where('key', 'maintenance_title')->value('value'));

        $regularResponse = $this->actingAs($this->regularUser)->get(route('websites.search-console.index'));
        $regularResponse->assertStatus(503);
        $regularResponse->assertSee('Under');
        $regularResponse->assertSee('Integration With Google Search Console');
        $regularResponse->assertSee('KiuQ.com');
        $regularResponse->assertSee('All rights reserved');
    }
}
