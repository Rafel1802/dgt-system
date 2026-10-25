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
        // 1. Google Search Console Sitemaps Table
        if (!Schema::hasTable('google_search_console_sitemaps')) {
            Schema::create('google_search_console_sitemaps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('site_url')->index(); // e.g. "sc-domain:example.com" or "https://example.com/"
                $table->string('sitemap_url', 1000);
                $table->string('type', 50)->default('SITEMAP'); // SITEMAP, INDEX, RSS_FEED, ATOM_FEED
                $table->boolean('is_pending')->default(false);
                $table->boolean('is_sitemaps_index')->default(false);
                $table->timestamp('last_submitted_at')->nullable();
                $table->timestamp('last_downloaded_at')->nullable();
                $table->unsignedInteger('submitted_count')->default(0);
                $table->unsignedInteger('indexed_count')->default(0);
                $table->unsignedInteger('errors_count')->default(0);
                $table->unsignedInteger('warnings_count')->default(0);
                $table->string('status', 30)->default('success')->index(); // success, pending, warning, error
                $table->text('error_message')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['website_id', 'status'], 'gsc_sitemaps_site_status_idx');
            });
        }

        // 2. Google Search Console URL Inspections Table
        if (!Schema::hasTable('google_search_console_url_inspections')) {
            Schema::create('google_search_console_url_inspections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('site_url')->index();
                $table->text('url');
                $table->char('url_hash', 40)->index(); // sha1(url)
                $table->string('verdict', 30)->default('PASS')->index(); // PASS, NEUTRAL, FAIL
                $table->string('coverage_state', 255)->default('Submitted and indexed')->index();
                $table->boolean('is_indexed')->default(true)->index();
                $table->string('indexing_state', 100)->nullable();
                $table->string('page_fetch_state', 100)->nullable();
                $table->string('robots_txt_state', 50)->nullable();
                $table->string('crawled_as', 50)->nullable(); // MOBILE, DESKTOP
                $table->timestamp('last_crawl_time')->nullable();
                $table->text('google_canonical')->nullable();
                $table->text('user_canonical')->nullable();
                $table->text('sitemap_url')->nullable();
                $table->string('mobile_verdict', 30)->nullable();
                $table->json('issues')->nullable();
                $table->json('raw_data')->nullable();
                $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('inspected_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['website_id', 'url_hash'], 'gsc_url_inspections_site_hash_unique');
                $table->index(['website_id', 'is_indexed'], 'gsc_url_inspections_indexed_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_search_console_url_inspections');
        Schema::dropIfExists('google_search_console_sitemaps');
    }
};
