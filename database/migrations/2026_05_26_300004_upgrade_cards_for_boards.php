<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE BOARD-1: Cards (upgraded)
 *
 * IMPORTANT: The old `cards` table used a `status` column as a pseudo-list.
 * We now add `board_list_id` (nullable for backwards compat) and `board_id`
 * so cards can belong to the new Workspace → Board → List hierarchy.
 *
 * Old cards (status-based) remain untouched.
 * New cards created through the Trello-style UI will have board_list_id set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            // Link to the new hierarchy  ── nullable so old rows don't break
            $table->foreignId('board_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('boards')
                  ->nullOnDelete();

            $table->foreignId('board_list_id')
                  ->nullable()
                  ->after('board_id')
                  ->constrained('board_lists')
                  ->nullOnDelete();

            // Card cover image
            $table->string('cover_image')->nullable()->after('rejection_reason');

            // Due date (time-aware), in addition to legacy 'deadline' (date-only)
            $table->timestamp('due_at')->nullable()->after('cover_image');
            $table->boolean('due_reminder_sent')->default(false)->after('due_at');

            // Card is archived (hidden but not deleted)
            $table->boolean('is_archived')->default(false)->after('due_reminder_sent');

            // Card watching (separate pivot table added later, this is a bool shortcut)
            $table->integer('watchers_count')->default(0)->after('is_archived');

            $table->index('board_id');
            $table->index('board_list_id');
            $table->index(['board_list_id', 'position']);
            $table->index('due_at');
            $table->index('is_archived');
        });

        // ── Labels (board-scoped coloured tags, like Trello labels) ──────────
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();           // optional name like "Urgent"
            $table->string('color', 7)->default('#ef4444'); // hex color
            $table->timestamps();

            $table->index('board_id');
        });

        // ── Card ↔ Label pivot ────────────────────────────────────────────────
        Schema::create('card_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['card_id', 'label_id']);
        });

        // ── Card watchers ─────────────────────────────────────────────────────
        Schema::create('card_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['card_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_watchers');
        Schema::dropIfExists('card_labels');
        Schema::dropIfExists('labels');

        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['board_list_id']);
            $table->dropForeign(['board_id']);
            $table->dropColumn([
                'board_id', 'board_list_id',
                'cover_image', 'due_at', 'due_reminder_sent',
                'is_archived', 'watchers_count',
            ]);
        });
    }
};
