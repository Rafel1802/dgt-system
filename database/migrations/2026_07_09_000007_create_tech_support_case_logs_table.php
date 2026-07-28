<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tech_support_case_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tech_support_case_id')->constrained('tech_support_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('follow_up');
            $table->text('note');
            $table->timestamps();

            $table->index(['tech_support_case_id', 'created_at'], 'tech_support_case_logs_case_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_support_case_logs');
    }
};
