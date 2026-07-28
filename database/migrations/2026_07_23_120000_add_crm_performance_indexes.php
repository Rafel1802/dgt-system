<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reversible CRM performance indexes for hot list/filter/lookup paths.
 *
 * Bottlenecks measured via code audit of:
 * - Customer match lookups (phone/email equality)
 * - eBay customer list (tab_type + updated_at)
 * - Shipment customer queues (status + updated_at)
 * - Notification unread counts
 * - Lead staff filters
 *
 * Safe: additive only. Never renames/drops columns or tables.
 * Duplicate indexes are skipped when already present.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('customers', 'customers_phone_index', ['phone']);
        $this->addIndexIfMissing('customers', 'customers_created_at_index', ['created_at']);
        $this->addIndexIfMissing('customers', 'customers_status_assigned_to_index', ['status', 'assigned_to']);
        $this->addIndexIfMissing('customers', 'customers_shipment_delay_index', ['shipment_delay']);

        $this->addIndexIfMissing('leads', 'leads_client_email_index', ['client_email']);
        $this->addIndexIfMissing('leads', 'leads_client_phone_index', ['client_phone']);
        $this->addIndexIfMissing('leads', 'leads_handled_by_status_index', ['handled_by', 'status']);
        $this->addIndexIfMissing('leads', 'leads_received_at_index', ['received_at']);

        $this->addIndexIfMissing('ebay_customer_records', 'ebay_records_username_index', ['username']);
        $this->addIndexIfMissing('ebay_customer_records', 'ebay_records_email_index', ['email']);
        $this->addIndexIfMissing('ebay_customer_records', 'ebay_records_phone_index', ['phone']);
        $this->addIndexIfMissing('ebay_customer_records', 'ebay_records_tab_updated_index', ['tab_type', 'updated_at']);
        $this->addIndexIfMissing('ebay_customer_records', 'ebay_records_shipment_delay_index', ['shipment_delay']);

        $this->addIndexIfMissing('shipment_customers', 'shipment_customers_status_updated_index', ['status', 'updated_at']);
        $this->addIndexIfMissing('shipment_customers', 'shipment_customers_status_shipment_index', ['status', 'shipment_id']);

        $this->addIndexIfMissing('shipments', 'shipments_status_created_at_index', ['status', 'created_at']);

        $this->addIndexIfMissing('notifications', 'notifications_notifiable_read_index', ['notifiable_type', 'notifiable_id', 'read_at']);

        $this->addIndexIfMissing('cards', 'cards_board_id_is_archived_index', ['board_id', 'is_archived']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('customers', 'customers_phone_index');
        $this->dropIndexIfExists('customers', 'customers_created_at_index');
        $this->dropIndexIfExists('customers', 'customers_status_assigned_to_index');
        $this->dropIndexIfExists('customers', 'customers_shipment_delay_index');

        $this->dropIndexIfExists('leads', 'leads_client_email_index');
        $this->dropIndexIfExists('leads', 'leads_client_phone_index');
        $this->dropIndexIfExists('leads', 'leads_handled_by_status_index');
        $this->dropIndexIfExists('leads', 'leads_received_at_index');

        $this->dropIndexIfExists('ebay_customer_records', 'ebay_records_username_index');
        $this->dropIndexIfExists('ebay_customer_records', 'ebay_records_email_index');
        $this->dropIndexIfExists('ebay_customer_records', 'ebay_records_phone_index');
        $this->dropIndexIfExists('ebay_customer_records', 'ebay_records_tab_updated_index');
        $this->dropIndexIfExists('ebay_customer_records', 'ebay_records_shipment_delay_index');

        $this->dropIndexIfExists('shipment_customers', 'shipment_customers_status_updated_index');
        $this->dropIndexIfExists('shipment_customers', 'shipment_customers_status_shipment_index');

        $this->dropIndexIfExists('shipments', 'shipments_status_created_at_index');

        $this->dropIndexIfExists('notifications', 'notifications_notifiable_read_index');

        $this->dropIndexIfExists('cards', 'cards_board_id_is_archived_index');
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        // Skip if any required column is missing (older DBs / partial deploys).
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        // MySQL / MariaDB (Hostinger production)
        $database = DB::getDatabaseName();
        if (! $database) {
            return false;
        }

        $result = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return ((int) ($result->c ?? 0)) > 0;
    }
};
