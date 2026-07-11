<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks whether the newly-assigned handler has explicitly confirmed
     * their assignment — surfaced as a pending-confirmation list in the
     * profile dropdown (separate from the notification bell), with a
     * Confirm action and a link to the customer record.
     */
    public function up(): void
    {
        Schema::table('ebay_customer_handler_history', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('ebay_customer_handler_history', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
    }
};
