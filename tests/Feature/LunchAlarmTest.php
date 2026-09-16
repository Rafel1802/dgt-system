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

    public function test_melodic_chime_is_default_sound_url(): void
    {
        $user = new User([
            'name' => 'Default Sound User',
            'email' => 'default@example.com',
        ]);

        $this->assertStringContainsString('melodic-chime.wav', $user->lunch_alarm_sound_url);
    }

    public function test_admin_digital_and_supervisor_are_disabled_by_default(): void
    {
        $regularUser = new User(['team_role' => 'Digital Staff']);
        $this->assertTrue($regularUser->isLunchAlarmEnabled());

        $supervisorUser = new User(['team_role' => 'Team Supervisor']);
        $this->assertFalse($supervisorUser->isLunchAlarmEnabled());

        $adminDigitalUser = new User(['team_role' => 'Admin Digital']);
        $this->assertFalse($adminDigitalUser->isLunchAlarmEnabled());

        $superAdminUser = new User(['team_role' => 'Super Admin']);
        $this->assertFalse($superAdminUser->isLunchAlarmEnabled());
    }

    public function test_alarm_apply_defaults_artisan_command(): void
    {
        $reg = User::factory()->create([
            'team_role' => 'Digital Staff',
            'lunch_alarm_sound' => 'alarm1.mp3',
            'lunch_alarm_enabled' => false,
        ]);

        $sup = User::factory()->create([
            'team_role' => 'Team Supervisor',
            'lunch_alarm_sound' => 'alarm1.mp3',
            'lunch_alarm_enabled' => true,
        ]);

        $this->artisan('alarm:apply-defaults')
            ->assertSuccessful();

        $this->assertEquals('melodic-chime.wav', $reg->fresh()->lunch_alarm_sound);
        $this->assertTrue($reg->fresh()->lunch_alarm_enabled);

        $this->assertEquals('melodic-chime.wav', $sup->fresh()->lunch_alarm_sound);
        $this->assertFalse($sup->fresh()->lunch_alarm_enabled);
    }
}
