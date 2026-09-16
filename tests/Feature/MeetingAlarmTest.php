<?php

namespace Tests\Feature;

use App\Models\MeetingAlarm;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MeetingAlarmTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $supervisor;
    protected User $qc;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['url']->forceRootUrl('http://localhost');

        Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class]);

        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole('super-admin');

        $this->supervisor = User::factory()->create([
            'is_active' => true,
            'team_role' => 'Team Supervisor',
        ]);

        $this->qc = User::factory()->create([
            'is_active' => true,
            'team_role' => 'QC Specialist',
        ]);

        $this->regularUser = User::factory()->create([
            'is_active' => true,
            'team_role' => 'Digital Staff',
        ]);
    }

    public function test_superadmin_and_supervisor_can_access_meeting_alarms_index(): void
    {
        $responseAdmin = $this->actingAs($this->superAdmin)
            ->get(route('admin.meeting-alarms.index'));
        $responseAdmin->assertStatus(200);

        $responseSupervisor = $this->actingAs($this->supervisor)
            ->get(route('admin.meeting-alarms.index'));
        $responseSupervisor->assertStatus(200);

        $responseQc = $this->actingAs($this->qc)
            ->get(route('admin.meeting-alarms.index'));
        $responseQc->assertStatus(200);
    }

    public function test_regular_user_cannot_access_meeting_alarms(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.meeting-alarms.index'));
        $response->assertStatus(403);
    }

    public function test_supervisor_and_qc_can_create_and_manage_meeting_alarms(): void
    {
        $this->actingAs($this->supervisor);

        $nowPP = Carbon::now('Asia/Phnom_Penh')->addHour();

        $response = $this->post(route('admin.meeting-alarms.store'), [
            'title' => 'Weekly QC & Team Sync',
            'description' => 'Review of sprint items and quality checks',
            'meeting_time' => $nowPP->format('Y-m-d H:i:s'),
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
            'sound' => 'alarm1.mp3',
            'ring_duration' => 5,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.meeting-alarms.index'));
        $this->assertDatabaseHas('meeting_alarms', [
            'title' => 'Weekly QC & Team Sync',
            'sound' => 'alarm1.mp3',
            'created_by' => $this->supervisor->id,
            'is_active' => true,
        ]);

        $meeting = MeetingAlarm::where('title', 'Weekly QC & Team Sync')->first();
        $this->assertNotNull($meeting);
        $this->assertEquals('alarm1.mp3', $meeting->sound);
        $this->assertStringContainsString('alarm1.mp3', $meeting->sound_url);

        // Test toggle active
        $toggleRes = $this->patch(route('admin.meeting-alarms.toggle-active', $meeting));
        $toggleRes->assertStatus(200);
        $this->assertFalse($meeting->fresh()->is_active);

        // Test API upcoming excludes inactive
        $apiRes = $this->get(route('meeting-alarms.upcoming'));
        $apiRes->assertStatus(200);
        $alarmIds = collect($apiRes->json('alarms'))->pluck('id')->toArray();
        // Reactivate via toggle-active endpoint
        $toggleBackRes = $this->patch(route('admin.meeting-alarms.toggle-active', $meeting));
        $toggleBackRes->assertStatus(200);
        $this->assertTrue($meeting->fresh()->is_active);

        $apiResActive = $this->get(route('meeting-alarms.upcoming'));
        $apiResActive->assertStatus(200);
        $alarmIdsActive = collect($apiResActive->json('alarms'))->pluck('id')->toArray();
        $this->assertContains($meeting->id, $alarmIdsActive);

        // Test update
        $updateRes = $this->put(route('admin.meeting-alarms.update', $meeting), [
            'title' => 'Updated Weekly Sync',
            'meeting_time' => $nowPP->addMinutes(30)->format('Y-m-d H:i:s'),
            'meeting_link' => 'https://meet.google.com/new-link',
            'sound' => 'bell-chime.mp3',
            'ring_duration' => 10,
            'is_active' => '1',
        ]);
        $updateRes->assertRedirect(route('admin.meeting-alarms.index'));
        $this->assertDatabaseHas('meeting_alarms', [
            'id' => $meeting->id,
            'title' => 'Updated Weekly Sync',
            'sound' => 'bell-chime.mp3',
        ]);

        // Test delete
        $delRes = $this->delete(route('admin.meeting-alarms.destroy', $meeting));
        $delRes->assertRedirect(route('admin.meeting-alarms.index'));
        $this->assertDatabaseMissing('meeting_alarms', ['id' => $meeting->id]);
    }
}
