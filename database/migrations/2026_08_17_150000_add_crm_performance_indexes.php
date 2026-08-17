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
        $this->safeAddIndex('leads', 'status', 'leads_status_idx');
        $this->safeAddIndex('leads', 'handled_by', 'leads_handled_by_idx');
        $this->safeAddIndex('leads', 'source', 'leads_source_idx');
        $this->safeAddIndex('leads', 'received_at', 'leads_received_at_idx');
        $this->safeAddIndex('leads', 'client_phone', 'leads_client_phone_idx');
        $this->safeAddIndex('leads', 'client_email', 'leads_client_email_idx');

        $this->safeAddIndex('ebay_customer_records', 'tab_type', 'ebay_records_tab_type_idx');
        $this->safeAddIndex('ebay_customer_records', 'ebay_store_id', 'ebay_records_store_id_idx');
        $this->safeAddIndex('ebay_customer_records', 'email', 'ebay_records_email_idx');
        $this->safeAddIndex('ebay_customer_records', 'phone', 'ebay_records_phone_idx');
        $this->safeAddIndex('ebay_customer_records', 'username', 'ebay_records_username_idx');
        $this->safeAddIndex('ebay_customer_records', 'updated_at', 'ebay_records_updated_at_idx');

        $this->safeAddIndex('shipment_customers', 'status', 'shipment_cust_status_idx');
        $this->safeAddIndex('shipment_customers', 'shipment_id', 'shipment_cust_shipment_id_idx');
        $this->safeAddIndex('shipment_customers', 'customer_id', 'shipment_cust_customer_id_idx');
        $this->safeAddIndex('shipment_customers', 'handled_by', 'shipment_cust_handled_by_idx');

        $this->safeAddIndex('shipments', 'status', 'shipments_status_idx');
        $this->safeAddIndex('shipments', 'trucking_company_id', 'shipments_trucking_co_id_idx');

        $this->safeAddIndex('customers', 'source', 'customers_source_idx');
        $this->safeAddIndex('customers', 'status', 'customers_status_idx');
        $this->safeAddIndex('customers', 'email', 'customers_email_idx');
        $this->safeAddIndex('customers', 'phone', 'customers_phone_idx');
        $this->safeAddIndex('customers', 'assigned_to', 'customers_assigned_to_idx');

        $this->safeAddIndex('call_reports', 'answered_by', 'call_reports_answered_by_idx');
        $this->safeAddIndex('call_reports', 'created_by', 'call_reports_created_by_idx');

        $this->safeAddIndex('call_requests', 'status', 'call_requests_status_idx');
        $this->safeAddIndex('call_requests', 'lead_id', 'call_requests_lead_id_idx');

        $this->safeAddIndex('tech_support_cases', 'status', 'tech_support_cases_status_idx');
        $this->safeAddIndex('tech_support_cases', 'assigned_to', 'tech_support_cases_assigned_to_idx');
    }

    public function down(): void
    {
        $this->safeDropIndex('leads', 'leads_status_idx');
        $this->safeDropIndex('leads', 'leads_handled_by_idx');
        $this->safeDropIndex('leads', 'leads_source_idx');
        $this->safeDropIndex('leads', 'leads_received_at_idx');
        $this->safeDropIndex('leads', 'leads_client_phone_idx');
        $this->safeDropIndex('leads', 'leads_client_email_idx');

        $this->safeDropIndex('ebay_customer_records', 'ebay_records_tab_type_idx');
        $this->safeDropIndex('ebay_customer_records', 'ebay_records_store_id_idx');
        $this->safeDropIndex('ebay_customer_records', 'ebay_records_email_idx');
        $this->safeDropIndex('ebay_customer_records', 'ebay_records_phone_idx');
        $this->safeDropIndex('ebay_customer_records', 'ebay_records_username_idx');
        $this->safeDropIndex('ebay_customer_records', 'ebay_records_updated_at_idx');

        $this->safeDropIndex('shipment_customers', 'shipment_cust_status_idx');
        $this->safeDropIndex('shipment_customers', 'shipment_cust_shipment_id_idx');
        $this->safeDropIndex('shipment_customers', 'shipment_cust_customer_id_idx');
        $this->safeDropIndex('shipment_customers', 'shipment_cust_handled_by_idx');

        $this->safeDropIndex('shipments', 'shipments_status_idx');
        $this->safeDropIndex('shipments', 'shipments_trucking_co_id_idx');

        $this->safeDropIndex('customers', 'customers_source_idx');
        $this->safeDropIndex('customers', 'customers_status_idx');
        $this->safeDropIndex('customers', 'customers_email_idx');
        $this->safeDropIndex('customers', 'customers_phone_idx');
        $this->safeDropIndex('customers', 'customers_assigned_to_idx');

        $this->safeDropIndex('call_reports', 'call_reports_answered_by_idx');
        $this->safeDropIndex('call_reports', 'call_reports_created_by_idx');

        $this->safeDropIndex('call_requests', 'call_requests_status_idx');
        $this->safeDropIndex('call_requests', 'call_requests_lead_id_idx');

        $this->safeDropIndex('tech_support_cases', 'tech_support_cases_status_idx');
        $this->safeDropIndex('tech_support_cases', 'tech_support_cases_assigned_to_idx');
    }
};
