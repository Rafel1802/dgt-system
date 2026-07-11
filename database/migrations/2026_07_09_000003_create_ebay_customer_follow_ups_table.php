<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebay_customer_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebay_customer_record_id')->constrained('ebay_customer_records')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes');
            $table->timestamp('contacted_at');
            $table->timestamps();

            $table->index(['ebay_customer_record_id', 'contacted_at'], 'ebay_follow_ups_record_contacted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_customer_follow_ups');
    }
};
