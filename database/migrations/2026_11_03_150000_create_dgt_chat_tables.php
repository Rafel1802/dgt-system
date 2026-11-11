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
        // 1. Conversations Table
        if (!Schema::hasTable('chat_conversations')) {
            Schema::create('chat_conversations', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['private', 'group'])->default('private');
                $table->string('title', 255)->nullable(); // For group chats
                $table->string('avatar', 500)->nullable(); // For group chats
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->unsignedBigInteger('last_message_id')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['type', 'last_message_at']);
                $table->index('last_message_at');
            });
        }

        // 2. Conversation Members Table
        if (!Schema::hasTable('chat_conversation_members')) {
            Schema::create('chat_conversation_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->enum('role', ['member', 'admin'])->default('member');
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_archived')->default(false);
                $table->boolean('is_muted')->default(false);
                $table->unsignedBigInteger('last_read_message_id')->nullable();
                $table->timestamp('last_read_at')->nullable();
                $table->string('custom_nickname', 100)->nullable();
                $table->string('custom_wallpaper', 255)->nullable();
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id'], 'chat_conv_user_unique');
                $table->index(['user_id', 'is_pinned']);
                $table->index(['user_id', 'is_archived']);
            });
        }

        // 3. Chat Messages Table
        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('reply_to_id')->nullable()->constrained('chat_messages')->onDelete('set null');
                $table->enum('type', ['text', 'image', 'video', 'document', 'voice', 'system'])->default('text');
                $table->longText('content')->nullable();
                $table->json('link_preview')->nullable(); // { url, title, description, image }
                $table->boolean('is_edited')->default(false);
                $table->timestamp('edited_at')->nullable();
                $table->boolean('is_pinned')->default(false);
                $table->timestamp('pinned_at')->nullable();
                $table->foreignId('pinned_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['conversation_id', 'id']);
                $table->index(['conversation_id', 'created_at']);
                $table->index('is_pinned');
            });
        }

        // 4. Message Attachments Table
        if (!Schema::hasTable('chat_message_attachments')) {
            Schema::create('chat_message_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('chat_messages')->onDelete('cascade');
                $table->string('file_path', 500);
                $table->string('file_name', 255);
                $table->string('file_type', 100);
                $table->unsignedBigInteger('file_size')->default(0); // in bytes
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('duration_seconds')->nullable();
                $table->string('thumbnail_path', 500)->nullable();
                $table->timestamps();

                $table->index('message_id');
            });
        }

        // 5. Message Reactions Table
        if (!Schema::hasTable('chat_message_reactions')) {
            Schema::create('chat_message_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained('chat_messages')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('reaction', 20); // e.g. 👍, ❤️, 🔥, 👏, 😂
                $table->timestamps();

                $table->unique(['message_id', 'user_id', 'reaction'], 'chat_reaction_unique');
                $table->index('message_id');
            });
        }

        // 6. User Chat Settings & Appearance Table
        if (!Schema::hasTable('chat_user_settings')) {
            Schema::create('chat_user_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->unique();
                $table->string('theme', 20)->default('system'); // light, dark, system
                $table->string('wallpaper', 255)->nullable();
                $table->string('bubble_color', 20)->default('#4f46e5');
                $table->string('font_size', 20)->default('medium'); // small, medium, large
                $table->boolean('notification_sound')->default(true);
                $table->string('sound_choice', 50)->default('chime');
                $table->boolean('enter_is_send')->default(true);
                $table->boolean('show_online_status')->default(true);
                $table->boolean('show_read_receipts')->default(true);
                $table->string('status_message', 255)->nullable()->default('Available');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_user_settings');
        Schema::dropIfExists('chat_message_reactions');
        Schema::dropIfExists('chat_message_attachments');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversation_members');
        Schema::dropIfExists('chat_conversations');
    }
};
