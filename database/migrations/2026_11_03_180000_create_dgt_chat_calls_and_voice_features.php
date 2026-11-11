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
        // 1. Chat Calls Table
        if (!Schema::hasTable('chat_calls')) {
            Schema::create('chat_calls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
                $table->foreignId('caller_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
                $table->enum('status', [
                    'calling',      // Initial state
                    'ringing',      // Recipient notified
                    'connected',    // Call active
                    'ended',        // Completed normally
                    'declined',     // Rejected by receiver
                    'missed',       // Not answered in time
                    'cancelled',    // Caller hung up before answer
                    'busy'          // Receiver currently in another call
                ])->default('calling');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('answered_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->default(0);
                $table->string('end_reason', 50)->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'created_at']);
                $table->index(['receiver_id', 'status']);
                $table->index(['caller_id', 'status']);
            });
        }

        // 2. Chat Call Signals Table (Signaling Fallback & Persistence)
        if (!Schema::hasTable('chat_call_signals')) {
            Schema::create('chat_call_signals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('call_id')->constrained('chat_calls')->onDelete('cascade');
                $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
                $table->string('type', 50); // 'offer', 'answer', 'ice-candidate', 'status'
                $table->longText('payload');
                $table->boolean('is_read')->default(false);
                $table->timestamps();

                $table->index(['call_id', 'id']);
                $table->index(['receiver_id', 'is_read']);
            });
        }

        // 3. Add waveform_data to chat_message_attachments
        Schema::table('chat_message_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_message_attachments', 'waveform_data')) {
                $table->json('waveform_data')->nullable()->after('duration_seconds');
            }
        });

        // 4. Add call_id to chat_messages
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'call_id')) {
                $table->foreignId('call_id')->nullable()->after('is_unsent')->constrained('chat_calls')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'call_id')) {
                $table->dropForeign(['call_id']);
                $table->dropColumn('call_id');
            }
        });

        Schema::table('chat_message_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('chat_message_attachments', 'waveform_data')) {
                $table->dropColumn('waveform_data');
            }
        });

        Schema::dropIfExists('chat_call_signals');
        Schema::dropIfExists('chat_calls');
    }
};
