<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure default shift alarm duration is 15 seconds in settings table
        try {
            if (Schema::hasTable('settings')) {
                DB::table('settings')->updateOrInsert(
                    ['key' => 'shift_alarm_duration'],
                    [
                        'value' => '15',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {}

        // 2. Update column defaults if supported
        try {
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'lunch_alarm_sound')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('lunch_alarm_sound')->nullable()->default('funny.wav')->change();
                });
            }
            if (Schema::hasTable('meeting_alarms') && Schema::hasColumn('meeting_alarms', 'sound')) {
                Schema::table('meeting_alarms', function (Blueprint $table) {
                    $table->string('sound')->default('funny.wav')->change();
                });
            }
        } catch (\Throwable $e) {}

        // 3. Update existing users: set funny.wav and turn on for everyone except supervisor or admin digital roles
        if (Schema::hasTable('users')) {
            try {
                $users = User::all();
                foreach ($users as $user) {
                    $isExcluded = $user->isSupervisorOrAdminDigital();
                    $user->lunch_alarm_sound = 'funny.wav';
                    $user->lunch_alarm_enabled = !$isExcluded;
                    $user->save();
                }
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversible if needed
    }
};
