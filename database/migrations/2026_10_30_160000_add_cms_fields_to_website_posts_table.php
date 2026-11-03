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
                if (!Schema::hasColumn('website_posts', 'content_html')) {
                    $table->longText('content_html')->nullable()->after('excerpt');
                }
                if (!Schema::hasColumn('website_posts', 'raw_content')) {
                    $table->longText('raw_content')->nullable()->after('content_html');
                }
                if (!Schema::hasColumn('website_posts', 'meta_title')) {
                    $table->string('meta_title', 500)->nullable()->after('title');
                }
                if (!Schema::hasColumn('website_posts', 'meta_description')) {
                    $table->text('meta_description')->nullable()->after('excerpt');
                }
                if (!Schema::hasColumn('website_posts', 'category')) {
                    $table->string('category', 255)->nullable()->after('author_name');
                }
                if (!Schema::hasColumn('website_posts', 'playlist')) {
                    $table->string('playlist', 255)->nullable()->after('category');
                }
                if (!Schema::hasColumn('website_posts', 'scheduled_at')) {
                    $table->dateTime('scheduled_at')->nullable()->after('published_at');
                }
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
                $columns = ['content_html', 'raw_content', 'meta_title', 'meta_description', 'category', 'playlist', 'scheduled_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('website_posts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
