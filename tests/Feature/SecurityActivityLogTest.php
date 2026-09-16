<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SecurityActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    public function test_humanize_description_removes_dots_and_uses_proper_english(): void
    {
        $log1 = new ActivityLog(['description' => 'Created or submitted boards.cards.bulkAction']);
        $this->assertSame('Updated board cards in bulk', $log1->description);
        $this->assertFalse(str_contains($log1->description, '.'));

        $log2 = new ActivityLog(['description' => 'Deleted boards.cards.destroy']);
        $this->assertSame('Deleted a board card', $log2->description);
        $this->assertFalse(str_contains($log2->description, '.'));

        $log3 = new ActivityLog(['description' => 'Created or submitted boards.cards.labels']);
        $this->assertSame('Updated board card labels', $log3->description);
        $this->assertFalse(str_contains($log3->description, '.'));

        $log4 = new ActivityLog(['description' => 'Created or submitted websites.followups.qc']);
        $this->assertSame('Updated website follow-up QC', $log4->description);
        $this->assertFalse(str_contains($log4->description, '.'));

        $log5 = new ActivityLog(['description' => 'User logged in']);
        $this->assertSame('User logged in', $log5->description);

        $log6 = new ActivityLog(['description' => 'Created or submitted custom_module.sub_feature.action_name']);
        $this->assertFalse(str_contains($log6->description, '.'));
    }

    public function test_security_activity_log_search_filters_results(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $user1 = User::factory()->create(['name' => 'Alice Wonderland', 'is_active' => true]);
        $user2 = User::factory()->create(['name' => 'Bob Builder', 'is_active' => true]);

        ActivityLog::create([
            'user_id' => $user1->id,
            'action' => 'user.action',
            'module' => 'auth',
            'description' => 'User logged in',
            'ip_address' => '192.168.1.100',
            'created_at' => now(),
        ]);

        ActivityLog::create([
            'user_id' => $user2->id,
            'action' => 'user.action',
            'module' => 'auth',
            'description' => 'Password reset requested',
            'ip_address' => '10.0.0.50',
            'created_at' => now(),
        ]);

        // Search by user name Alice
        $response = $this->actingAs($admin)->get(route('admin.security.index', ['activity_q' => 'Alice']));
        $response->assertOk();
        $logs = $response->viewData('activityLogs');
        $this->assertSame(1, $logs->total());
        $this->assertSame($user1->id, $logs->first()->user_id);

        // Search by IP address 10.0.0.50
        $response2 = $this->actingAs($admin)->get(route('admin.security.index', ['activity_q' => '10.0.0.50']));
        $response2->assertOk();
        $logs2 = $response2->viewData('activityLogs');
        $this->assertSame(1, $logs2->total());
        $this->assertSame($user2->id, $logs2->first()->user_id);
    }
}
