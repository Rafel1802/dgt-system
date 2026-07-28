<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_requests', function (Blueprint $table) {
            $table->id();
            // Polymorphic origin (e.g. a Lead or an EbayCustomerRecord flagged from Tech Support)
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('note');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('fulfilled')->default(false);
            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index('fulfilled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_requests');
    }
};
