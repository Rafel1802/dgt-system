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
        Schema::table('board_automations', function (Blueprint $table) {
            $table->foreignId('trigger_board_id')->nullable()->after('trigger_word')->constrained('boards')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_automations', function (Blueprint $table) {
            $table->dropForeign(['trigger_board_id']);
            $table->dropColumn('trigger_board_id');
        });
    }
};
