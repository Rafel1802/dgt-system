<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a customer's Lead/eBay record re-enters Technical Support after
     * their previous case was resolved, the case is now reopened in place
     * (see TechSupportCaseService::createCaseFor) instead of spawning a
     * second case row for the same customer. occurrence_count tracks how
     * many separate technical issues this one case has now covered.
     */
    public function up(): void
    {
        Schema::table('tech_support_cases', function (Blueprint $table) {
            $table->unsignedInteger('occurrence_count')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tech_support_cases', function (Blueprint $table) {
            $table->dropColumn('occurrence_count');
        });
    }
};
