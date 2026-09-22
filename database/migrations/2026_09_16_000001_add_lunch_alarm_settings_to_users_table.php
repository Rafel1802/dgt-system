<?php

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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'lunch_alarm_enabled')) {
                $table->boolean('lunch_alarm_enabled')->default(false)->after('notification_sound');
            }
            if (!Schema::hasColumn('users', 'lunch_alarm_sound')) {
                $table->string('lunch_alarm_sound')->nullable()->default('melodic-chime.wav')->after('lunch_alarm_enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'lunch_alarm_sound')) {
                $table->dropColumn('lunch_alarm_sound');
            }
            if (Schema::hasColumn('users', 'lunch_alarm_enabled')) {
                $table->dropColumn('lunch_alarm_enabled');
            }
        });
    }
};
