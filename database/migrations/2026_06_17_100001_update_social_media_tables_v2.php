<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the v1 tasks table
        Schema::dropIfExists('social_media_tasks');

        // Add sort_order to items
        Schema::table('social_media_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('social_media_items', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
