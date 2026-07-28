<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebay_customer_records', function (Blueprint $table) {
            if (! Schema::hasColumn('ebay_customer_records', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('tab_type')
                    ->constrained('customers')
                    ->nullOnDelete();

                $table->index('customer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ebay_customer_records', function (Blueprint $table) {
            if (Schema::hasColumn('ebay_customer_records', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });
    }
};
