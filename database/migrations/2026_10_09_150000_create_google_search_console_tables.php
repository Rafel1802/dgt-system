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
        // 1. Google Search Console Accounts (OAuth tokens & connection status)
        if (!Schema::hasTable('google_search_console_accounts')) {
            Schema::create('google_search_console_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('google_account_id')->nullable()->index();
                $table->string('email')->nullable()->index();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->string('token_type')->default('Bearer');
                $table->timestamp('expires_at')->nullable();
                $table->text('scopes')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('last_connected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('last_error')->nullable();
                $table->timestamp('last_error_at')->nullable();
                $table->timestamps();
            });
        }

        // 2. Google Search Console Site Mappings (links DGT websites to GSC properties)
        if (!Schema::hasTable('google_search_console_sites')) {
            Schema::create('google_search_console_sites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->nullable()->constrained('google_search_console_accounts')->nullOnDelete();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('site_url')->index(); // e.g. "sc-domain:example.com" or "https://example.com/"
                $table->string('permission_level')->nullable(); // e.g. "siteOwner", "siteFullUser"
                $table->boolean('is_verified')->default(true);
                $table->boolean('is_sync_enabled')->default(true)->index();
                $table->timestamp('last_synced_at')->nullable();
                $table->date('last_data_date')->nullable();
                $table->string('sync_status')->default('idle')->index(); // idle, syncing, success, failed
                $table->text('sync_error')->nullable();
                $table->timestamps();

                $table->unique(['website_id', 'site_url']);
            });
        }

        // 3. Daily Aggregate Metrics (Clicks, Impressions, CTR, Position per site & date)
        if (!Schema::hasTable('google_search_console_daily_metrics')) {
            Schema::create('google_search_console_daily_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('site_url');
                $table->date('data_date')->index();
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('impressions')->default(0);
                $table->double('ctr', 8, 4)->default(0); // 0.0520 = 5.2%
                $table->double('position', 8, 2)->default(0);
                $table->string('search_type', 30)->default('web')->index();
                $table->timestamps();

                $table->unique(['website_id', 'data_date', 'search_type'], 'gsc_daily_unique');
                $table->index(['website_id', 'data_date'], 'gsc_daily_date_idx');
            });
        }

        // 4. Search Keywords / Queries
        if (!Schema::hasTable('google_search_console_search_queries')) {
            Schema::create('google_search_console_search_queries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('site_url');
                $table->date('data_date')->index();
                $table->text('query');
                $table->char('query_hash', 40)->index(); // sha1(query)
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('impressions')->default(0);
                $table->double('ctr', 8, 4)->default(0);
                $table->double('position', 8, 2)->default(0);
                $table->text('page')->nullable();
                $table->timestamps();

                $table->unique(['website_id', 'data_date', 'query_hash'], 'gsc_query_unique');
                $table->index(['website_id', 'data_date', 'clicks'], 'gsc_query_clicks_idx');
                $table->index(['website_id', 'data_date', 'impressions'], 'gsc_query_imp_idx');
            });
        }

        // 5. Top Landing Pages
        if (!Schema::hasTable('google_search_console_pages')) {
            Schema::create('google_search_console_pages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('site_url');
                $table->date('data_date')->index();
                $table->text('page');
                $table->char('page_hash', 40)->index(); // sha1(page)
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('impressions')->default(0);
                $table->double('ctr', 8, 4)->default(0);
                $table->double('position', 8, 2)->default(0);
                $table->timestamps();

                $table->unique(['website_id', 'data_date', 'page_hash'], 'gsc_pages_unique');
                $table->index(['website_id', 'data_date', 'clicks'], 'gsc_pages_clicks_idx');
            });
        }

        // 6. Country Performance
        if (!Schema::hasTable('google_search_console_countries')) {
            Schema::create('google_search_console_countries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('site_url');
                $table->date('data_date')->index();
                $table->string('country', 10)->index(); // 3-letter ISO code e.g. USA, KHM
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('impressions')->default(0);
                $table->double('ctr', 8, 4)->default(0);
                $table->double('position', 8, 2)->default(0);
                $table->timestamps();

                $table->unique(['website_id', 'data_date', 'country'], 'gsc_country_unique');
            });
        }

        // 7. Device Performance
        if (!Schema::hasTable('google_search_console_devices')) {
            Schema::create('google_search_console_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('site_url');
                $table->date('data_date')->index();
                $table->string('device', 30)->index(); // DESKTOP, MOBILE, TABLET
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('impressions')->default(0);
                $table->double('ctr', 8, 4)->default(0);
                $table->double('position', 8, 2)->default(0);
                $table->timestamps();

                $table->unique(['website_id', 'data_date', 'device'], 'gsc_device_unique');
            });
        }

        // 8. Synchronization Audit & History Logs
        if (!Schema::hasTable('google_search_console_sync_logs')) {
            Schema::create('google_search_console_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->nullable()->constrained('websites')->nullOnDelete();
                $table->string('site_url')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->string('status', 30)->default('running')->index(); // running, success, failed, partial
                $table->unsignedInteger('records_synced')->default(0);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->text('message')->nullable();
                $table->string('triggered_by', 30)->default('schedule'); // schedule, manual, oauth
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['started_at', 'status'], 'gsc_sync_log_idx');
            });
        }

        // 9. Website Assignees Pivot Table (Multi-member assignments)
        if (!Schema::hasTable('website_assignees')) {
            Schema::create('website_assignees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['website_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_assignees');
        Schema::dropIfExists('google_search_console_sync_logs');
        Schema::dropIfExists('google_search_console_devices');
        Schema::dropIfExists('google_search_console_countries');
        Schema::dropIfExists('google_search_console_pages');
        Schema::dropIfExists('google_search_console_search_queries');
        Schema::dropIfExists('google_search_console_daily_metrics');
        Schema::dropIfExists('google_search_console_sites');
        Schema::dropIfExists('google_search_console_accounts');
    }
};
