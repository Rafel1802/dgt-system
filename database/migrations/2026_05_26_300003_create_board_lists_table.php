<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE BOARD-1: Lists (Columns)
 *
 * A List is a column inside a Board.
 * Example: Backlog | To Do | In Progress | Review | Done
 *
 * Cards sit inside Lists. Lists are ordered by 'position'.
 * A List can be archived (soft-hidden) without deleting its cards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_lists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('board_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->integer('position')->default(0);      // left-to-right order
            $table->boolean('is_archived')->default(false);

            // Colour accent on the list header (optional, Trello-style)
            $table->string('color', 7)->nullable();

            // Limit for WIP (Work In Progress) – 0 means no limit
            $table->unsignedSmallInteger('wip_limit')->default(0);

            $table->timestamps();

            $table->index('board_id');
            $table->index(['board_id', 'position']);
            $table->index(['board_id', 'is_archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_lists');
    }
};
