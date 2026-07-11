<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebay_customer_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebay_customer_record_id')->constrained('ebay_customer_records')->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['ebay_customer_record_id', 'changed_at'], 'ebay_status_history_record_changed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_customer_status_history');
    }
};
