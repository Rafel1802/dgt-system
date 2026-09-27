<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Squad Members (allows Dara & Kim to add staff to their squads from users)
        if (!Schema::hasTable('kpi_squad_members')) {
            Schema::create('kpi_squad_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('squad_id')->constrained('kpi_squads')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role_title')->nullable()->default('Team Member');
                $table->date('joined_date')->nullable();
                $table->timestamps();

                $table->unique(['squad_id', 'user_id']);
            });
        }

        // 2. Enhance KPI Reviews with squad_id and supervisor_notes
        if (Schema::hasTable('kpi_reviews')) {
            Schema::table('kpi_reviews', function (Blueprint $table) {
                if (!Schema::hasColumn('kpi_reviews', 'squad_id')) {
                    $table->foreignId('squad_id')->nullable()->after('kpi_period_id')->constrained('kpi_squads')->nullOnDelete();
                }
                if (!Schema::hasColumn('kpi_reviews', 'supervisor_notes')) {
                    $table->text('supervisor_notes')->nullable()->after('manager_notes');
                }
            });
        }

        // Allow kpi_assignment_id in kpi_reviews to be nullable
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE kpi_reviews MODIFY kpi_assignment_id BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_squad_members');
        if (Schema::hasTable('kpi_reviews')) {
            Schema::table('kpi_reviews', function (Blueprint $table) {
                if (Schema::hasColumn('kpi_reviews', 'squad_id')) {
                    $table->dropForeign(['squad_id']);
                    $table->dropColumn('squad_id');
                }
                if (Schema::hasColumn('kpi_reviews', 'supervisor_notes')) {
                    $table->dropColumn('supervisor_notes');
                }
            });
        }
    }
};
