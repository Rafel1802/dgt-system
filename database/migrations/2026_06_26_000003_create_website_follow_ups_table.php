<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('website_page'); // blog_post | indexed_page | website_page | other
            $table->string('title')->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('google_indexed')->default('pending'); // yes | no | pending
            $table->text('note')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('qc_status')->default('pending'); // pending | checked | approved
            $table->foreignId('qc_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qc_checked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_follow_ups');
    }
};
