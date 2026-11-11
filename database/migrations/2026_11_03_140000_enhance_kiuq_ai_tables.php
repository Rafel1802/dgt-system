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
        if (Schema::hasTable('ai_conversations')) {
            Schema::table('ai_conversations', function (Blueprint $table) {
                if (!Schema::hasColumn('ai_conversations', 'is_archived')) {
                    $table->boolean('is_archived')->default(false)->after('is_pinned');
                    $table->index('is_archived');
                }
            });
        }

        if (!Schema::hasTable('ai_voice_overs')) {
            Schema::create('ai_voice_overs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('title', 255)->default('Untitled Voice-Over');
                $table->text('script');
                $table->string('voice_name', 100)->default('Puck');
                $table->string('voice_gender', 20)->default('male');
                $table->string('style', 50)->default('professional');
                $table->decimal('speed', 3, 2)->default(1.00);
                $table->string('audio_path', 500);
                $table->integer('duration_seconds')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_voice_overs');

        if (Schema::hasTable('ai_conversations') && Schema::hasColumn('ai_conversations', 'is_archived')) {
            Schema::table('ai_conversations', function (Blueprint $table) {
                $table->dropColumn('is_archived');
            });
        }
    }
};
