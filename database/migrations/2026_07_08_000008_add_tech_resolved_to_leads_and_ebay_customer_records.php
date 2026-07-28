<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'tech_resolved')) {
                $table->boolean('tech_resolved')->default(false)->after('status');
            }
            if (! Schema::hasColumn('leads', 'tech_resolved_at')) {
                $table->date('tech_resolved_at')->nullable()->after('tech_resolved');
            }
        });

        Schema::table('ebay_customer_records', function (Blueprint $table) {
            if (! Schema::hasColumn('ebay_customer_records', 'tech_resolved')) {
                $table->boolean('tech_resolved')->default(false)->after('status');
            }
            if (! Schema::hasColumn('ebay_customer_records', 'tech_resolved_at')) {
                $table->date('tech_resolved_at')->nullable()->after('tech_resolved');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['tech_resolved', 'tech_resolved_at']);
        });

        Schema::table('ebay_customer_records', function (Blueprint $table) {
            $table->dropColumn(['tech_resolved', 'tech_resolved_at']);
        });
    }
};
