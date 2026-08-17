<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index('status', 'leads_status_idx');
            $table->index('handled_by', 'leads_handled_by_idx');
            $table->index('source', 'leads_source_idx');
            $table->index('received_at', 'leads_received_at_idx');
            $table->index('client_phone', 'leads_client_phone_idx');
            $table->index('client_email', 'leads_client_email_idx');
        });

        Schema::table('ebay_customer_records', function (Blueprint $table) {
            $table->index('tab_type', 'ebay_records_tab_type_idx');
            $table->index('ebay_store_id', 'ebay_records_store_id_idx');
            $table->index('email', 'ebay_records_email_idx');
            $table->index('phone', 'ebay_records_phone_idx');
            $table->index('username', 'ebay_records_username_idx');
            $table->index('updated_at', 'ebay_records_updated_at_idx');
        });

        Schema::table('shipment_customers', function (Blueprint $table) {
            $table->index('status', 'shipment_cust_status_idx');
            $table->index('shipment_id', 'shipment_cust_shipment_id_idx');
            $table->index('customer_id', 'shipment_cust_customer_id_idx');
            $table->index('handled_by', 'shipment_cust_handled_by_idx');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->index('status', 'shipments_status_idx');
            $table->index('trucking_company_id', 'shipments_trucking_co_id_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('source', 'customers_source_idx');
            $table->index('status', 'customers_status_idx');
            $table->index('email', 'customers_email_idx');
            $table->index('phone', 'customers_phone_idx');
            $table->index('assigned_to', 'customers_assigned_to_idx');
        });

        Schema::table('call_reports', function (Blueprint $table) {
            $table->index('lead_id', 'call_reports_lead_id_idx');
            $table->index('user_id', 'call_reports_user_id_idx');
            $table->index('report_date', 'call_reports_report_date_idx');
        });

        Schema::table('call_requests', function (Blueprint $table) {
            $table->index('status', 'call_requests_status_idx');
            $table->index('lead_id', 'call_requests_lead_id_idx');
        });

        Schema::table('tech_support_cases', function (Blueprint $table) {
            $table->index('status', 'tech_support_cases_status_idx');
            $table->index('assigned_to', 'tech_support_cases_assigned_to_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_status_idx');
            $table->dropIndex('leads_handled_by_idx');
            $table->dropIndex('leads_source_idx');
            $table->dropIndex('leads_received_at_idx');
            $table->dropIndex('leads_client_phone_idx');
            $table->dropIndex('leads_client_email_idx');
        });

        Schema::table('ebay_customer_records', function (Blueprint $table) {
            $table->dropIndex('ebay_records_tab_type_idx');
            $table->dropIndex('ebay_records_store_id_idx');
            $table->dropIndex('ebay_records_email_idx');
            $table->dropIndex('ebay_records_phone_idx');
            $table->dropIndex('ebay_records_username_idx');
            $table->dropIndex('ebay_records_updated_at_idx');
        });

        Schema::table('shipment_customers', function (Blueprint $table) {
            $table->dropIndex('shipment_cust_status_idx');
            $table->dropIndex('shipment_cust_shipment_id_idx');
            $table->dropIndex('shipment_cust_customer_id_idx');
            $table->dropIndex('shipment_cust_handled_by_idx');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_status_idx');
            $table->dropIndex('shipments_trucking_co_id_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_source_idx');
            $table->dropIndex('customers_status_idx');
            $table->dropIndex('customers_email_idx');
            $table->dropIndex('customers_phone_idx');
            $table->dropIndex('customers_assigned_to_idx');
        });

        Schema::table('call_reports', function (Blueprint $table) {
            $table->dropIndex('call_reports_lead_id_idx');
            $table->dropIndex('call_reports_user_id_idx');
            $table->dropIndex('call_reports_report_date_idx');
        });

        Schema::table('call_requests', function (Blueprint $table) {
            $table->dropIndex('call_requests_status_idx');
            $table->dropIndex('call_requests_lead_id_idx');
        });

        Schema::table('tech_support_cases', function (Blueprint $table) {
            $table->dropIndex('tech_support_cases_status_idx');
            $table->dropIndex('tech_support_cases_assigned_to_idx');
        });
    }
};
