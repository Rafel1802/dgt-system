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
        Schema::table('boards', function (Blueprint $table) {
            if (!Schema::hasColumn('boards', 'type')) {
                $table->string('type')->default('kanban')->after('workspace_id')->index();
            }
            if (!Schema::hasColumn('boards', 'is_active_smm')) {
                $table->boolean('is_active_smm')->default(false)->after('is_hidden');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropColumn(['type', 'is_active_smm']);
        });
    }
};
