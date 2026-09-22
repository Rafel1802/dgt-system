<?php

namespace App\Console\Commands;

use App\Models\MeetingAlarm;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateAlarmDefaultsCommand extends Command
{
    protected $signature = 'alarm:apply-defaults {--force : Force apply without confirmation}';
    protected $description = 'Update all users to use lunch.wav for 12PM, funny.wav for 4PM and Sat 11AM, turn off shift clock alarms for everyone by default, and set duration to 15s';

    public function handle(): int
    {
        $this->info('Starting alarm settings update for all users...');

        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'lunch_alarm_sound')) {
            $this->error('The users table or lunch_alarm_sound column does not exist. Please run migrations first.');
            return 1;
        }

        $users = User::all();
        $updatedCount = 0;

        foreach ($users as $user) {
            $user->lunch_alarm_sound = 'lunch.wav';
            if (Schema::hasColumn('users', 'offwork_alarm_sound')) {
                $user->offwork_alarm_sound = 'funny.wav';
            }
            if (Schema::hasColumn('users', 'sat_alarm_sound')) {
                $user->sat_alarm_sound = 'funny.wav';
            }
            $user->lunch_alarm_enabled = false;
            $user->save();

            $updatedCount++;
        }

        $this->info("✓ Successfully updated {$updatedCount} users:");
        $this->line("   - Default sounds set (12PM: lunch.wav, 4PM: funny.wav, Sat 11AM: funny.wav)");
        $this->line("   - Shift clock alarms turned OFF for all {$updatedCount} users (opt-in via profile)");

        // Ensure default shift alarm duration in settings is 15s
        if (Schema::hasTable('settings')) {
            \App\Models\Setting::updateOrCreate(
                ['key' => 'shift_alarm_duration'],
                ['value' => '15']
            );
            $this->info("✓ Set shift alarm duration to 15 seconds in settings.");
        }

        if (Schema::hasTable('meeting_alarms') && Schema::hasColumn('meeting_alarms', 'ring_duration')) {
            MeetingAlarm::whereNull('ring_duration')->orWhere('ring_duration', '<', 10)->update([
                'ring_duration' => 15,
            ]);
            $this->info("✓ Updated meeting alarms default ring duration to 15 seconds.");
        }

        return 0;
    }
}
