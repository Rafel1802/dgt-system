<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function safeAddIndex(string $table, string|array $columns, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                $t->index($columns, $indexName);
            });
        } catch (\Throwable $e) {
            // Ignore if index already exists
        }
    }

    private function safeDropIndex(string $table, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        } catch (\Throwable $e) {
            // Ignore if index does not exist
        }
    }

    public function up(): void
    {
        // 1. Leads Table Indexes
        $this->safeAddIndex('leads', 'created_at', 'leads_created_at_idx');
        $this->safeAddIndex('leads', ['handled_by', 'created_at'], 'leads_handled_created_idx');

        // 2. eBay Customer Records Table Indexes
        $this->safeAddIndex('ebay_customer_records', 'created_at', 'ebay_records_created_at_idx');

        // 3. eBay Customer Handler History Table Indexes
        $this->safeAddIndex('ebay_customer_handler_history', ['user_id', 'started_at'], 'ebay_history_user_started_idx');

        // 4. Tech Support Cases Table Indexes
        $this->safeAddIndex('tech_support_cases', 'created_at', 'tech_support_created_at_idx');
        $this->safeAddIndex('tech_support_cases', 'resolved_at', 'tech_support_resolved_at_idx');
        $this->safeAddIndex('tech_support_cases', ['assigned_to', 'created_at'], 'tech_support_assigned_created_idx');
        $this->safeAddIndex('tech_support_cases', ['assigned_to', 'status', 'resolved_at'], 'tech_support_assigned_status_resolved_idx');

        // 5. Shipments Table Indexes
        $this->safeAddIndex('shipments', ['assigned_to', 'created_at'], 'shipments_assigned_created_idx');
        $this->safeAddIndex('shipments', ['assigned_to', 'status', 'updated_at'], 'shipments_assigned_status_updated_idx');

        // 6. Shipment Customers Table Indexes
        $this->safeAddIndex('shipment_customers', 'created_at', 'shipment_cust_created_at_idx');
        $this->safeAddIndex('shipment_customers', 'recipient_phone', 'shipment_cust_phone_idx');
        $this->safeAddIndex('shipment_customers', 'recipient_email', 'shipment_cust_email_idx');
        $this->safeAddIndex('shipment_customers', 'tracking_number', 'shipment_cust_tracking_idx');
        $this->safeAddIndex('shipment_customers', ['handled_by', 'created_at'], 'shipment_cust_handled_created_idx');
        $this->safeAddIndex('shipment_customers', ['handled_by', 'status', 'created_at'], 'shipment_cust_handled_status_created_idx');

        // 7. Clean up invalid/empty indexes on call_requests table
        $this->safeDropIndex('call_requests', 'call_requests_status_idx');
        $this->safeDropIndex('call_requests', 'call_requests_lead_id_idx');
    }

    public function down(): void
    {
        // Drop added indexes
        $this->safeDropIndex('leads', 'leads_created_at_idx');
        $this->safeDropIndex('leads', 'leads_handled_created_idx');

        $this->safeDropIndex('ebay_customer_records', 'ebay_records_created_at_idx');

        $this->safeDropIndex('ebay_customer_handler_history', 'ebay_history_user_started_idx');

        $this->safeDropIndex('tech_support_cases', 'tech_support_created_at_idx');
        $this->safeDropIndex('tech_support_cases', 'tech_support_resolved_at_idx');
        $this->safeDropIndex('tech_support_cases', 'tech_support_assigned_created_idx');
        $this->safeDropIndex('tech_support_cases', 'tech_support_assigned_status_resolved_idx');

        $this->safeDropIndex('shipments', 'shipments_assigned_created_idx');
        $this->safeDropIndex('shipments', 'shipments_assigned_status_updated_idx');

        $this->safeDropIndex('shipment_customers', 'shipment_cust_created_at_idx');
        $this->safeDropIndex('shipment_customers', 'shipment_cust_phone_idx');
        $this->safeDropIndex('shipment_customers', 'shipment_cust_email_idx');
        $this->safeDropIndex('shipment_customers', 'shipment_cust_tracking_idx');
        $this->safeDropIndex('shipment_customers', 'shipment_cust_handled_created_idx');
        $this->safeDropIndex('shipment_customers', 'shipment_cust_handled_status_created_idx');

        // Recreate empty/invalid indexes if reversing (though not strictly necessary as they target invalid columns)
        $this->safeAddIndex('call_requests', '', 'call_requests_status_idx');
        $this->safeAddIndex('call_requests', '', 'call_requests_lead_id_idx');
    }
};
