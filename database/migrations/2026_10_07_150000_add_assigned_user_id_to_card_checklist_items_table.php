<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('card_checklist_items', 'assigned_user_id')) {
            Schema::table('card_checklist_items', function (Blueprint $table) {
                $table->foreignId('assigned_user_id')->nullable()->after('completed_by')->constrained('users')->nullOnDelete();
            });
        }

        // Backfill existing checklist items where unambiguous matching card member exists
        try {
            $items = \App\Models\CardChecklistItem::with(['checklist.card.assignees'])->whereNull('assigned_user_id')->get();
            foreach ($items as $item) {
                $card = $item->checklist?->card;
                if ($card) {
                    $detectedUserId = \App\Models\CardChecklistItem::detectUserIdForCard($item->content ?? '', $card);
                    if ($detectedUserId) {
                        $item->updateQuietly(['assigned_user_id' => $detectedUserId]);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning("Checklist item backfill warning: " . $e->getMessage());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('card_checklist_items', 'assigned_user_id')) {
            Schema::table('card_checklist_items', function (Blueprint $table) {
                $table->dropForeign(['assigned_user_id']);
                $table->dropColumn('assigned_user_id');
            });
        }
    }
};
