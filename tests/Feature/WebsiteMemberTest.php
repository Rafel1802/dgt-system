<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebsiteMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'admin-digital']);
        Role::create(['name' => 'staff']);
    }

    public function test_admin_can_add_website_member_via_ajax()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin-digital');

        $user = User::factory()->create(['name' => 'John Doe']);

        $response = $this->actingAs($admin)
            ->postJson(route('websites.members.store'), [
                'user_ids' => [$user->id],
                'role' => 'Developer',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('website_members', [
            'user_id' => $user->id,
            'role' => 'Developer',
        ]);
    }

    public function test_admin_can_update_existing_member_role_via_ajax()
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $user = User::factory()->create(['name' => 'Jane QC']);
        WebsiteMember::create([
            'user_id' => $user->id,
            'role' => 'QC',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('websites.members.store'), [
                'user_ids' => [$user->id],
                'role' => 'Supervisor',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('website_members', [
            'user_id' => $user->id,
            'role' => 'Supervisor',
        ]);
    }

    public function test_admin_can_remove_website_member_via_ajax()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin-digital');

        $user = User::factory()->create();
        $member = WebsiteMember::create([
            'user_id' => $user->id,
            'role' => 'Developer',
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson(route('websites.members.destroy', $member->id));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('website_members', [
            'id' => $member->id,
        ]);
    }

    public function test_non_admin_cannot_modify_members()
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $user = User::factory()->create();

        $response = $this->actingAs($staff)
            ->postJson(route('websites.members.store'), [
                'user_ids' => [$user->id],
                'role' => 'Developer',
            ]);

        $response->assertStatus(403);
    }
}
