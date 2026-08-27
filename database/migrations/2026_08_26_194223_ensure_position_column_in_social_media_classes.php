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
        if (!Schema::hasColumn('social_media_classes', 'position')) {
            Schema::table('social_media_classes', function (Blueprint $table) {
                $table->integer('position')->default(0)->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('social_media_classes', 'position')) {
            Schema::table('social_media_classes', function (Blueprint $table) {
                $table->dropColumn('position');
            });
        }
    }
};
