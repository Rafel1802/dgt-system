<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_reports')) {
            Schema::create('production_reports', function (Blueprint $table) {
                $table->id();
                $table->string('report_code', 80)->unique();
                $table->string('report_type', 20); // daily, weekly, monthly
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('team_code', 20)->nullable(); // 'A', 'B'
                $table->string('team_name', 150)->nullable();
                $table->date('reporting_date')->nullable();
                $table->date('reporting_start_date');
                $table->date('reporting_end_date');
                $table->unsignedTinyInteger('reporting_month')->nullable();
                $table->unsignedSmallInteger('reporting_year');
                $table->string('project_category', 120)->nullable();
                $table->string('title', 255);
                $table->longText('executive_summary')->nullable();
                $table->longText('tasks_completed')->nullable();
                $table->longText('work_in_progress')->nullable();
                $table->longText('pending_tasks')->nullable();
                $table->longText('blockers')->nullable();
                $table->longText('achievements')->nullable();
                $table->json('deliverable_links')->nullable();
                $table->longText('next_priorities')->nullable();
                $table->longText('notes')->nullable();
                $table->unsignedTinyInteger('performance_rating')->nullable(); // 1..5
                $table->string('performance_rating_label', 80)->nullable();
                $table->longText('head_comments')->nullable();
                $table->longText('supervisor_comments')->nullable();
                $table->json('member_ratings_json')->nullable();
                $table->json('compiled_summary_json')->nullable();
                $table->string('status', 40)->default('draft'); // not_started, draft, submitted, submitted_late, returned_for_correction, approved
                $table->boolean('is_late')->default(false);
                $table->timestamp('deadline_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('returned_reason')->nullable();
                $table->string('pptx_path', 500)->nullable();
                $table->string('pdf_path', 500)->nullable();
                $table->string('csv_path', 500)->nullable();
                $table->timestamp('last_exported_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['report_type', 'team_code', 'reporting_start_date'], 'idx_pr_type_team_start');
                $table->index(['user_id', 'report_type', 'reporting_date'], 'idx_pr_user_type_date');
                $table->index(['reporting_year', 'reporting_month', 'report_type'], 'idx_pr_year_month_type');
                $table->index(['status', 'report_type'], 'idx_pr_status_type');
            });
        }

        if (!Schema::hasTable('production_report_items')) {
            Schema::create('production_report_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_report_id')->constrained('production_reports')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('task_title', 255);
                $table->text('task_description')->nullable();
                $table->string('task_category', 100)->nullable();
                $table->decimal('quantity', 10, 2)->default(1);
                $table->string('unit', 50)->default('items');
                $table->string('status', 40)->default('completed'); // completed, in_progress, pending, blocked
                $table->date('completion_date')->nullable();
                $table->string('external_link', 1000)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['production_report_id', 'status'], 'idx_pri_report_status');
                $table->index(['user_id', 'task_category'], 'idx_pri_user_cat');
            });
        }

        if (!Schema::hasTable('production_report_media')) {
            Schema::create('production_report_media', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_report_id')->constrained('production_reports')->cascadeOnDelete();
                $table->unsignedBigInteger('source_daily_report_id')->nullable();
                $table->unsignedBigInteger('uploaded_by');
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->string('team_code', 20)->nullable();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('media_type', 40)->default('poster'); // poster, graphic, video, screenshot, document, external_link
                $table->string('project_category', 120)->nullable();
                $table->date('production_date')->nullable();
                $table->string('disk', 40)->default('local');
                $table->string('file_path', 500)->nullable();
                $table->string('thumbnail_path', 500)->nullable();
                $table->string('original_filename', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('external_url', 1000)->nullable();
                $table->string('platform', 80)->nullable();
                $table->string('duration_label', 40)->nullable();
                $table->boolean('is_highlighted')->default(false);
                $table->string('highlight_category', 40)->nullable(); // best_poster, best_video, top_output
                $table->unsignedInteger('highlight_order')->default(0);
                $table->timestamps();

                $table->index(['production_report_id', 'media_type'], 'idx_prm_report_type');
                $table->index(['team_code', 'media_type', 'production_date'], 'idx_prm_team_type_date');
                $table->index(['created_by_user_id', 'is_highlighted'], 'idx_prm_creator_highlight');
            });
        }

        if (!Schema::hasTable('production_report_revisions')) {
            Schema::create('production_report_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_report_id')->constrained('production_reports')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedInteger('revision_number')->default(1);
                $table->string('action', 60); // created, draft_saved, submitted, submitted_late, reopened, returned_for_correction, approved, updated_after_reopen, compiled
                $table->string('previous_status', 40)->nullable();
                $table->string('new_status', 40)->nullable();
                $table->longText('snapshot_json')->nullable();
                $table->text('notes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['production_report_id', 'revision_number'], 'idx_prr_report_rev');
            });
        }

        if (!Schema::hasTable('production_report_deadlines')) {
            Schema::create('production_report_deadlines', function (Blueprint $table) {
                $table->id();
                $table->string('scope_key', 50)->unique(); // 'global'
                $table->string('timezone', 60)->default('Asia/Phnom_Penh');
                $table->string('daily_deadline_time', 10)->default('17:00');
                $table->string('daily_reminder_time', 10)->default('16:45');
                $table->string('weekly_deadline_day', 20)->default('Saturday');
                $table->string('weekly_deadline_time', 10)->default('08:00');
                $table->string('weekly_reminder_day', 20)->default('Saturday');
                $table->string('weekly_reminder_time', 10)->default('07:00');
                $table->string('weekly_prep_day', 20)->default('Friday');
                $table->string('weekly_prep_time', 10)->default('16:00');
                $table->string('weekly_period_start_day', 20)->default('Monday');
                $table->string('weekly_period_end_day', 20)->default('Friday');
                $table->unsignedTinyInteger('monthly_deadline_day')->default(28);
                $table->string('monthly_deadline_mode', 30)->default('last_day_of_month'); // last_day_of_month, specific_day, day_of_next_month
                $table->string('monthly_deadline_time', 10)->default('17:00');
                $table->boolean('allow_head_daily_submission')->default(false);
                $table->boolean('allow_head_monthly_submission')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('production_report_team_mappings')) {
            Schema::create('production_report_team_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('team_code', 20); // 'A', 'B', 'ALL'
                $table->string('team_name', 150);
                $table->string('role_in_reporting', 40); // 'supervisor', 'head', 'member'
                $table->string('position_title', 120)->nullable();
                $table->boolean('can_submit_daily')->default(true);
                $table->boolean('can_submit_weekly')->default(false);
                $table->boolean('can_submit_monthly')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->timestamps();

                $table->unique(['user_id'], 'uniq_prtm_user');
                $table->index(['team_code', 'role_in_reporting', 'is_active'], 'idx_prtm_team_role');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_report_team_mappings');
        Schema::dropIfExists('production_report_deadlines');
        Schema::dropIfExists('production_report_revisions');
        Schema::dropIfExists('production_report_media');
        Schema::dropIfExists('production_report_items');
        Schema::dropIfExists('production_reports');
    }
};

