<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['url']->forceRootUrl('http://localhost');

        // Run roles and permissions seeder
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        $this->nonAdmin = User::factory()->create(['is_active' => true]);
    }

    public function test_settings_page_access_restricted_to_admins(): void
    {
        // Non-admin gets forbidden
        $this->actingAs($this->nonAdmin)
            ->get(route('admin.settings.index', [], false))
            ->assertStatus(403);

        // Admin gets access
        $this->actingAs($this->admin)
            ->get(route('admin.settings.index', [], false))
            ->assertStatus(200);
    }

    public function test_admin_can_save_static_and_custom_tools_with_ordering(): void
    {
        // 1. Prepare data
        $postData = [
            // Static tools overrides
            'hosting_image_url' => 'https://image.my-custom-host.com',
            'hosting_image_url_label' => 'Super Host',
            'hosting_image_url_icon' => 'https://image.my-custom-host.com/icon.png',

            // Custom tools in workspace group
            'custom_workspace_tools' => json_encode([
                [
                    'custom_id' => 'custom_link_1',
                    'label' => 'Custom Slack',
                    'url' => 'https://slack.com',
                    'icon_url' => 'https://slack.com/favicon.ico'
                ],
                [
                    'custom_id' => 'custom_link_2',
                    'label' => 'Empty Link', // Should be skipped/filtered because URL is empty
                    'url' => '',
                    'icon_url' => ''
                ]
            ]),

            // Orders
            'board_tools_order' => json_encode(['weekly_report_url', 'backup_server_url', 'hosting_image_url']),
            'workspace_tools_order' => json_encode(['google_docs_url', 'custom_link_1', 'google_drive_url']),
        ];

        // 2. Submit request
        $response = $this->actingAs($this->admin)
            ->post(route('admin.settings.store', [], false), $postData);

        $response->assertRedirect(route('admin.settings.index', [], false));

        // 3. Assert database has static overrides
        $this->assertSame('https://image.my-custom-host.com', Setting::get('hosting_image_url'));
        $this->assertSame('Super Host', Setting::get('hosting_image_url_label'));
        $this->assertSame('https://image.my-custom-host.com/icon.png', Setting::get('hosting_image_url_icon'));

        // 4. Assert workspace custom tools JSON was saved correctly, filtering out empty url tool
        $workspaceCustom = json_decode(Setting::get('custom_workspace_tools'), true);
        $this->assertCount(1, $workspaceCustom);
        $this->assertSame('custom_link_1', $workspaceCustom[0]['custom_id']);
        $this->assertSame('Custom Slack', $workspaceCustom[0]['label']);
        $this->assertSame('https://slack.com', $workspaceCustom[0]['url']);

        // 5. Assert sorting works using externalToolsForGroup
        // Google Drive, Google Docs, Google Sheets, Google Translate, WhatsApp are in workspace group
        // We ordered them: google_docs_url, custom_link_1, google_drive_url
        $workspaceTools = Setting::externalToolsForGroup('workspace');

        // Let's assert the order of the first three tools matches our custom order
        $this->assertSame('google_docs_url', $workspaceTools[0]['key'] ?? null);
        $this->assertSame('custom_link_1', $workspaceTools[1]['custom_id'] ?? null);
        $this->assertSame('google_drive_url', $workspaceTools[2]['key'] ?? null);
    }
}
