<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // eBay identity
            $table->string('ebay_username')->nullable()->after('website');
            $table->string('ebay_buyer_id')->nullable()->after('ebay_username');

            // CRM type classification
            $table->string('crm_type')->default('website')->after('status');
            // crm_type: website | ebay | logistic | general

            // ID/reference docs
            $table->string('id_number')->nullable()->after('postcode');

            // Extra contact
            $table->string('whatsapp')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'ebay_username', 'ebay_buyer_id',
                'crm_type', 'id_number', 'whatsapp',
            ]);
        });
    }
};
