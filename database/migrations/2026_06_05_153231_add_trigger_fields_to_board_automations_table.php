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
            $table->string('trigger_type')->default('keyword')->after('board_id'); // keyword, list
            $table->string('trigger_word')->nullable()->change();
            $table->foreignId('trigger_list_id')->nullable()->after('trigger_word')->constrained('board_lists')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_automations', function (Blueprint $table) {
            $table->dropForeign(['trigger_list_id']);
            $table->dropColumn(['trigger_type', 'trigger_list_id']);
            $table->string('trigger_word')->nullable(false)->change();
        });
    }
};
