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
        Schema::table('social_media_analytics', function (Blueprint $table) {
            $table->text('canva_link')->nullable()->after('original_name');
            $table->string('file_path')->nullable()->change();
            $table->string('original_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_media_analytics', function (Blueprint $table) {
            $table->dropColumn('canva_link');
            $table->string('file_path')->nullable(false)->change();
            $table->string('original_name')->nullable(false)->change();
        });
    }
};
