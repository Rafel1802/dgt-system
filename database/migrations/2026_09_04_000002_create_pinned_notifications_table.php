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
        Schema::create('pinned_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('notification_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('actor_avatar', 1024)->nullable();
            $table->string('title')->nullable();
            $table->text('message');
            $table->string('link', 2048)->nullable();
            $table->string('board_name')->nullable();
            $table->string('card_title')->nullable();
            $table->unsignedBigInteger('card_id')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('pinned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('pinned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pinned_notifications');
    }
};
