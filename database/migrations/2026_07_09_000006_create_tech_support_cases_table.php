<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tech_support_cases', function (Blueprint $table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('order_id')->nullable();
            $table->string('status')->default('new_case');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('ebay_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_type', 'source_id'], 'tech_support_cases_source_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_support_cases');
    }
};
