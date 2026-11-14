<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_checklist_items', function (Blueprint $table) {
            if (!Schema::hasColumn('card_checklist_items', 'is_uploaded')) {
                $table->boolean('is_uploaded')->default(false)->after('is_approved');
            }
            if (!Schema::hasColumn('card_checklist_items', 'uploaded_by')) {
                $table->unsignedBigInteger('uploaded_by')->nullable()->after('is_uploaded');
            }
            if (!Schema::hasColumn('card_checklist_items', 'uploaded_at')) {
                $table->timestamp('uploaded_at')->nullable()->after('uploaded_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('card_checklist_items', function (Blueprint $table) {
            if (Schema::hasColumn('card_checklist_items', 'uploaded_at')) {
                $table->dropColumn('uploaded_at');
            }
            if (Schema::hasColumn('card_checklist_items', 'uploaded_by')) {
                $table->dropColumn('uploaded_by');
            }
            if (Schema::hasColumn('card_checklist_items', 'is_uploaded')) {
                $table->dropColumn('is_uploaded');
            }
        });
    }
};

