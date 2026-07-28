<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core cards table — the heart of the Kanban board.
     * Status field tracks the approval workflow:
     *   todo → in_progress → review → approved/rejected → done
     */
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('title');
            $table->text('description')->nullable();

            // Categorisation
            $table->string('label');             // Video, Graphic, eBay Listing, etc.
            $table->string('sub_label')->nullable();  // Short Video, Banner, etc.
            $table->string('priority')->default('medium');  // low, medium, high, urgent

            // Workflow status
            $table->string('status')->default('todo');       // todo, in_progress, review, approved, rejected, done
            $table->integer('position')->default(0);         // ordering within column

            // Deadline
            $table->date('deadline')->nullable();

            // Approval workflow
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Meta
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('label');
            $table->index(['status', 'position']);
            $table->index('deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
