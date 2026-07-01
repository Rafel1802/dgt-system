<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->text('error_note')->nullable()->after('notes');
            $table->string('error_link', 1000)->nullable()->after('error_note');
            $table->timestamp('error_flagged_at')->nullable()->after('error_link');
            $table->foreignId('error_flagged_by')->nullable()->constrained('users')->nullOnDelete()->after('error_flagged_at');
            $table->integer('error_progress_percent')->default(0)->after('error_flagged_by');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['error_flagged_by']);
            $table->dropColumn([
                'error_note',
                'error_link',
                'error_flagged_at',
                'error_flagged_by',
                'error_progress_percent',
            ]);
        });
    }
};
