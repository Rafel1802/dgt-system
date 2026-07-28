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
        if (!Schema::hasColumn('cards', 'sync_group_id')) {
            Schema::table('cards', function (Blueprint $table) {
                $table->string('sync_group_id')->nullable()->index()->after('id');
            });
        }

        if (!Schema::hasColumn('card_comments', 'sync_id')) {
            Schema::table('card_comments', function (Blueprint $table) {
                $table->string('sync_id')->nullable()->index()->after('id');
            });
        }

        if (!Schema::hasColumn('card_checklists', 'sync_id')) {
            Schema::table('card_checklists', function (Blueprint $table) {
                $table->string('sync_id')->nullable()->index()->after('id');
            });
        }

        if (!Schema::hasColumn('card_checklist_items', 'sync_id')) {
            Schema::table('card_checklist_items', function (Blueprint $table) {
                $table->string('sync_id')->nullable()->index()->after('id');
            });
        }

        if (!Schema::hasColumn('card_files', 'sync_id')) {
            Schema::table('card_files', function (Blueprint $table) {
                $table->string('sync_id')->nullable()->index()->after('id');
            });
        }

        if (!Schema::hasColumn('board_automations', 'action_type')) {
            Schema::table('board_automations', function (Blueprint $table) {
                $table->string('action_type')->default('move')->after('target_list_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('cards', 'sync_group_id')) {
            Schema::table('cards', function (Blueprint $table) {
                $table->dropColumn('sync_group_id');
            });
        }

        if (Schema::hasColumn('card_comments', 'sync_id')) {
            Schema::table('card_comments', function (Blueprint $table) {
                $table->dropColumn('sync_id');
            });
        }

        if (Schema::hasColumn('card_checklists', 'sync_id')) {
            Schema::table('card_checklists', function (Blueprint $table) {
                $table->dropColumn('sync_id');
            });
        }

        if (Schema::hasColumn('card_checklist_items', 'sync_id')) {
            Schema::table('card_checklist_items', function (Blueprint $table) {
                $table->dropColumn('sync_id');
            });
        }

        if (Schema::hasColumn('card_files', 'sync_id')) {
            Schema::table('card_files', function (Blueprint $table) {
                $table->dropColumn('sync_id');
            });
        }

        if (Schema::hasColumn('board_automations', 'action_type')) {
            Schema::table('board_automations', function (Blueprint $table) {
                $table->dropColumn('action_type');
            });
        }
    }
};
