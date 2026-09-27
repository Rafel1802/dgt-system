<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (Schema::hasTable('cards')) {
            $existingCardIndexes = $isSqlite
                ? collect(DB::select("PRAGMA index_list('cards')"))->pluck('name')->toArray()
                : collect(DB::select("SHOW INDEX FROM cards"))->pluck('Key_name')->unique()->toArray();

            Schema::table('cards', function (Blueprint $table) use ($existingCardIndexes) {
                if (!in_array('cards_list_arch_del_pos_idx', $existingCardIndexes)) {
                    $table->index(['board_list_id', 'is_archived', 'deleted_at', 'position'], 'cards_list_arch_del_pos_idx');
                }
                if (!in_array('cards_board_arch_del_idx', $existingCardIndexes)) {
                    $table->index(['board_id', 'is_archived', 'deleted_at'], 'cards_board_arch_del_idx');
                }
                if (!in_array('cards_smm_class_label_idx', $existingCardIndexes)) {
                    $table->index('smm_class_label', 'cards_smm_class_label_idx');
                }
                if (!in_array('cards_smm_team_label_idx', $existingCardIndexes)) {
                    $table->index('smm_team_label', 'cards_smm_team_label_idx');
                }
                if (!in_array('cards_content_pub_date_idx', $existingCardIndexes)) {
                    $table->index('content_public_date', 'cards_content_pub_date_idx');
                }
                if (!in_array('cards_creator_arch_del_idx', $existingCardIndexes)) {
                    $table->index(['created_by', 'is_archived', 'deleted_at'], 'cards_creator_arch_del_idx');
                }
            });
        }

        if (Schema::hasTable('card_checklist_items')) {
            $existingChkIndexes = $isSqlite
                ? collect(DB::select("PRAGMA index_list('card_checklist_items')"))->pluck('name')->toArray()
                : collect(DB::select("SHOW INDEX FROM card_checklist_items"))->pluck('Key_name')->unique()->toArray();

            Schema::table('card_checklist_items', function (Blueprint $table) use ($existingChkIndexes) {
                if (!in_array('card_chk_items_done_idx', $existingChkIndexes)) {
                    $table->index(['checklist_id', 'is_completed'], 'card_chk_items_done_idx');
                }
            });
        }
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';
        if ($isSqlite) {
            return;
        }

        if (Schema::hasTable('cards')) {
            $existingCardIndexes = collect(DB::select("SHOW INDEX FROM cards"))->pluck('Key_name')->unique()->toArray();

            Schema::table('cards', function (Blueprint $table) use ($existingCardIndexes) {
                foreach (['cards_list_arch_del_pos_idx', 'cards_board_arch_del_idx', 'cards_smm_class_label_idx', 'cards_smm_team_label_idx', 'cards_content_pub_date_idx', 'cards_creator_arch_del_idx'] as $idx) {
                    if (in_array($idx, $existingCardIndexes)) {
                        $table->dropIndex($idx);
                    }
                }
            });
        }

        if (Schema::hasTable('card_checklist_items')) {
            $existingChkIndexes = collect(DB::select("SHOW INDEX FROM card_checklist_items"))->pluck('Key_name')->unique()->toArray();

            Schema::table('card_checklist_items', function (Blueprint $table) use ($existingChkIndexes) {
                if (in_array('card_chk_items_done_idx', $existingChkIndexes)) {
                    $table->dropIndex('card_chk_items_done_idx');
                }
            });
        }
    }
};
