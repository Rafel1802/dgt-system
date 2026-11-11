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
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'poll_data')) {
                $table->json('poll_data')->nullable()->after('link_preview');
            }
            if (!Schema::hasColumn('chat_messages', 'forward_from')) {
                $table->json('forward_from')->nullable()->after('poll_data');
            }
            if (!Schema::hasColumn('chat_messages', 'is_unsent')) {
                $table->boolean('is_unsent')->default(false)->after('is_edited');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'poll_data')) {
                $table->dropColumn('poll_data');
            }
            if (Schema::hasColumn('chat_messages', 'forward_from')) {
                $table->dropColumn('forward_from');
            }
            if (Schema::hasColumn('chat_messages', 'is_unsent')) {
                $table->dropColumn('is_unsent');
            }
        });
    }
};
