<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Cross-department workflow queue (technical, logistics, sales,
            // follow_up), independent from the per-domain status machines
            // (WebsiteLeadStatus, EbayCustomerRecord tab_type, TechSupportCase,
            // ShipmentCustomer status) — set by CustomerController::routeToQueue().
            $table->string('current_queue')->nullable()->after('pipeline_stage');
            $table->index('current_queue');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['current_queue']);
            $table->dropColumn('current_queue');
        });
    }
};
