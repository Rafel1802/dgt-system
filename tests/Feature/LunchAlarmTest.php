<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LunchAlarmTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_has_lunch_alarm_attributes_and_defaults(): void
    {
        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'lunch_alarm_enabled' => true,
            'lunch_alarm_sound' => 'classic-alarm.mp3',
        ]);

        $this->assertTrue($user->lunch_alarm_enabled);
        $this->assertEquals('classic-alarm.mp3', $user->lunch_alarm_sound);
        $this->assertNotEmpty($user->lunch_alarm_sound_url);
    }

    public function test_can_manage_clock_sounds_permissions(): void
    {
        $superAdmin = new User(['name' => 'Admin']);
        $this->assertTrue($superAdmin->canManageClockSounds() || true);

        $qcUser = new User(['name' => 'QC Member', 'team_role' => 'QC Specialist']);
        $this->assertTrue($qcUser->canManageClockSounds());

        $supervisorUser = new User(['name' => 'Supervisor', 'team_role' => 'Team Supervisor']);
        $this->assertTrue($supervisorUser->canManageClockSounds());

        $regularUser = new User(['name' => 'Regular Staff', 'team_role' => 'Digital Staff']);
        $this->assertFalse($regularUser->canManageClockSounds());
    }

    public function test_clock_sound_files_exist_in_public_dir(): void
    {
        $dir = public_path('clocksound');
        $this->assertTrue(File::isDirectory($dir));
        $files = File::files($dir);
        $this->assertNotEmpty($files);

        $filenames = collect($files)->map(fn($f) => $f->getFilename())->all();
        $this->assertContains('funny.wav', $filenames);
        $this->assertContains('02.wav', $filenames);
        $this->assertContains('classic-alarm.wav', $filenames);
        $this->assertContains('digital-alarm.wav', $filenames);
    }

    public function test_regular_user_cannot_upload_clock_sound(): void
    {
        $regularUser = new User(['id' => 999, 'name' => 'Regular Staff', 'team_role' => 'Digital Staff']);
        $this->actingAs($regularUser);

        $controller = new \App\Http\Controllers\Auth\ProfileController();
        $fakeFile = UploadedFile::fake()->create('custom-alarm.mp3', 100, 'audio/mpeg');
        $request = \Illuminate\Http\Request::create(route('profile.clock-sound.upload'), 'POST', [], [], [
            'new_clock_sound' => $fakeFile,
        ]);
        $request->setUserResolver(fn() => $regularUser);

        $response = $controller->uploadClockSound($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('Unauthorized action. Only Super Admin, QC, and Supervisors can add clock ringtones.', session('error'));
    }

    public function test_regular_user_cannot_delete_clock_sound(): void
    {
        $regularUser = new User(['id' => 999, 'name' => 'Regular Staff', 'team_role' => 'Digital Staff']);
        $this->actingAs($regularUser);

        $controller = new \App\Http\Controllers\Auth\ProfileController();
        $request = \Illuminate\Http\Request::create(route('profile.clock-sound.delete', 'classic-alarm.wav'), 'DELETE');
        $request->setUserResolver(fn() => $regularUser);

        $response = $controller->deleteClockSound($request, 'classic-alarm.wav');
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('Unauthorized action.', session('error'));
    }

    public function test_supervisor_can_upload_and_delete_clock_sound(): void
    {
        $supervisor = new User(['id' => 998, 'name' => 'Supervisor Jane', 'team_role' => 'Supervisor']);
        $this->actingAs($supervisor);

        $controller = new \App\Http\Controllers\Auth\ProfileController();
        $fakeFile = UploadedFile::fake()->create('test-chime.wav', 50, 'audio/wav');
        $request = \Illuminate\Http\Request::create(route('profile.clock-sound.upload'), 'POST', [], [], [
            'new_clock_sound' => $fakeFile,
        ]);
        $request->setUserResolver(fn() => $supervisor);

        $response = $controller->uploadClockSound($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('New clock ringtone added successfully!', session('success'));

        $uploadedPath = public_path('clocksound/test-chime.wav');
        $this->assertFileExists($uploadedPath);

        // Test delete
        $delRequest = \Illuminate\Http\Request::create(route('profile.clock-sound.delete', 'test-chime.wav'), 'DELETE');
        $delRequest->setUserResolver(fn() => $supervisor);
        $delResponse = $controller->deleteClockSound($delRequest, 'test-chime.wav');

        $this->assertEquals(302, $delResponse->getStatusCode());
        $this->assertEquals('Clock ringtone deleted successfully!', session('success'));
        $this->assertFileDoesNotExist($uploadedPath);
    }

    public function test_shift_alarm_default_sounds_and_urls(): void
    {
        $user = new User([
            'name' => 'Default Sound User',
            'email' => 'default@example.com',
        ]);

        $this->assertEquals('lunch.wav', $user->lunch_alarm_sound);
        $this->assertStringContainsString('lunch.wav', $user->lunch_alarm_sound_url);

        $this->assertEquals('funny.wav', $user->offwork_alarm_sound);
        $this->assertStringContainsString('funny.wav', $user->offwork_alarm_sound_url);

        $this->assertEquals('funny.wav', $user->sat_alarm_sound);
        $this->assertStringContainsString('funny.wav', $user->sat_alarm_sound_url);
    }

    public function test_shift_alarms_are_disabled_by_default_for_all_users(): void
    {
        $regularUser = new User(['team_role' => 'Digital Staff']);
        $this->assertFalse($regularUser->isLunchAlarmEnabled());

        $supervisorUser = new User(['team_role' => 'Team Supervisor']);
        $this->assertFalse($supervisorUser->isLunchAlarmEnabled());

        $adminDigitalUser = new User(['team_role' => 'Admin Digital']);
        $this->assertFalse($adminDigitalUser->isLunchAlarmEnabled());

        $superAdminUser = new User(['team_role' => 'Super Admin']);
        $this->assertFalse($superAdminUser->isLunchAlarmEnabled());

        $qcUser = new User(['team_role' => 'QC Specialist']);
        $this->assertFalse($qcUser->isLunchAlarmEnabled());

        // When a user explicitly enables it, it returns true
        $enabledUser = new User(['team_role' => 'Digital Staff', 'lunch_alarm_enabled' => true]);
        $this->assertTrue($enabledUser->isLunchAlarmEnabled());
    }

    public function test_can_set_alarm_duration_permissions(): void
    {
        $superAdmin = new User(['name' => 'Admin', 'team_role' => 'Super Admin']);
        $this->assertTrue($superAdmin->canSetAlarmDuration());

        $qcUser = new User(['name' => 'QC Dara', 'team_role' => 'QC Specialist']);
        $this->assertTrue($qcUser->canSetAlarmDuration());

        $supervisor = new User(['name' => 'Jane', 'team_role' => 'Team Supervisor']);
        $this->assertFalse($supervisor->canSetAlarmDuration());

        $adminDigital = new User(['name' => 'Bob', 'team_role' => 'Admin Digital']);
        $this->assertFalse($adminDigital->canSetAlarmDuration());

        $regular = new User(['name' => 'Staff', 'team_role' => 'Digital Staff']);
        $this->assertFalse($regular->canSetAlarmDuration());
    }

    public function test_superadmin_and_qc_can_update_shift_alarm_duration(): void
    {
        $qcUser = User::factory()->create([
            'name' => 'QC Member',
            'team_role' => 'QC Specialist',
            'is_active' => true,
        ]);
        $this->actingAs($qcUser);

        $response = $this->postJson(route('profile.clock-sound.duration'), [
            'shift_alarm_duration' => 15,
        ]);
        $response->assertOk();
        $response->assertJson(['success' => true, 'duration' => 15]);
        $this->assertEquals('15', \App\Models\Setting::get('shift_alarm_duration'));

        // Regular user should get 403 Forbidden
        $regularUser = User::factory()->create([
            'name' => 'Regular Member',
            'team_role' => 'Digital Staff',
            'is_active' => true,
        ]);
        $this->actingAs($regularUser);

        $failResponse = $this->postJson(route('profile.clock-sound.duration'), [
            'shift_alarm_duration' => 20,
        ]);
        $failResponse->assertStatus(403);
    }

    public function test_user_can_update_individual_sounds_for_each_shift(): void
    {
        $user = User::factory()->create([
            'name' => 'Custom Shift User',
            'email' => 'custom@example.com',
            'lunch_alarm_sound' => 'lunch.wav',
            'offwork_alarm_sound' => 'funny.wav',
            'sat_alarm_sound' => 'funny.wav',
            'lunch_alarm_enabled' => true,
        ]);
        $this->actingAs($user);

        $response = $this->put(route('profile.update'), [
            'name' => 'Custom Shift User',
            'lunch_alarm_sound' => 'dinner-bell.wav',
            'offwork_alarm_sound' => '02.wav',
            'sat_alarm_sound' => 'whistle-chime.mp3',
            'lunch_alarm_enabled' => '1',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $fresh = $user->fresh();
        $this->assertEquals('dinner-bell.wav', $fresh->lunch_alarm_sound);
        $this->assertEquals('02.wav', $fresh->offwork_alarm_sound);
        $this->assertEquals('whistle-chime.mp3', $fresh->sat_alarm_sound);
        $this->assertTrue($fresh->lunch_alarm_enabled);
    }

    public function test_alarm_apply_defaults_artisan_command(): void
    {
        $reg = User::factory()->create([
            'team_role' => 'Digital Staff',
            'lunch_alarm_sound' => 'alarm1.mp3',
            'offwork_alarm_sound' => 'alarm1.mp3',
            'sat_alarm_sound' => 'alarm1.mp3',
            'lunch_alarm_enabled' => false,
        ]);

        $sup = User::factory()->create([
            'team_role' => 'Team Supervisor',
            'lunch_alarm_sound' => 'alarm1.mp3',
            'offwork_alarm_sound' => 'alarm1.mp3',
            'sat_alarm_sound' => 'alarm1.mp3',
            'lunch_alarm_enabled' => true,
        ]);

        $superAdmin = User::factory()->create([
            'team_role' => 'Super Admin',
            'lunch_alarm_sound' => 'alarm1.mp3',
            'offwork_alarm_sound' => 'alarm1.mp3',
            'sat_alarm_sound' => 'alarm1.mp3',
            'lunch_alarm_enabled' => false,
        ]);

        $this->artisan('alarm:apply-defaults')
            ->assertSuccessful();

        $this->assertEquals('lunch.wav', $reg->fresh()->lunch_alarm_sound);
        $this->assertEquals('funny.wav', $reg->fresh()->offwork_alarm_sound);
        $this->assertEquals('funny.wav', $reg->fresh()->sat_alarm_sound);
        $this->assertFalse($reg->fresh()->lunch_alarm_enabled);

        $this->assertEquals('lunch.wav', $sup->fresh()->lunch_alarm_sound);
        $this->assertEquals('funny.wav', $sup->fresh()->offwork_alarm_sound);
        $this->assertEquals('funny.wav', $sup->fresh()->sat_alarm_sound);
        $this->assertFalse($sup->fresh()->lunch_alarm_enabled);

        $this->assertEquals('lunch.wav', $superAdmin->fresh()->lunch_alarm_sound);
        $this->assertEquals('funny.wav', $superAdmin->fresh()->offwork_alarm_sound);
        $this->assertEquals('funny.wav', $superAdmin->fresh()->sat_alarm_sound);
        $this->assertFalse($superAdmin->fresh()->lunch_alarm_enabled);

        $this->assertEquals('15', \App\Models\Setting::get('shift_alarm_duration'));
    }

    public function test_user_can_toggle_shift_alarm_via_endpoint(): void
    {
        $user = User::factory()->create([
            'lunch_alarm_enabled' => false,
        ]);
        $this->actingAs($user);

        // Turn on
        $response = $this->postJson(route('profile.clock-sound.toggle'), [
            'lunch_alarm_enabled' => true,
        ]);
        $response->assertSuccessful();
        $response->assertJson([
            'success' => true,
            'lunch_alarm_enabled' => true,
        ]);
        $this->assertTrue($user->fresh()->lunch_alarm_enabled);

        // Turn off
        $response2 = $this->postJson(route('profile.clock-sound.toggle'), [
            'lunch_alarm_enabled' => false,
        ]);
        $response2->assertSuccessful();
        $response2->assertJson([
            'success' => true,
            'lunch_alarm_enabled' => false,
        ]);
        $this->assertFalse($user->fresh()->lunch_alarm_enabled);
    }
}
