<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE BOARD-1: Boards
 *
 * A Board lives inside a Workspace and contains multiple Lists.
 * Think of it like a Trello board: each one has its own background,
 * its own permission set, and its own member list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Background: can be a Tailwind color class or hex, or an image path
            $table->string('background_type')->default('color'); // color | image | gradient
            $table->string('background_value')->default('#6366f1'); // hex / css class / path

            $table->enum('visibility', ['private', 'workspace', 'public'])->default('workspace');

            $table->boolean('is_starred')->default(false);   // quick-access favourite
            $table->boolean('is_archived')->default(false);  // soft-archive (hide from sidebar)
            $table->boolean('is_template')->default(false);  // used as a board template

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->integer('position')->default(0);         // order within workspace sidebar

            $table->timestamps();
            $table->softDeletes();

            $table->index('workspace_id');
            $table->index('created_by');
            $table->index(['workspace_id', 'is_archived']);
            $table->index('is_starred');
        });

        // ── Board Members ─────────────────────────────────────────────────────
        // A user can have a different role on each board they're on.
        // They must first be a workspace member to be added to a board.

        Schema::create('board_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'member', 'observer'])->default('member');
            $table->timestamps();

            $table->unique(['board_id', 'user_id']);
            $table->index('board_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_members');
        Schema::dropIfExists('boards');
    }
};
