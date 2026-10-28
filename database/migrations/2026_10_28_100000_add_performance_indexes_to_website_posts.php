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
        if (Schema::hasTable('website_posts')) {
            Schema::table('website_posts', function (Blueprint $table) {
                // Standalone indexes for fast cross-website leaderboard sorting and filtering
                $table->index('cumulative_views', 'ws_posts_cumulative_views_idx');
                $table->index('period_views', 'ws_posts_period_views_idx');
                $table->index('published_at', 'ws_posts_published_at_idx');
                $table->index('analytics_source', 'ws_posts_analytics_source_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('website_posts')) {
            Schema::table('website_posts', function (Blueprint $table) {
                $table->dropIndex('ws_posts_cumulative_views_idx');
                $table->dropIndex('ws_posts_period_views_idx');
                $table->dropIndex('ws_posts_published_at_idx');
                $table->dropIndex('ws_posts_analytics_source_idx');
            });
        }
    }
};
