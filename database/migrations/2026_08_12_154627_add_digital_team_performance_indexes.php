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
        $addIndex = function($table, $column) {
            try {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                    $tableBlueprint->index($column);
                });
            } catch (\Exception $e) {
                // Index might already exist, skip safely
            }
        };

        // Websites
        $addIndex('websites', 'handled_by');
        $addIndex('websites', 'status');
        $addIndex('websites', 'is_archived');
        
        // Boards & Cards
        $addIndex('lists', 'board_id'); // Assuming table is lists or board_lists
        $addIndex('lists', 'position');
        
        $addIndex('labels', 'board_id');
        
        $addIndex('comments', 'card_id');
        $addIndex('comments', 'user_id');
        
        $addIndex('card_members', 'card_id');
        $addIndex('card_members', 'user_id');

        $addIndex('card_labels', 'card_id');
        $addIndex('card_labels', 'label_id');
        
        // Website related
        $addIndex('website_classes', 'website_id');
        $addIndex('website_members', 'website_id');
        $addIndex('website_members', 'user_id');
        $addIndex('website_follow_ups', 'website_id');
        $addIndex('website_progress_logs', 'website_id');
    }

    public function down(): void
    {
        $dropIndex = function($table, $column) {
            try {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($table, $column) {
                    $tableBlueprint->dropIndex("{$table}_{$column}_index");
                });
            } catch (\Exception $e) {}
        };

        $dropIndex('websites', 'handled_by');
        $dropIndex('websites', 'status');
        $dropIndex('websites', 'is_archived');
        
        $dropIndex('lists', 'board_id');
        $dropIndex('lists', 'position');
        
        $dropIndex('labels', 'board_id');
        
        $dropIndex('comments', 'card_id');
        $dropIndex('comments', 'user_id');
        
        $dropIndex('card_members', 'card_id');
        $dropIndex('card_members', 'user_id');
        $dropIndex('card_labels', 'card_id');
        $dropIndex('card_labels', 'label_id');
        
        $dropIndex('website_classes', 'website_id');
        $dropIndex('website_members', 'website_id');
        $dropIndex('website_members', 'user_id');
        $dropIndex('website_follow_ups', 'website_id');
        $dropIndex('website_progress_logs', 'website_id');
    }
};
