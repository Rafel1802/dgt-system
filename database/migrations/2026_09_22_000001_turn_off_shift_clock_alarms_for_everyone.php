<?php

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
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'lunch_alarm_enabled')) {
            // 1. Change column default value to false (0)
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->boolean('lunch_alarm_enabled')->default(false)->change();
                });
            } catch (\Throwable $e) {}

            // 2. Turn off shift clock alarms for ALL existing users immediately
            try {
                DB::table('users')->update(['lunch_alarm_enabled' => false]);
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'lunch_alarm_enabled')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->boolean('lunch_alarm_enabled')->default(true)->change();
                });
            } catch (\Throwable $e) {}
        }
    }
};
