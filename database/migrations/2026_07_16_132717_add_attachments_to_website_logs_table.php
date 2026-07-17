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
        Schema::table('website_progress_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('website_progress_logs', 'attachment_path')) {
                $table->string('attachment_path')->nullable();
            }
            if (!Schema::hasColumn('website_progress_logs', 'attachment_name')) {
                $table->string('attachment_name')->nullable();
            }
        });

        Schema::table('website_maintenance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('website_maintenance_logs', 'attachment_path')) {
                $table->string('attachment_path')->nullable();
            }
            if (!Schema::hasColumn('website_maintenance_logs', 'attachment_name')) {
                $table->string('attachment_name')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_progress_logs', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });

        Schema::table('website_maintenance_logs', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });
    }
};
