<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_calls')) {
            return;
        }

        Schema::table('chat_calls', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_calls', 'call_type')) {
                $table->string('call_type', 16)->default('voice')->after('receiver_id');
            }
            if (!Schema::hasColumn('chat_calls', 'metadata')) {
                $table->json('metadata')->nullable()->after('end_reason');
            }
        });

        Schema::table('chat_calls', function (Blueprint $table) {
            $table->index(['caller_id', 'created_at'], 'chat_calls_caller_created_idx');
            $table->index(['receiver_id', 'created_at'], 'chat_calls_receiver_created_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('chat_calls')) {
            return;
        }

        Schema::table('chat_calls', function (Blueprint $table) {
            try {
                $table->dropIndex('chat_calls_caller_created_idx');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex('chat_calls_receiver_created_idx');
            } catch (\Throwable $e) {
            }
            if (Schema::hasColumn('chat_calls', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('chat_calls', 'call_type')) {
                $table->dropColumn('call_type');
            }
        });
    }
};
