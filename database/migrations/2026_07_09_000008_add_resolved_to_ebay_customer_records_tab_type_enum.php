<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "Resolved" status (EbayCustomerRecord::TAB_RESOLVED) was added at the
 * PHP level earlier, but tab_type is a native MySQL ENUM column whose
 * allowed-value list was never widened to match — so setting tab_type to
 * 'resolved' was silently rejected by MySQL (masked by SQLite-backed tests,
 * which don't enforce ENUM constraints). This adds it to the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only MySQL enforces a fixed ENUM value set; SQLite (used by the test
        // suite) stores this as plain TEXT with no such constraint, so there's
        // nothing to widen there — running the raw ALTER would just be invalid syntax.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE ebay_customer_records MODIFY COLUMN tab_type ENUM(
            'urgent_client','cancelation_client','technical_issues',
            'potential_negatives','negatives_feedbacks','new_order','resolved'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE ebay_customer_records MODIFY COLUMN tab_type ENUM(
            'urgent_client','cancelation_client','technical_issues',
            'potential_negatives','negatives_feedbacks','new_order'
        ) NOT NULL");
    }
};
