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
        Schema::table('websites', function (Blueprint $table) {
            $table->index('status');
            $table->index('category');
            $table->index('is_archived');
        });

        Schema::table('website_follow_ups', function (Blueprint $table) {
            $table->index('type');
            $table->index('qc_status');
            $table->index('assigned_to');
            // Note: website_id is likely already indexed if it's a foreign key, but adding it doesn't hurt if we catch
            // or we just skip website_id since it's probably already a foreign key.
        });

        Schema::table('boards', function (Blueprint $table) {
            $table->index('type');
            $table->index('workspace_id');
            $table->index('is_archived');
            $table->index('is_hidden');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->index('list_id');
            $table->index('position');
            $table->index('is_archived');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['category']);
            $table->dropIndex(['is_archived']);
        });

        Schema::table('website_follow_ups', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['qc_status']);
            $table->dropIndex(['assigned_to']);
        });

        Schema::table('boards', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['workspace_id']);
            $table->dropIndex(['is_archived']);
            $table->dropIndex(['is_hidden']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['list_id']);
            $table->dropIndex(['position']);
            $table->dropIndex(['is_archived']);
        });
    }
};
