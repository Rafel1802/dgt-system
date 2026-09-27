<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. KPI Squads
        if (!Schema::hasTable('kpi_squads')) {
            Schema::create('kpi_squads', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->foreignId('lead_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. KPI Skills
        if (!Schema::hasTable('kpi_skills')) {
            Schema::create('kpi_skills', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('category')->default('Creative');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 3. KPI Periods
        if (!Schema::hasTable('kpi_periods')) {
            Schema::create('kpi_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->date('start_date');
                $table->date('end_date');
                $table->enum('status', ['Draft', 'Open', 'Locked', 'Archived'])->default('Open');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 4. KPI Templates
        if (!Schema::hasTable('kpi_templates')) {
            Schema::create('kpi_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('role_type');
                $table->foreignId('skill_id')->nullable()->constrained('kpi_skills')->nullOnDelete();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 5. KPI Template Items
        if (!Schema::hasTable('kpi_template_items')) {
            Schema::create('kpi_template_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('kpi_templates')->cascadeOnDelete();
                $table->string('name');
                $table->enum('pillar', ['productivity', 'quality', 'deadline', 'teamwork']);
                $table->decimal('weight', 5, 2);
                $table->decimal('target_value', 10, 2);
                $table->string('unit', 50)->default('units');
                $table->timestamps();
            });
        }

        // 6. KPI Assignments
        if (!Schema::hasTable('kpi_assignments')) {
            Schema::create('kpi_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('squad_id')->constrained('kpi_squads')->cascadeOnDelete();
                $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
                $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('kpi_templates')->nullOnDelete();
                $table->integer('target_deliverables')->default(20);
                $table->enum('status', ['Draft', 'Active', 'Submitted', 'Evaluated', 'Finalized'])->default('Active');
                $table->timestamps();

                $table->unique(['user_id', 'kpi_period_id']);
            });
        }

        // 7. KPI Assignment Items
        if (!Schema::hasTable('kpi_assignment_items')) {
            Schema::create('kpi_assignment_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assignment_id')->constrained('kpi_assignments')->cascadeOnDelete();
                $table->foreignId('template_item_id')->nullable()->constrained('kpi_template_items')->nullOnDelete();
                $table->string('name');
                $table->enum('pillar', ['productivity', 'quality', 'deadline', 'teamwork']);
                $table->decimal('weight', 5, 2);
                $table->decimal('target_value', 10, 2);
                $table->string('unit', 50)->default('units');
                $table->timestamps();
            });
        }

        // 8. KPI Tasks
        if (!Schema::hasTable('kpi_tasks')) {
            Schema::create('kpi_tasks', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->foreignId('squad_id')->constrained('kpi_squads')->cascadeOnDelete();
                $table->foreignId('assignee_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
                $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])->default('Medium');
                $table->enum('status', ['Assigned', 'In Progress', 'Submitted', 'Approved', 'Revision Required', 'Rejected'])->default('Assigned');
                $table->date('due_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->decimal('quality_score', 5, 2)->nullable();
                $table->text('feedback')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 9. KPI Task Submissions
        if (!Schema::hasTable('kpi_task_submissions')) {
            Schema::create('kpi_task_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('kpi_tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('evidence_url', 500);
                $table->text('notes')->nullable();
                $table->timestamp('submitted_at');
                $table->timestamps();
            });
        }

        // 10. KPI Reviews
        if (!Schema::hasTable('kpi_reviews')) {
            Schema::create('kpi_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kpi_assignment_id')->constrained('kpi_assignments')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
                $table->decimal('productivity_score', 5, 2)->default(0);
                $table->decimal('quality_score', 5, 2)->default(0);
                $table->decimal('deadline_score', 5, 2)->default(0);
                $table->decimal('teamwork_score', 5, 2)->default(0);
                $table->decimal('overall_kpi', 5, 2)->default(0);
                $table->enum('performance_band', ['Outstanding', 'Exceeds Expectations', 'Meets Expectations', 'Needs Improvement', 'Unsatisfactory'])->nullable();
                $table->enum('status', ['Draft', 'Submitted', 'Approved', 'Finalized'])->default('Draft');
                $table->text('manager_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'kpi_period_id']);
            });
        }

        // 11. KPI Review Items
        if (!Schema::hasTable('kpi_review_items')) {
            Schema::create('kpi_review_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_id')->constrained('kpi_reviews')->cascadeOnDelete();
                $table->foreignId('assignment_item_id')->nullable()->constrained('kpi_assignment_items')->nullOnDelete();
                $table->decimal('score', 5, 2);
                $table->decimal('weight', 5, 2);
                $table->decimal('weighted_score', 5, 2);
                $table->text('comments')->nullable();
                $table->timestamps();
            });
        }

        // 12. KPI Supervisor Reports
        if (!Schema::hasTable('kpi_supervisor_reports')) {
            Schema::create('kpi_supervisor_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('squad_id')->constrained('kpi_squads')->cascadeOnDelete();
                $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
                $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('overall_team_kpi', 5, 2)->default(0);
                $table->decimal('team_productivity', 5, 2)->default(0);
                $table->decimal('team_quality', 5, 2)->default(0);
                $table->decimal('team_deadline', 5, 2)->default(0);
                $table->enum('status', ['Draft', 'Submitted', 'Approved', 'Changes Requested', 'Finalized'])->default('Draft');
                $table->text('summary')->nullable();
                $table->text('strengths')->nullable();
                $table->text('improvements')->nullable();
                $table->text('supervisor_notes')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->unique(['squad_id', 'kpi_period_id']);
            });
        }

        // 13. KPI Reports
        if (!Schema::hasTable('kpi_reports')) {
            Schema::create('kpi_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
                $table->foreignId('squad_id')->nullable()->constrained('kpi_squads')->nullOnDelete();
                $table->enum('type', ['pdf', 'excel']);
                $table->string('file_name');
                $table->string('file_path');
                $table->string('google_drive_file_id')->nullable();
                $table->string('google_drive_link')->nullable();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        // 14. KPI Audit Logs
        if (!Schema::hasTable('kpi_audit_logs')) {
            Schema::create('kpi_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_audit_logs');
        Schema::dropIfExists('kpi_reports');
        Schema::dropIfExists('kpi_supervisor_reports');
        Schema::dropIfExists('kpi_review_items');
        Schema::dropIfExists('kpi_reviews');
        Schema::dropIfExists('kpi_task_submissions');
        Schema::dropIfExists('kpi_tasks');
        Schema::dropIfExists('kpi_assignment_items');
        Schema::dropIfExists('kpi_assignments');
        Schema::dropIfExists('kpi_template_items');
        Schema::dropIfExists('kpi_templates');
        Schema::dropIfExists('kpi_periods');
        Schema::dropIfExists('kpi_skills');
        Schema::dropIfExists('kpi_squads');
    }
};
