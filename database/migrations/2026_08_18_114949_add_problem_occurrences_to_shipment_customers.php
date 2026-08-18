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
        Schema::table('shipment_customers', function (Blueprint $table) {
            $table->integer('problem_occurrences')->default(0)->after('status');
        });

        // Initialize any current problems to 1 occurrence
        DB::table('shipment_customers')
            ->where('status', 'problem')
            ->update(['problem_occurrences' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_customers', function (Blueprint $table) {
            //
        });
    }
};
