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
        if (Schema::hasTable('chat_user_settings') && !Schema::hasColumn('chat_user_settings', 'wallpaper_image')) {
            Schema::table('chat_user_settings', function (Blueprint $table) {
                $table->text('wallpaper_image')->nullable()->after('wallpaper');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('chat_user_settings') && Schema::hasColumn('chat_user_settings', 'wallpaper_image')) {
            Schema::table('chat_user_settings', function (Blueprint $table) {
                $table->dropColumn('wallpaper_image');
            });
        }
    }
};
