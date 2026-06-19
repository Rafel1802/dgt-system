<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_media_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_media_class_id')->constrained('social_media_classes')->cascadeOnDelete();
            $table->foreignId('social_media_item_id')->constrained('social_media_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('post_date');
            $table->text('post_url')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_checked')->default(false);
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            // 1 post record per user per item per date (unique)
            $table->unique(
                ['social_media_class_id', 'social_media_item_id', 'user_id', 'post_date'],
                'unique_social_post'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_posts');
    }
};
