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
            $table->foreignId('target_assignee_id')->nullable()->after('target_list_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_automations', function (Blueprint $table) {
            $table->dropForeign(['target_assignee_id']);
            $table->dropColumn('target_assignee_id');
        });
    }
};
