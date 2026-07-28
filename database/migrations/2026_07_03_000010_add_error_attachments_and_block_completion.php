<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            if (! Schema::hasColumn('websites', 'error_attachment_path')) {
                $table->string('error_attachment_path')->nullable()->after('error_link');
            }
            if (! Schema::hasColumn('websites', 'error_attachment_name')) {
                $table->string('error_attachment_name')->nullable()->after('error_attachment_path');
            }
        });

        Schema::table('cards', function (Blueprint $table) {
            if (! Schema::hasColumn('cards', 'block_completed_at')) {
                $table->timestamp('block_completed_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('cards', 'block_completed_by')) {
                $table->foreignId('block_completed_by')->nullable()->after('block_completed_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            if (Schema::hasColumn('cards', 'block_completed_by')) {
                $table->dropConstrainedForeignId('block_completed_by');
            }
            if (Schema::hasColumn('cards', 'block_completed_at')) {
                $table->dropColumn('block_completed_at');
            }
        });

        Schema::table('websites', function (Blueprint $table) {
            if (Schema::hasColumn('websites', 'error_attachment_name')) {
                $table->dropColumn('error_attachment_name');
            }
            if (Schema::hasColumn('websites', 'error_attachment_path')) {
                $table->dropColumn('error_attachment_path');
            }
        });
    }
};
