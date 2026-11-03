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
                if (!Schema::hasColumn('website_posts', 'tag_keywords')) {
                    $table->text('tag_keywords')->nullable()->after('meta_description');
                }
                if (!Schema::hasColumn('website_posts', 'focus_keyword')) {
                    $table->string('focus_keyword', 255)->nullable()->after('tag_keywords');
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
                if (Schema::hasColumn('website_posts', 'tag_keywords')) {
                    $table->dropColumn('tag_keywords');
                }
                if (Schema::hasColumn('website_posts', 'focus_keyword')) {
                    $table->dropColumn('focus_keyword');
                }
            });
        }
    }
};
