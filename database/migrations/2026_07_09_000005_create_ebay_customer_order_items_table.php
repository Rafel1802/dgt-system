<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebay_customer_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebay_customer_order_id')->constrained('ebay_customer_orders')->cascadeOnDelete();
            $table->string('product_name');
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_customer_order_items');
    }
};
