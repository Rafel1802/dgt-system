<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_checklist_items', function (Blueprint $table) {
            if (!Schema::hasColumn('card_checklist_items', 'is_marked')) {
                $table->boolean('is_marked')->default(false);
                $table->unsignedBigInteger('marked_by')->nullable();
                $table->timestamp('marked_at')->nullable();
            }
            if (!Schema::hasColumn('card_checklist_items', 'has_issue')) {
                $table->boolean('has_issue')->default(false);
                $table->unsignedBigInteger('issue_by')->nullable();
                $table->timestamp('issue_at')->nullable();
            }
            if (!Schema::hasColumn('card_checklist_items', 'is_approved')) {
                $table->boolean('is_approved')->default(false);
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
            }
        });

        // Backfill auto-assigned users for existing "Listing" / "Content" checklist items
        try {
            \App\Models\CardChecklistItem::whereNull('assigned_user_id')->get()->each(function ($item) {
                $userId = \App\Models\CardChecklistItem::detectSpecialUserId($item->content ?? '');
                if ($userId) {
                    $item->updateQuietly(['assigned_user_id' => $userId]);
                }
            });
        } catch (\Throwable $e) {
            \Log::warning('Checklist listing/content backfill warning: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        $columns = [
            'is_marked', 'marked_by', 'marked_at',
            'has_issue', 'issue_by', 'issue_at',
            'is_approved', 'approved_by', 'approved_at',
        ];
        foreach ($columns as $col) {
            if (Schema::hasColumn('card_checklist_items', $col)) {
                Schema::table('card_checklist_items', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
