<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->integer('maintenance_percent')->default(0)->after('progress_percent');
            $table->foreignId('qc_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('maintenance_percent');
            $table->timestamp('qc_approved_at')->nullable()->after('qc_approved_by');
            $table->timestamp('maintenance_started_at')->nullable()->after('qc_approved_at');
            $table->timestamp('maintenance_completed_at')->nullable()->after('maintenance_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['qc_approved_by']);
            $table->dropColumn([
                'maintenance_percent',
                'qc_approved_by',
                'qc_approved_at',
                'maintenance_started_at',
                'maintenance_completed_at',
            ]);
        });
    }
};
