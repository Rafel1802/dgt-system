<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Logistics Records ─────────────────────────────────────────────────
        Schema::create('logistics', function (Blueprint $table) {
            $table->id();

            // Links to order systems
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ebay_order_id')->nullable()->constrained('ebay_orders')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Order reference
            $table->string('order_id')->nullable(); // internal or eBay order ID
            $table->text('product_description')->nullable();

            // Delivery details
            $table->text('shipping_address');
            $table->string('recipient_name');
            $table->string('recipient_phone');

            // Truck & driver
            $table->string('truck_company')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();

            // Financial
            $table->decimal('shipping_budget', 10, 2)->nullable();
            $table->decimal('final_shipping_cost', 10, 2)->nullable();
            $table->string('currency', 3)->default('AUD');

            // Dates
            $table->timestamp('pickup_datetime')->nullable();
            $table->date('estimated_arrival')->nullable();
            $table->date('actual_arrival')->nullable();

            // Tracking
            $table->string('tracking_number')->nullable();
            $table->string('status')->default('order_confirmed'); // LogisticStatus

            // Delivery confirmation
            $table->string('delivery_proof')->nullable(); // file path
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('tracking_number');
            $table->index('order_id');
            $table->index('assigned_to');
        });

        // ── Logistic Status Updates (timeline log) ────────────────────────────
        Schema::create('logistic_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // LogisticStatus value
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable(); // proof file
            $table->timestamp('occurred_at')->nullable()->useCurrent();
            $table->timestamps();

            $table->index(['logistic_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistic_updates');
        Schema::dropIfExists('logistics');
    }
};
