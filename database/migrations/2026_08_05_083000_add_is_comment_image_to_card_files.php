<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('card_files', 'is_comment_image')) {
            Schema::table('card_files', function (Blueprint $table) {
                $table->boolean('is_comment_image')->default(false)->after('size');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('card_files', 'is_comment_image')) {
            Schema::table('card_files', function (Blueprint $table) {
                $table->dropColumn('is_comment_image');
            });
        }
    }
};
