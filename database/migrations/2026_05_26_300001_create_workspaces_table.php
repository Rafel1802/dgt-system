<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE BOARD-1: Workspaces
 *
 * A Workspace is the top-level container (like a Trello organisation).
 * Each workspace can have multiple boards, and each board belongs to one workspace.
 *
 * Visibility:
 *   private  – only members can see it
 *   team     – all authenticated users in the app can see it
 *   public   – anyone can view (read-only for non-members)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();           // URL-friendly name
            $table->text('description')->nullable();
            $table->string('logo')->nullable();         // stored in storage/app/public/workspaces/
            $table->string('color', 7)->default('#6366f1'); // hex brand color
            $table->enum('visibility', ['private', 'team', 'public'])->default('team');

            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('owner_id');
            $table->index('visibility');
        });

        // ── Workspace Members ─────────────────────────────────────────────────
        // Roles inside a workspace:
        //   owner  – full control, can delete workspace
        //   admin  – can manage boards and members
        //   member – can work on boards they have access to
        //   guest  – read-only across the workspace

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'admin', 'member', 'guest'])->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index('workspace_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
