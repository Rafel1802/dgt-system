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
        // 1. AI Conversations Table
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title', 255)->default('New Chat');
            $table->string('mode', 50)->default('general'); // general, blog, image, seo, task, qc, report
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'updated_at']);
            $table->index('mode');
        });

        // 2. AI Messages Table
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('role', ['user', 'model', 'system'])->default('user');
            $table->longText('content');
            
            // Action confirmation & preview data
            $table->string('action_type', 100)->nullable(); // e.g. publish_blog_preview, create_task_preview, report_preview, image_preview
            $table->json('action_data')->nullable(); // structured payload for preview & confirmation
            $table->string('action_status', 50)->nullable(); // pending_confirmation, confirmed, cancelled, executed, rejected
            
            $table->json('attachments')->nullable(); // images, uploaded files, generated asset URLs
            $table->integer('tokens_used')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index('action_status');
        });

        // 3. AI Generated Reports Table
        Schema::create('ai_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title', 255);
            $table->string('category', 100)->default('monthly_production'); // monthly_production, blog_publishing, task_completion, website_qc, employee_performance
            $table->date('date_from');
            $table->date('date_to');
            $table->json('filters')->nullable(); // department, user_ids, website_ids, etc.
            $table->json('stats_data'); // Real calculated DB metrics (blogs, cards, QC, tasks)
            $table->longText('analysis_text')->nullable(); // Gemini AI insights & executive summary
            $table->string('file_pdf_path', 500)->nullable();
            $table->string('file_csv_path', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('category');
        });

        // 4. AI Audit Logs Table
        Schema::create('ai_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action', 100);
            $table->string('target_type', 100)->nullable(); // WebsitePost, Card, Website, Report, etc.
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('status', 50)->default('success'); // success, blocked, failed
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
        Schema::dropIfExists('ai_reports');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
