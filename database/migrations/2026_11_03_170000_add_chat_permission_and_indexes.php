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
        // 1. Add can_use_chat to users table
        if (!Schema::hasColumn('users', 'can_use_chat')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('can_use_chat')->default(true)->after('is_active')->index();
            });
        }

        // 2. Add performance indexes on chat tables if not already present
        Schema::table('chat_messages', function (Blueprint $table) {
            // Composite index for fast conversation pagination & sync
            if (!Schema::hasIndex('chat_messages', 'chat_messages_conv_id_id_idx')) {
                $table->index(['conversation_id', 'id'], 'chat_messages_conv_id_id_idx');
            }
        });

        Schema::table('chat_conversation_members', function (Blueprint $table) {
            // Composite index for fast user unread queries
            if (!Schema::hasIndex('chat_conversation_members', 'chat_members_user_conv_idx')) {
                $table->index(['user_id', 'conversation_id'], 'chat_members_user_conv_idx');
            }
        });

        Schema::table('chat_message_attachments', function (Blueprint $table) {
            // Index for WebP conversion query (older than 5 mins by mime)
            if (!Schema::hasIndex('chat_message_attachments', 'chat_att_type_created_idx')) {
                $table->index(['file_type', 'created_at'], 'chat_att_type_created_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'can_use_chat')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('can_use_chat');
            });
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('chat_messages_conv_id_id_idx');
        });

        Schema::table('chat_conversation_members', function (Blueprint $table) {
            $table->dropIndex('chat_members_user_conv_idx');
        });

        Schema::table('chat_message_attachments', function (Blueprint $table) {
            $table->dropIndex('chat_att_type_created_idx');
        });
    }
};
