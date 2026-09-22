<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add offwork_alarm_sound and sat_alarm_sound columns to users table
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'offwork_alarm_sound')) {
                    $table->string('offwork_alarm_sound')->nullable()->default('funny.wav')->after('lunch_alarm_sound');
                }
                if (!Schema::hasColumn('users', 'sat_alarm_sound')) {
                    $table->string('sat_alarm_sound')->nullable()->default('funny.wav')->after('offwork_alarm_sound');
                }
            });

            // Update column default for lunch_alarm_sound to lunch.wav
            try {
                if (Schema::hasColumn('users', 'lunch_alarm_sound')) {
                    Schema::table('users', function (Blueprint $table) {
                        $table->string('lunch_alarm_sound')->nullable()->default('lunch.wav')->change();
                    });
                }
            } catch (\Throwable $e) {}
        }

        // 2. Set defaults for existing users:
        //    - 12:00 PM: lunch.wav
        //    - 4:00 PM:  funny.wav
        //    - 11:00 AM: funny.wav
        //    - Alarm ON for all, EXCEPT supervisors and admin-digital (OFF)
        if (Schema::hasTable('users')) {
            try {
                $users = User::all();
                foreach ($users as $user) {
                    $isExcluded = $user->isSupervisorOrAdminDigital();
                    $user->lunch_alarm_sound = 'lunch.wav';
                    $user->offwork_alarm_sound = 'funny.wav';
                    $user->sat_alarm_sound = 'funny.wav';
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
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'offwork_alarm_sound')) {
                    $table->dropColumn('offwork_alarm_sound');
                }
                if (Schema::hasColumn('users', 'sat_alarm_sound')) {
                    $table->dropColumn('sat_alarm_sound');
                }
            });
        }
    }
};
