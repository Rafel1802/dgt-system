<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            // Handler / assigned user
            $table->foreignId('handled_by')->nullable()->after('logo_path')
                  ->constrained('users')->nullOnDelete();

            // Dates
            $table->date('start_date')->nullable()->after('handled_by');
            $table->date('deadline')->nullable()->after('start_date');
            $table->timestamp('completed_at')->nullable()->after('deadline');
            $table->timestamp('live_at')->nullable()->after('completed_at');

            // Status & Progress
            $table->string('status', 50)->default('Draft')->after('live_at');
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');

            // Notes
            $table->text('notes')->nullable()->after('progress_percent');

            // Audit fields
            $table->foreignId('created_by')->nullable()->after('notes')
                  ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')
                  ->constrained('users')->nullOnDelete();
            $table->boolean('is_archived')->default(false)->after('updated_by');

            // Soft deletes
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'start_date', 'deadline', 'completed_at', 'live_at',
                'status', 'progress_percent', 'notes', 'is_archived', 'deleted_at',
            ]);
        });
    }
};
