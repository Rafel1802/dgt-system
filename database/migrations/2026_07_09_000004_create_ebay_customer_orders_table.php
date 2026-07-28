<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebay_customer_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebay_customer_record_id')->constrained('ebay_customer_records')->cascadeOnDelete();
            $table->string('order_id');
            $table->foreignId('ebay_store_id')->nullable()->constrained('ebay_stores')->nullOnDelete();
            $table->date('ordered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ebay_customer_record_id', 'ordered_at'], 'ebay_orders_record_ordered_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_customer_orders');
    }
};
