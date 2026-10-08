<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kpi_reviews')) {
            Schema::table('kpi_reviews', function (Blueprint $table) {
                if (!Schema::hasColumn('kpi_reviews', 'evaluation_date')) {
                    $table->date('evaluation_date')->nullable()->after('reviewed_at');
                }
                if (!Schema::hasColumn('kpi_reviews', 'uploaded_pdf_path')) {
                    $table->string('uploaded_pdf_path', 500)->nullable()->after('evaluation_date');
                }
                if (!Schema::hasColumn('kpi_reviews', 'google_drive_url')) {
                    $table->text('google_drive_url')->nullable()->after('uploaded_pdf_path');
                }
                if (!Schema::hasColumn('kpi_reviews', 'google_drive_file_id')) {
                    $table->string('google_drive_file_id', 255)->nullable()->after('google_drive_url');
                }
                if (!Schema::hasColumn('kpi_reviews', 'google_drive_synced_at')) {
                    $table->timestamp('google_drive_synced_at')->nullable()->after('google_drive_file_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kpi_reviews')) {
            Schema::table('kpi_reviews', function (Blueprint $table) {
                if (Schema::hasColumn('kpi_reviews', 'google_drive_url')) {
                    $table->dropColumn('google_drive_url');
                }
                if (Schema::hasColumn('kpi_reviews', 'google_drive_file_id')) {
                    $table->dropColumn('google_drive_file_id');
                }
                if (Schema::hasColumn('kpi_reviews', 'google_drive_synced_at')) {
                    $table->dropColumn('google_drive_synced_at');
                }
            });
        }
    }
};
