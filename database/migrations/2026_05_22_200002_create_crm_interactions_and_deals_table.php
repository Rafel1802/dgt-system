<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer interaction log (calls, emails, meetings, notes)
        Schema::create('customer_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');           // call, email, meeting, note, whatsapp, demo
            $table->string('subject')->nullable();
            $table->text('content');
            $table->string('outcome')->nullable(); // positive, neutral, negative
            $table->timestamp('interacted_at');
            $table->integer('duration_minutes')->nullable(); // for calls/meetings
            $table->timestamps();

            $table->index(['customer_id', 'interacted_at']);
        });

        // Deals / opportunities in the pipeline
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('stage')->default('new_lead');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('AUD');
            $table->integer('probability')->default(10);  // 0-100%
            $table->date('expected_close_date')->nullable();
            $table->date('closed_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->json('product_interests')->nullable();
            $table->integer('position')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('stage');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
        Schema::dropIfExists('customer_interactions');
    }
};
