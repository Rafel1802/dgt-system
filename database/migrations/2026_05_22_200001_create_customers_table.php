<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->string('website')->nullable();
            $table->string('country')->nullable()->default('AU');
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('postcode', 20)->nullable();

            // CRM classification
            $table->string('status')->default('lead');         // lead, prospect, active, inactive, lost
            $table->string('source')->nullable();              // website, referral, ebay, social_media, cold_call, etc.
            $table->string('pipeline_stage')->default('new_lead'); // new_lead, contacted, qualified, proposal_sent, negotiating, won, lost

            // Product interest (JSON array of strings)
            $table->json('product_interests')->nullable();

            // Financial
            $table->decimal('lifetime_value', 12, 2)->default(0);
            $table->string('currency', 3)->default('AUD');

            // Bought status
            $table->boolean('has_purchased')->default(false);
            $table->date('first_purchase_date')->nullable();
            $table->date('last_purchase_date')->nullable();
            $table->unsignedInteger('total_orders')->default(0);

            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Tags & notes
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->string('avatar')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('pipeline_stage');
            $table->index('source');
            $table->index('assigned_to');
            $table->index(['has_purchased', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
