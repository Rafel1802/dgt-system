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
        $indexesToAdd = [
            'websites' => ['status', 'category', 'is_archived'],
            'website_follow_ups' => ['type', 'qc_status', 'assigned_to'],
            'boards' => ['type', 'workspace_id', 'is_archived', 'is_hidden'],
            'cards' => ['board_list_id', 'position', 'is_archived'],
        ];

        foreach ($indexesToAdd as $tableName => $columns) {
            foreach ($columns as $column) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($column) {
                        $table->index($column);
                    });
                } catch (\Illuminate\Database\QueryException $e) {
                    $msg = strtolower($e->getMessage());
                    if (!str_contains($msg, 'already exists') && !str_contains($msg, 'duplicate key name') && !str_contains($msg, 'duplicate key')) {
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexesToDrop = [
            'websites' => ['status', 'category', 'is_archived'],
            'website_follow_ups' => ['type', 'qc_status', 'assigned_to'],
            'boards' => ['type', 'workspace_id', 'is_archived', 'is_hidden'],
            'cards' => ['board_list_id', 'position', 'is_archived'],
        ];

        foreach ($indexesToDrop as $tableName => $columns) {
            foreach ($columns as $column) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($column) {
                        $table->dropIndex([$column]);
                    });
                } catch (\Illuminate\Database\QueryException $e) {
                    $msg = strtolower($e->getMessage());
                    if (!str_contains($msg, 'check that column/key exists') && !str_contains($msg, 'drop index')) {
                        // Just ignore if it fails to drop
                    }
                }
            }
        }
    }
};
