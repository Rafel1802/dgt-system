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
        Schema::table('labels', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('id')->constrained('workspaces')->nullOnDelete();
            
            // Make board_id nullable
            $table->foreignId('board_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
            
            // Note: Reverting board_id to non-nullable might fail if there are nulls.
            // Ideally, we shouldn't have to revert, but if we do, we might need to delete null rows.
            $table->foreignId('board_id')->nullable(false)->change();
        });
    }
};
