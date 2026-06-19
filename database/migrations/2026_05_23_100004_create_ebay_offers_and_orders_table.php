<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── eBay Offers (incoming purchase offers) ────────────────────────────
        Schema::create('ebay_offers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // eBay identifiers
            $table->string('ebay_username')->nullable();
            $table->string('ebay_message_id')->nullable();
            $table->string('ebay_item_id')->nullable();

            // Offer details
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->text('inquiry_notes')->nullable();
            $table->text('offer_details')->nullable();
            $table->decimal('offer_amount', 12, 2)->nullable();
            $table->decimal('final_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('AUD');
            $table->string('payment_status')->default('unpaid'); // unpaid|paid|partial|refunded

            // Status
            $table->string('status')->default('inquiry'); // EbayLeadStatus

            // Authorization (Hongling / Dennis review)
            $table->string('authorization_status')->default('pending'); // AuthorizationStatus
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->text('authorization_notes')->nullable();

            $table->timestamp('received_at')->nullable()->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('authorization_status');
            $table->index('ebay_username');
        });

        // ── eBay Orders (confirmed purchases) ────────────────────────────────
        Schema::create('ebay_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ebay_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // eBay identifiers
            $table->string('ebay_order_id')->nullable()->unique();
            $table->string('ebay_username')->nullable();

            // Order details
            $table->decimal('sale_amount', 12, 2);
            $table->string('currency', 3)->default('AUD');
            $table->string('payment_status')->default('unpaid');
            $table->date('payment_date')->nullable();

            $table->string('status')->default('order_confirmed'); // EbayLeadStatus
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('ebay_order_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_orders');
        Schema::dropIfExists('ebay_offers');
    }
};
