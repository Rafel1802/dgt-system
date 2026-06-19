<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IP ban table to block malicious IPs from accessing the system.
     * Supports both permanent and time-limited bans.
     */
    public function up(): void
    {
        Schema::create('ip_bans', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('reason')->nullable();
            $table->foreignId('banned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('banned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();  // null = permanent ban
            $table->timestamps();

            $table->index(['ip_address', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_bans');
    }
};
