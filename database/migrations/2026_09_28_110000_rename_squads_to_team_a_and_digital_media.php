<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kpi_squads')) {
            // Squad 1 (Dara): Digital Media Production Team A
            DB::table('kpi_squads')
                ->where('id', 1)
                ->orWhere('code', 'SQUAD-1')
                ->update(['name' => 'Digital Media Production Team A']);

            // Squad 2 (Kim): Digital Media Production
            DB::table('kpi_squads')
                ->where('id', 2)
                ->orWhere('code', 'SQUAD-2')
                ->update(['name' => 'Digital Media Production']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kpi_squads')) {
            DB::table('kpi_squads')
                ->where('id', 1)
                ->orWhere('code', 'SQUAD-1')
                ->update(['name' => 'Digital Media Production 1']);

            DB::table('kpi_squads')
                ->where('id', 2)
                ->orWhere('code', 'SQUAD-2')
                ->update(['name' => 'Digital Media Squad 2']);
        }
    }
};
