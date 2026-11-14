<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cards', 'card_color')) {
            Schema::table('cards', function (Blueprint $table) {
                $table->string('card_color', 20)->nullable()->after('cover_image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cards', 'card_color')) {
            Schema::table('cards', function (Blueprint $table) {
                $table->dropColumn('card_color');
            });
        }
    }
};
