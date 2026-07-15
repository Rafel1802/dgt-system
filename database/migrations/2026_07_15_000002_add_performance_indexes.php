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

        $addIndex('cards', 'status');
        $addIndex('cards', 'is_archived');
        $addIndex('cards', 'priority');
        $addIndex('cards', 'board_list_id');
        
        $addIndex('customers', 'status');
        
        $addIndex('shipments', 'status');
        
        $addIndex('tech_support_cases', 'status');
        
        $addIndex('leads', 'status');
        
        $addIndex('websites', 'is_archived');
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

        $dropIndex('cards', 'status');
        $dropIndex('cards', 'is_archived');
        $dropIndex('cards', 'priority');
        $dropIndex('cards', 'board_list_id');
        
        $dropIndex('customers', 'status');
        $dropIndex('shipments', 'status');
        $dropIndex('tech_support_cases', 'status');
        $dropIndex('leads', 'status');
        $dropIndex('websites', 'is_archived');
    }
};
