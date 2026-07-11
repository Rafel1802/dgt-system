<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_customers', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->after('notes');
        });

        Schema::table('shipment_customers', function (Blueprint $table) {
            $table->dropColumn(['machine_sku', 'attachment_sku', 'product_description']);
        });
    }

    public function down(): void
    {
        Schema::table('shipment_customers', function (Blueprint $table) {
            $table->dropColumn('tracking_number');
            $table->string('machine_sku')->nullable();
            $table->string('attachment_sku')->nullable();
            $table->string('product_description')->nullable();
        });
    }
};
