<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Call Reports now require a phone number instead of a name — the name
     * column must become nullable so a phone-only submission can save.
     */
    public function up(): void
    {
        Schema::table('call_reports', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('call_reports', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};
