<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_progress_logs', function (Blueprint $table) {
            // 'build' = build progress log, 'maintenance' = maintenance progress log
            $table->string('type')->default('build')->after('website_id');
        });
    }

    public function down(): void
    {
        Schema::table('website_progress_logs', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
