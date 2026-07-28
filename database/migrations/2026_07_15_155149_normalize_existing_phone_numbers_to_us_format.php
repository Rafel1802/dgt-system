<?php

use App\Support\PhoneNumberFormatter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time backfill: reformat every existing phone number to the same US
 * display format the model mutators now enforce on write, so pages don't
 * show a mix of old raw formats and newly-normalized ones, and so
 * CrmCustomerMatchService's exact-match phone lookups work reliably against
 * historical data too. Not reversible — the original raw formatting varied
 * per row and isn't preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['customers', 'phone'],
            ['leads', 'client_phone'],
            ['ebay_customer_records', 'phone'],
            ['shipment_customers', 'recipient_phone'],
            ['logistics', 'recipient_phone'],
            ['logistics', 'driver_phone'],
            ['call_reports', 'phone'],
            ['call_requests', 'phone'],
            ['trucking_company_drivers', 'phone'],
            ['trucking_companies', 'phone'],
            ['users', 'phone'],
        ] as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $column) {
                    foreach ($rows as $row) {
                        $formatted = PhoneNumberFormatter::format($row->$column);
                        if ($formatted !== $row->$column) {
                            DB::table($table)->where('id', $row->id)->update([$column => $formatted]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Not reversible — original raw formatting per row isn't preserved.
    }
};
