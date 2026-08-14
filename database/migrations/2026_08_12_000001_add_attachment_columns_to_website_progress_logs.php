<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_progress_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('website_progress_logs', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('note');
            }
            if (! Schema::hasColumn('website_progress_logs', 'attachment_name')) {
                $table->string('attachment_name')->nullable()->after('attachment_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('website_progress_logs', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });
    }
};
