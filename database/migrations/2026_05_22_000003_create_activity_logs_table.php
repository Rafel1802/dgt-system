<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprehensive activity log for all user actions in the system.
     * Used for security auditing and compliance.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');                      // e.g. 'task.created', 'user.login'
            $table->string('module')->nullable();          // e.g. 'kanban', 'crm', 'auth'
            $table->text('description')->nullable();
            $table->nullableMorphs('subject');                // polymorphic: subject_type, subject_id (nullable)
            $table->json('properties')->nullable();        // before/after values
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('module');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
