<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_class_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_media_class_id')->constrained('social_media_classes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['social_media_class_id', 'user_id'], 'unique_class_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_class_user');
    }
};
