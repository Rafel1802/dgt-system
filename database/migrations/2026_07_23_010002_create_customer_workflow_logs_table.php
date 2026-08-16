<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_workflow_logs')) {
            Schema::create('customer_workflow_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('moved_by')->nullable();
                $table->string('feedback_category')->nullable();
                $table->string('from_queue')->nullable();
                $table->string('to_queue');
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->index('customer_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_workflow_logs');
    }
};
