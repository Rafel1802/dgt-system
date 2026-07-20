<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Process Trucking imports create ShipmentCustomer rows that aren't tied to
 * any Shipment yet — they're just a pending queue of trucking records staff
 * review and later group into shipments manually. shipment_id must be
 * nullable to allow that, and drops the cascadeOnDelete FK in favor of
 * nullOnDelete (deleting a Shipment shouldn't ever cascade-delete a
 * customer that's no longer actually linked to it).
 *
 * Uses raw SQL for the column MODIFY instead of Schema::table(...)->change()
 * — this app doesn't have doctrine/dbal installed (adding a new Composer
 * package can't reach production through the current deploy pipeline;
 * vendor/ is excluded from every rsync deploy), and ->change() requires it.
 *
 * Only MySQL needs this raw ALTER; SQLite (used by the test suite) has no
 * fixed-NOT-NULL constraint to relax here in the same way, so there's
 * nothing to run there — same guard used elsewhere for MySQL-only DDL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE shipment_customers DROP FOREIGN KEY shipment_customers_shipment_id_foreign');
        DB::statement('ALTER TABLE shipment_customers MODIFY shipment_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE shipment_customers ADD CONSTRAINT shipment_customers_shipment_id_foreign FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE shipment_customers DROP FOREIGN KEY shipment_customers_shipment_id_foreign');
        DB::statement('ALTER TABLE shipment_customers MODIFY shipment_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE shipment_customers ADD CONSTRAINT shipment_customers_shipment_id_foreign FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE');
    }
};
