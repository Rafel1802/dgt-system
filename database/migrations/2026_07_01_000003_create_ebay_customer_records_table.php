<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebay_customer_records', function (Blueprint $table) {
            $table->id();

            // Which of the 6 tabs this record belongs to
            $table->enum('tab_type', [
                'urgent_client',
                'cancelation_client',
                'technical_issues',
                'potential_negatives',
                'negatives_feedbacks',
                'new_order',
            ]);

            // Shared fields across all tabs
            $table->unsignedInteger('n')->nullable()->comment('Sequence / row number N');
            $table->string('username')->nullable();
            $table->string('buyer_name')->nullable();
            $table->text('informations')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('ebay_store_id')
                  ->nullable()
                  ->constrained('ebay_stores')
                  ->nullOnDelete();
            $table->string('order_id')->nullable();
            $table->text('summary')->nullable();
            $table->string('sku_number')->nullable();
            $table->date('date')->nullable();
            $table->date('order_date')->nullable();

            // Attention / required fields
            $table->text('attention_required')->nullable();
            $table->text('required_attentions')->nullable();

            // Negatives Feedbacks only
            $table->text('updates')->nullable();

            // Status
            $table->string('status')->default('open');

            // Audit
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->foreignId('updated_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tab_type');
            $table->index('ebay_store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_customer_records');
    }
};
