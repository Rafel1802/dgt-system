<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebay_stores', function (Blueprint $table) {
            if (! Schema::hasColumn('ebay_stores', 'total_sales')) {
                $table->decimal('total_sales', 12, 2)->default(0)->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ebay_stores', function (Blueprint $table) {
            $table->dropColumn('total_sales');
        });
    }
};
