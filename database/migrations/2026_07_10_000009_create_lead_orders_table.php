<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Lead's purchase history — mirrors EbayCustomerOrder/Item, which
     * already lets eBay customers accumulate a running list of orders.
     * Website leads previously had only one wipe-and-replace set of
     * products (LeadProduct), overwritten every time "Mark Successful"
     * ran again — this table lets a lead log a new order at any time
     * without losing previous ones.
     */
    public function up(): void
    {
        Schema::create('lead_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->date('order_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('lead_id');
        });

        Schema::table('lead_products', function (Blueprint $table) {
            $table->foreignId('lead_order_id')->nullable()->after('lead_id')
                ->constrained('lead_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lead_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_order_id');
        });

        Schema::dropIfExists('lead_orders');
    }
};
