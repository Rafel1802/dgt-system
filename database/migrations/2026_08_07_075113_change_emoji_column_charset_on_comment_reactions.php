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
        \DB::statement("DELETE FROM comment_reactions WHERE emoji = '?'");
        \DB::statement('ALTER TABLE comment_reactions MODIFY emoji VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comment_reactions', function (Blueprint $table) {
            //
        });
    }
};
