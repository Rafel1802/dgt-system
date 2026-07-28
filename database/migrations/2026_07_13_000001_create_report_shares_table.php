<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A share link is a saved period filter (day/week/month or a custom
     * date range) behind an unguessable token — the public page re-runs the
     * report live against current data rather than freezing a snapshot, so
     * it always reflects up-to-date numbers for whoever holds the link.
     * Same convention as call_report_shares. `user_id` is set for a staff
     * profile share and null for a Team Report share.
     */
    public function up(): void
    {
        Schema::create('report_shares', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->enum('report_type', ['staff', 'team']);
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->json('filters')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_shares');
    }
};
