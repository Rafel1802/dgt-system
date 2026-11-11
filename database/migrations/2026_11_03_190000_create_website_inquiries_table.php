<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Centralized Website Inquiries Table
        if (!Schema::hasTable('website_inquiries')) {
            Schema::create('website_inquiries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->onDelete('cascade');
                
                // Form & Inquiry Classification
                $table->string('inquiry_type', 50)->default('general'); // quote_request, contact, product_question, pricing_availability, technical_support, sales, partnership, dealer_distributor, general, custom_form
                $table->string('status', 50)->default('new'); // new, unread, pending, assigned, in_progress, replied, follow_up_required, resolved, closed, spam
                $table->boolean('is_read')->default(false);
                $table->string('form_id', 100)->nullable();
                $table->string('form_name', 150)->nullable();

                // Customer Information
                $table->string('customer_name')->nullable();
                $table->string('customer_email')->nullable();
                $table->string('customer_phone', 60)->nullable();
                $table->string('customer_company')->nullable();

                // Inquiry Content
                $table->string('subject')->nullable();
                $table->text('message')->nullable();
                $table->string('product_service')->nullable();
                $table->text('source_url')->nullable();
                $table->json('custom_fields')->nullable();
                $table->json('attachments')->nullable();

                // Technical & Replay Metadata
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('referrer_url')->nullable();
                $table->string('idempotency_key', 64)->nullable();

                // Assignment & Workflow
                $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('last_replied_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('submitted_at')->useCurrent();

                $table->timestamps();
                $table->softDeletes();

                // Indexes for high performance querying
                $table->index(['website_id', 'created_at']);
                $table->index(['status', 'is_read']);
                $table->index(['inquiry_type', 'created_at']);
                $table->index('customer_email');
                $table->index('customer_phone');
                $table->index(['website_id', 'idempotency_key']);
            });
        }

        // 2. Inquiry Activity History & Internal Notes Table
        if (!Schema::hasTable('website_inquiry_activities')) {
            Schema::create('website_inquiry_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inquiry_id')->constrained('website_inquiries')->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->string('activity_type', 50); // created, status_changed, assigned, note_added, replied, retried
                $table->text('description');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['inquiry_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_inquiry_activities');
        Schema::dropIfExists('website_inquiries');
    }
};
