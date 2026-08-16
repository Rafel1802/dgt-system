<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_follow_ups', function (Blueprint $table) {
            $table->index('created_at', 'website_follow_ups_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('website_follow_ups', function (Blueprint $table) {
            $table->dropIndex('website_follow_ups_created_at_index');
        });
    }
};
