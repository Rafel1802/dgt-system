<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('due_at');
            $table->time('due_time')->nullable()->after('start_date');
            // reminder: minutes before due (e.g. 0=at due, 5, 10, 15, 30, 60, 1440, 2880)
            $table->unsignedSmallInteger('reminder')->nullable()->after('due_time');
            // recurring: none|daily|weekly|monthly|yearly
            $table->string('recurring', 20)->default('none')->after('reminder');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'due_time', 'reminder', 'recurring']);
        });
    }
};
