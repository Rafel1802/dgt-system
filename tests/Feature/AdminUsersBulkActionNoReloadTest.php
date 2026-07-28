<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdminUsersBulkActionNoReloadTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $targetUser;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        $this->targetUser = User::factory()->create(['is_active' => true, 'can_edit_profile' => true]);
    }

    public function test_users_index_renders_row_and_security_badge_ids_used_for_local_updates(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('id="user-row-'.$this->targetUser->id.'"', false);
        $response->assertSee('id="status-dot-'.$this->targetUser->id.'"', false);
        $response->assertSee('id="security-badge-'.$this->targetUser->id.'"', false);

        // The bulk-action handler must no longer force a full page reload.
        $response->assertDontSee('window.location.reload()', false);
    }

    public function test_bulk_freeze_action_still_updates_the_user_server_side(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.users.bulk-action'), [
            'action' => 'freeze',
            'users' => [$this->targetUser->id],
        ]);

        $response->assertOk();
        $this->assertFalse($this->targetUser->fresh()->is_active);
    }
}
