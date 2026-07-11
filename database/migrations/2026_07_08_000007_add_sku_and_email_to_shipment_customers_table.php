<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_customers', function (Blueprint $table) {
            if (! Schema::hasColumn('shipment_customers', 'recipient_email')) {
                $table->string('recipient_email')->nullable()->after('recipient_phone');
            }
            if (! Schema::hasColumn('shipment_customers', 'machine_sku')) {
                $table->string('machine_sku')->nullable()->after('product_description');
            }
            if (! Schema::hasColumn('shipment_customers', 'attachment_sku')) {
                $table->string('attachment_sku')->nullable()->after('machine_sku');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipment_customers', function (Blueprint $table) {
            $table->dropColumn(['recipient_email', 'machine_sku', 'attachment_sku']);
        });
    }
};
