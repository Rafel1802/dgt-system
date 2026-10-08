<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('card_checklist_items', 'assigned_user_ids')) {
            Schema::table('card_checklist_items', function (Blueprint $table) {
                // Multiple assignees per checklist item (assigned_user_id stays as the first/primary one)
                $table->json('assigned_user_ids')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('card_checklist_items', 'assigned_user_ids')) {
            Schema::table('card_checklist_items', function (Blueprint $table) {
                $table->dropColumn('assigned_user_ids');
            });
        }
    }
};
