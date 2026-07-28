<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops only `deals` — the Pipeline feature's table. The migration that
     * originally created it (2026_05_22_200002_...) also created the
     * unrelated `customer_interactions` table in the same file, so rolling
     * that migration back would take out customer interactions too. This
     * migration drops `deals` on its own instead.
     */
    public function up(): void
    {
        Schema::dropIfExists('deals');
    }

    public function down(): void
    {
        Schema::create('deals', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('stage')->default('new_lead');
            $table->decimal('value', 12, 2)->nullable();
            $table->string('currency', 3)->default('AUD');
            $table->unsignedTinyInteger('probability')->default(10);
            $table->date('expected_close_date')->nullable();
            $table->date('closed_at')->nullable();
            $table->text('lost_reason')->nullable();
            $table->json('product_interests')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('stage');
            $table->index('customer_id');
        });
    }
};
