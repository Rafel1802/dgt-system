<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deleting a Customer should remove everything linked to them across
     * every CRM domain, not just null out the foreign key. These columns
     * were nullOnDelete(), which left orphaned Leads, eBay records/orders,
     * Shipment links, and Tech Support cases behind whenever a customer
     * row was actually removed from the database.
     */
    public function up(): void
    {
        $this->recreate('leads', 'customer_id', 'customers', 'cascade');
        $this->recreate('ebay_offers', 'customer_id', 'customers', 'cascade');
        $this->recreate('ebay_orders', 'customer_id', 'customers', 'cascade');
        $this->recreate('ebay_customer_records', 'customer_id', 'customers', 'cascade');
        $this->recreate('shipment_customers', 'customer_id', 'customers', 'cascade');
        $this->recreate('tech_support_cases', 'customer_id', 'customers', 'cascade');
    }

    public function down(): void
    {
        $this->recreate('leads', 'customer_id', 'customers', 'set null');
        $this->recreate('ebay_offers', 'customer_id', 'customers', 'set null');
        $this->recreate('ebay_orders', 'customer_id', 'customers', 'set null');
        $this->recreate('ebay_customer_records', 'customer_id', 'customers', 'set null');
        $this->recreate('shipment_customers', 'customer_id', 'customers', 'set null');
        $this->recreate('tech_support_cases', 'customer_id', 'customers', 'set null');
    }

    private function recreate(string $table, string $column, string $references, string $onDelete): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $onDelete) {
            $blueprint->dropForeign([$column]);
            $blueprint->foreign($column)->references('id')->on($references)->onDelete($onDelete);
        });
    }
};
