<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_media_class_id')
                  ->constrained('social_media_classes')
                  ->cascadeOnDelete();
            $table->date('date_from');       // Custom start date
            $table->date('date_to');         // Custom end date
            $table->string('file_path');     // relative path inside storage
            $table->string('original_name'); // original uploaded filename
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            // One analytics record per class per date range (replace on re-upload)
            $table->unique(['social_media_class_id', 'date_from', 'date_to'], 'sma_class_dates_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_analytics');
    }
};
