<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Call Reports now capture the time of the call, not just the date —
     * `occurred_at` needs to hold a full datetime instead of a date-only
     * value. Existing rows keep their date with a midnight time component.
     */
    public function up(): void
    {
        Schema::table('call_reports', function (Blueprint $table) {
            $table->dateTime('occurred_at')->change();
        });
    }

    public function down(): void
    {
        Schema::table('call_reports', function (Blueprint $table) {
            $table->date('occurred_at')->change();
        });
    }
};
