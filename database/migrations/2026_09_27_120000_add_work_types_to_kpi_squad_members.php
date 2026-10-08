<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kpi_squad_members') && !Schema::hasColumn('kpi_squad_members', 'work_types')) {
            Schema::table('kpi_squad_members', function (Blueprint $table) {
                $table->text('work_types')->nullable()->after('role_title');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kpi_squad_members') && Schema::hasColumn('kpi_squad_members', 'work_types')) {
            Schema::table('kpi_squad_members', function (Blueprint $table) {
                $table->dropColumn('work_types');
            });
        }
    }
};
