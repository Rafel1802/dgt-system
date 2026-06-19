<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_media_class_id')->constrained('social_media_classes')->cascadeOnDelete();
            $table->foreignId('social_media_item_id')->constrained('social_media_items')->cascadeOnDelete();
            $table->date('task_date');
            $table->text('post_url')->nullable();
            $table->boolean('is_posted')->default(false);
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->boolean('is_checked')->default(false);
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            // Prevent duplicate entries for same class + item + date
            $table->unique(['social_media_class_id', 'social_media_item_id', 'task_date'], 'unique_social_task');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_tasks');
    }
};
