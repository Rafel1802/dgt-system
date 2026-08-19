<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \App\Models\ShipmentCustomer::where('status', \App\Models\ShipmentCustomer::STATUS_PROBLEM)
            ->where('problem_occurrences', 0)
            ->update(['problem_occurrences' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
