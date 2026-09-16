<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Google Sheet synchronisation tracking columns to website_follow_ups.
 *
 * google_sheet_status:
 *   skipped — not a blog_post type, or Google Sheet integration not configured
 *   pending  — queued but not yet processed
 *   synced   — successfully written to Google Sheet
 *   failed   — all retry attempts exhausted
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_follow_ups', function (Blueprint $table) {
            $table->string('blog_sheet_class')->nullable()->after('url')
                  ->comment('Google Sheet class number (1-7) for blog_post follow-ups');

            $table->string('google_sheet_status', 20)->default('skipped')->after('blog_sheet_class')
                  ->comment('skipped|pending|synced|failed');

            $table->unsignedInteger('google_sheet_row')->nullable()->after('google_sheet_status')
                  ->comment('Row number written in the Google Sheet');

            $table->timestamp('google_sheet_synced_at')->nullable()->after('google_sheet_row');

            $table->text('google_sheet_error')->nullable()->after('google_sheet_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('website_follow_ups', function (Blueprint $table) {
            $table->dropColumn([
                'blog_sheet_class',
                'google_sheet_status',
                'google_sheet_row',
                'google_sheet_synced_at',
                'google_sheet_error',
            ]);
        });
    }
};
