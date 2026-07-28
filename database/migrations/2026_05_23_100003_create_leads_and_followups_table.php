<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Website CRM Leads ─────────────────────────────────────────────────
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Customer link (nullable — can create lead before customer record)
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Staff accountability
            $table->foreignId('handled_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Client details (duplicated for standalone logging if customer_id is null)
            $table->string('client_name');
            $table->string('client_phone')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_whatsapp')->nullable();

            // Inquiry details
            $table->string('source'); // InquirySource enum
            $table->string('product_interested')->nullable(); // freeform or ProductCategory
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('inquiry_details')->nullable();
            $table->timestamp('received_at')->nullable()->useCurrent();

            // Pipeline status
            $table->string('status')->default('new_lead'); // WebsiteLeadStatus
            $table->string('temperature')->default('cold'); // LeadTemperature

            // Nurturing
            $table->text('follow_up_notes')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('next_action')->nullable();

            // Resolution
            $table->boolean('converted')->default(false);
            $table->timestamp('converted_at')->nullable();
            $table->text('lost_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('temperature');
            $table->index('source');
            $table->index('follow_up_date');
            $table->index('handled_by');
        });

        // ── Lead Follow-Ups Log ───────────────────────────────────────────────
        Schema::create('lead_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('notes');
            $table->string('next_action')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('temperature')->nullable(); // temperature at this follow-up
            $table->string('status_changed_to')->nullable(); // if status was changed
            $table->timestamp('contacted_at')->nullable()->useCurrent();
            $table->timestamps();

            $table->index(['lead_id', 'contacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_follow_ups');
        Schema::dropIfExists('leads');
    }
};
