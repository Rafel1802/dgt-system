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
    protected $description = 'Update all users to use melodic-chime.wav ringtone, keep admin-digital and supervisors disabled, and set duration to 10s';

    public function handle(): int
    {
        $this->info('Starting alarm settings update for all users...');

        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'lunch_alarm_sound')) {
            $this->error('The users table or lunch_alarm_sound column does not exist. Please run migrations first.');
            return 1;
        }

        $users = User::all();
        $updatedCount = 0;
        $disabledCount = 0;
        $enabledCount = 0;

        foreach ($users as $user) {
            $isExcluded = false;

            try {
                if ($user->hasAnyRole(['admin-digital', 'supervisor', 'ebay-supervisor', 'logistic-supervisor', 'super-admin'])) {
                    $isExcluded = true;
                }
            } catch (\Throwable $e) {}

            $teamRole = strtolower($user->team_role ?? '');
            if (
                str_contains($teamRole, 'supervisor') ||
                str_contains($teamRole, 'admin digital') ||
                str_contains($teamRole, 'super admin') ||
                str_contains($teamRole, 'superuser')
            ) {
                $isExcluded = true;
            }

            $user->lunch_alarm_sound = 'melodic-chime.wav';
            $user->lunch_alarm_enabled = !$isExcluded;
            $user->save();

            $updatedCount++;
            if ($isExcluded) {
                $disabledCount++;
            } else {
                $enabledCount++;
            }
        }

        $this->info("✓ Successfully updated {$updatedCount} users:");
        $this->line("   - {$enabledCount} users enabled with 'melodic-chime.wav'");
        $this->line("   - {$disabledCount} users (admin-digital, supervisor, super-admin) kept turned off");

        if (Schema::hasTable('meeting_alarms') && Schema::hasColumn('meeting_alarms', 'ring_duration')) {
            MeetingAlarm::whereNull('ring_duration')->orWhere('ring_duration', 5)->orWhere('ring_duration', 8)->update([
                'ring_duration' => 10,
            ]);
            $this->info("✓ Updated meeting alarms to 10 seconds ring duration.");
        }

        return 0;
    }
}
