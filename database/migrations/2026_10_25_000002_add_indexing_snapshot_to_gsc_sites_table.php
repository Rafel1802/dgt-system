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
        if (Schema::hasTable('google_search_console_sites')) {
            Schema::table('google_search_console_sites', function (Blueprint $table) {
                if (!Schema::hasColumn('google_search_console_sites', 'gsc_indexed_pages')) {
                    $table->unsignedInteger('gsc_indexed_pages')->nullable()->after('sync_error');
                }
                if (!Schema::hasColumn('google_search_console_sites', 'gsc_not_indexed_pages')) {
                    $table->unsignedInteger('gsc_not_indexed_pages')->nullable()->after('gsc_indexed_pages');
                }
                if (!Schema::hasColumn('google_search_console_sites', 'gsc_indexing_updated_at')) {
                    $table->date('gsc_indexing_updated_at')->nullable()->after('gsc_not_indexed_pages');
                }
                if (!Schema::hasColumn('google_search_console_sites', 'gsc_indexing_reasons')) {
                    $table->json('gsc_indexing_reasons')->nullable()->after('gsc_indexing_updated_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('google_search_console_sites')) {
            Schema::table('google_search_console_sites', function (Blueprint $table) {
                $columns = ['gsc_indexed_pages', 'gsc_not_indexed_pages', 'gsc_indexing_updated_at', 'gsc_indexing_reasons'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('google_search_console_sites', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
