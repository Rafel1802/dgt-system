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
        Schema::create('ai_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_language', 32)->default('auto');
            $table->string('detected_language', 32)->nullable();
            $table->string('target_language', 32);
            $table->string('translation_mode', 40)->default('standard');
            $table->longText('source_text');
            $table->longText('translated_text');
            $table->longText('extracted_ocr_text')->nullable();
            $table->json('advanced_options')->nullable();
            $table->string('provider_model', 100)->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'is_favorite']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_translations');
    }
};
