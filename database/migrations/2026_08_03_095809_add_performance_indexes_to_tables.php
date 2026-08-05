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
        // Safe index addition helper
        $addIndex = function($table, $column) {
            try {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                    $tableBlueprint->index($column);
                });
            } catch (\Exception $e) {
                // Index might already exist, skip safely
            }
        };

        $addIndex('boards', 'workspace_id');
        $addIndex('boards', 'is_hidden');
        $addIndex('boards', 'is_archived');
        
        $addIndex('cards', 'board_id');
        $addIndex('cards', 'sync_group_id');
        
        $addIndex('activity_logs', 'user_id');
        $addIndex('activity_logs', 'created_at');
        
        $addIndex('card_files', 'sync_id');
        
        $addIndex('users', 'last_login_at');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safely drop indexes
        $dropIndex = function($table, $column) {
            try {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($table, $column) {
                    $tableBlueprint->dropIndex("{$table}_{$column}_index");
                });
            } catch (\Exception $e) {
                // Ignore if it doesn't exist
            }
        };

        $dropIndex('boards', 'workspace_id');
        $dropIndex('boards', 'is_hidden');
        $dropIndex('boards', 'is_archived');
        
        $dropIndex('cards', 'board_id');
        $dropIndex('cards', 'sync_group_id');
        
        $dropIndex('activity_logs', 'user_id');
        $dropIndex('activity_logs', 'created_at');
        
        $dropIndex('card_files', 'sync_id');
        
        $dropIndex('users', 'last_login_at');
    }
};
