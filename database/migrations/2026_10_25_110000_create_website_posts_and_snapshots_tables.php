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
        if (!Schema::hasTable('website_posts')) {
            Schema::create('website_posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('external_id', 191);
                $table->text('title');
                $table->string('slug', 500)->nullable();
                $table->string('canonical_url', 1000);
                $table->string('author_name', 255)->nullable();
                $table->string('status', 50)->default('published'); // published, draft, scheduled, archived
                $table->dateTime('published_at')->nullable();
                $table->dateTime('modified_at')->nullable();
                $table->string('featured_image_url', 1000)->nullable();
                $table->text('excerpt')->nullable();
                $table->unsignedBigInteger('cumulative_views')->default(0);
                $table->unsignedBigInteger('period_views')->default(0);
                $table->unsignedBigInteger('page_views')->default(0);
                $table->unsignedBigInteger('unique_visitors')->default(0);
                $table->string('analytics_source', 50)->default('none'); // wp_postviews, jetpack, post_views_counter, ga4, custom_counter, none
                $table->json('metrics_data')->nullable();
                $table->string('data_completeness', 50)->default('complete'); // complete, partial_views_only, no_analytics
                $table->string('sync_hash', 64)->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['website_id', 'external_id'], 'ws_posts_ws_ext_unique');
                $table->index(['website_id', 'published_at'], 'ws_posts_ws_pub_idx');
                $table->index(['website_id', 'cumulative_views'], 'ws_posts_ws_views_idx');
                $table->index('status');
            });
        }

        if (!Schema::hasTable('website_post_analytics_snapshots')) {
            Schema::create('website_post_analytics_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->foreignId('post_id')->constrained('website_posts')->cascadeOnDelete();
                $table->date('snapshot_date');
                $table->unsignedInteger('views')->default(0);
                $table->unsignedInteger('page_views')->default(0);
                $table->unsignedInteger('unique_visitors')->default(0);
                $table->string('source', 50)->default('cms_counter');
                $table->json('raw_metrics')->nullable();
                $table->timestamps();

                $table->unique(['website_id', 'post_id', 'snapshot_date', 'source'], 'ws_snap_unique');
                $table->index(['website_id', 'snapshot_date'], 'ws_snap_date_idx');
            });
        }

        if (!Schema::hasTable('website_sync_logs')) {
            Schema::create('website_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('sync_type', 50); // blog_sync_pull, blog_sync_push, ga4_sync, health_check
                $table->string('status', 50); // success, failed, partial
                $table->unsignedInteger('items_synced')->default(0);
                $table->unsignedInteger('items_updated')->default(0);
                $table->unsignedInteger('items_failed')->default(0);
                $table->unsignedInteger('duration_ms')->default(0);
                $table->text('error_message')->nullable();
                $table->string('request_ip', 45)->nullable();
                $table->json('payload_summary')->nullable();
                $table->string('triggered_by', 50)->default('scheduler'); // scheduler, manual, webhook, api_push
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['website_id', 'created_at'], 'ws_sync_logs_idx');
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_sync_logs');
        Schema::dropIfExists('website_post_analytics_snapshots');
        Schema::dropIfExists('website_posts');
    }
};
