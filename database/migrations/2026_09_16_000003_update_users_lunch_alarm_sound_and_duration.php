<?php

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
        // 1. Update column defaults if supported
        try {
            if (Schema::hasColumn('users', 'lunch_alarm_sound')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('lunch_alarm_sound')->nullable()->default('melodic-chime.wav')->change();
                });
            }
        } catch (\Throwable $e) {}

        try {
            if (Schema::hasTable('meeting_alarms') && Schema::hasColumn('meeting_alarms', 'sound')) {
                Schema::table('meeting_alarms', function (Blueprint $table) {
                    $table->string('sound')->default('melodic-chime.wav')->change();
                    $table->integer('ring_duration')->default(8)->change();
                });
            }
        } catch (\Throwable $e) {}

        // 2. Update all existing users
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'lunch_alarm_sound')) {
            DB::table('users')->update([
                'lunch_alarm_sound' => 'melodic-chime.wav',
            ]);

            try {
                $users = User::all();
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

                    $user->lunch_alarm_enabled = !$isExcluded;
                    $user->lunch_alarm_sound = 'melodic-chime.wav';
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
