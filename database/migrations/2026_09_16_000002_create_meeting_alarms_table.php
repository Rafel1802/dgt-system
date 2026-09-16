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
        Schema::create('meeting_alarms', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('meeting_time');
            $table->string('meeting_link')->nullable();
            $table->string('sound')->default('melodic-chime.wav');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->integer('ring_duration')->default(8);
            $table->timestamps();

            $table->index(['meeting_time', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_alarms');
    }
};
