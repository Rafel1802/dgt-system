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
        // Add return_approved, loaded_for_return, loaded, logistic_delay
        // to the ENUM list of tab_type
        
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE ebay_customer_records MODIFY COLUMN tab_type ENUM(
                'urgent_client',
                'cancelation_client',
                'technical_issues',
                'potential_negatives',
                'negatives_feedbacks',
                'new_order',
                'return_received',
                'resolved',
                'tech_in_progress',
                'tech_potential_return',
                'tech_return_machine',
                'pickup_arranged',
                'return_approved',
                'loaded',
                'loaded_for_return',
                'logistic_delay'
            )");
        } else {
            // For sqlite testing, usually we just ignore enum alterations
            // since SQLite doesn't natively enforce ENUMs the same way.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
