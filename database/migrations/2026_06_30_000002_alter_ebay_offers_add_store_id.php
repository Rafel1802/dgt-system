<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebay_offers', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('customer_id')->constrained('ebay_stores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ebay_offers', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
    }
};
