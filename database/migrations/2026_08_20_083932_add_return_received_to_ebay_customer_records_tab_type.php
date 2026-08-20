<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE ebay_customer_records MODIFY COLUMN tab_type ENUM('urgent_client', 'cancelation_client', 'technical_issues', 'potential_negatives', 'negatives_feedbacks', 'new_order', 'resolved', 'return_received') DEFAULT 'new_order'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE ebay_customer_records MODIFY COLUMN tab_type ENUM('urgent_client', 'cancelation_client', 'technical_issues', 'potential_negatives', 'negatives_feedbacks', 'new_order', 'resolved') DEFAULT 'new_order'");
    }
};
