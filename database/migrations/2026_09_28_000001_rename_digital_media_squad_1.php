<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kpi_squads')) {
            DB::table('kpi_squads')
                ->where('name', 'Digital Media Squad 1')
                ->orWhere('id', 1)
                ->update(['name' => 'Digital Media Production 1']);
        }

        // Backfill evaluation_date where null
        if (Schema::hasTable('kpi_reviews') && Schema::hasColumn('kpi_reviews', 'evaluation_date')) {
            DB::table('kpi_reviews')
                ->whereNull('evaluation_date')
                ->update(['evaluation_date' => '2026-09-27']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kpi_squads')) {
            DB::table('kpi_squads')
                ->where('name', 'Digital Media Production 1')
                ->orWhere('id', 1)
                ->update(['name' => 'Digital Media Squad 1']);
        }
    }
};
