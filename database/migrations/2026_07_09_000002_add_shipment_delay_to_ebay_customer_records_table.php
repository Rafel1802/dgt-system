<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebay_customer_records', function (Blueprint $table) {
            if (! Schema::hasColumn('ebay_customer_records', 'shipment_delay')) {
                $table->boolean('shipment_delay')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ebay_customer_records', function (Blueprint $table) {
            $table->dropColumn('shipment_delay');
        });
    }
};
