<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A share link is a saved filter set (search/date range/answered_by)
     * behind an unguessable token — the public page re-runs the filter live
     * against current data rather than freezing a snapshot, so it always
     * reflects up-to-date call reports for whoever holds the link.
     */
    public function up(): void
    {
        Schema::create('call_report_shares', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->json('filters')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_report_shares');
    }
};
