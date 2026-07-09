<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Shipment::STATUS_DELIVERED ('delivered') is renamed to STATUS_COMPLETE ('complete'); backfill existing rows. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('shipments')->where('status', 'delivered')->update(['status' => 'complete']);
    }

    public function down(): void
    {
        DB::table('shipments')->where('status', 'complete')->update(['status' => 'delivered']);
    }
};
