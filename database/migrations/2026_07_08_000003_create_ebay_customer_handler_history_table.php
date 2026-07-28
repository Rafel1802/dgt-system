<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebay_customer_handler_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebay_customer_record_id')->constrained('ebay_customer_records')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['ebay_customer_record_id', 'started_at'], 'ebay_handler_history_record_started_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_customer_handler_history');
    }
};
