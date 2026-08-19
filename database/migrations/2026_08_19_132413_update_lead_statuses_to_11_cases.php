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
        $mapping = [
            'new_inquiry'          => 'new_inquiry',
            'contacted'         => 'contacted',
            'lost_interest'              => 'lost_interest',
            'successful_lead'        => 'successful_lead',
            'pending_delivery'       => 'pending_delivery',
            'pending_delivery'  => 'pending_delivery',
            'loaded'        => 'loaded',
            'delivered'         => 'delivered',
            'technical_issues' => 'technical_issues',
            'technical_issues'  => 'technical_issues',
            'potential_return'     => 'potential_return',
            'approve_return'    => 'potential_return',
            'resolve'          => 'resolve',
        ];

        foreach ($mapping as $old => $new) {
            DB::table('leads')
                ->where('status', $old)
                ->update(['status' => $new]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('11_cases', function (Blueprint $table) {
            //
        });
    }
};
