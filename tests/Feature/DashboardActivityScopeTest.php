<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DashboardActivityScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function makeLog(User $user, string $module = 'crm'): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'test.action',
            'module' => $module,
            'description' => 'Test activity',
            'created_at' => now(),
        ]);
    }

    public function test_a_tech_support_user_only_sees_their_own_activity(): void
    {
        $user = $this->makeUser('tech-support');
        $other = $this->makeUser('sales-crm');

        $mine = $this->makeLog($user);
        $theirs = $this->makeLog($other);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $recent = $response->viewData('stats')['recent_activities'];

        $this->assertTrue($recent->contains('id', $mine->id));
        $this->assertFalse($recent->contains('id', $theirs->id));
    }

    public function test_a_super_admin_sees_everyones_activity(): void
    {
        $admin = $this->makeUser('super-admin');
        $other = $this->makeUser('sales-crm');

        $mine = $this->makeLog($admin);
        $theirs = $this->makeLog($other);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $recent = $response->viewData('stats')['recent_activities'];

        $this->assertTrue($recent->contains('id', $mine->id));
        $this->assertTrue($recent->contains('id', $theirs->id));
    }

    public function test_a_crm_supervisor_sees_everyones_activity(): void
    {
        $supervisor = $this->makeUser('sales-crm');
        $supervisor->update(['crm_role' => 'supervisor']);
        $other = $this->makeUser('ebay-team');

        $mine = $this->makeLog($supervisor);
        $theirs = $this->makeLog($other);

        $response = $this->actingAs($supervisor)->get(route('dashboard'));

        $response->assertOk();
        $recent = $response->viewData('stats')['recent_activities'];

        $this->assertTrue($recent->contains('id', $mine->id));
        $this->assertTrue($recent->contains('id', $theirs->id));
    }
}
