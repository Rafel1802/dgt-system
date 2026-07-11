<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The CRM was seeded with AUD as the default currency; the business
     * actually operates in USD, so both the column default and every
     * existing row still sitting on the untouched default need to switch.
     * Rows a staff member deliberately set to AUD (or any other currency)
     * are left alone — this only backfills the default value itself.
     */
    private const TABLES = ['customers', 'products', 'ebay_offers', 'ebay_orders', 'logistics'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('currency', 3)->default('USD')->change();
            });

            DB::table($table)->where('currency', 'AUD')->update(['currency' => 'USD']);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('currency', 3)->default('AUD')->change();
            });
        }
    }
};
