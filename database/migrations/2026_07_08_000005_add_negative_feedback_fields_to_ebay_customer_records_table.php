<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebay_customer_records', function (Blueprint $table) {
            if (! Schema::hasColumn('ebay_customer_records', 'negative_feedback_causes')) {
                $table->json('negative_feedback_causes')->nullable()->after('status');
            }
            if (! Schema::hasColumn('ebay_customer_records', 'negative_feedback_resolved')) {
                $table->boolean('negative_feedback_resolved')->default(false)->after('negative_feedback_causes');
            }
            if (! Schema::hasColumn('ebay_customer_records', 'negative_feedback_resolved_at')) {
                $table->date('negative_feedback_resolved_at')->nullable()->after('negative_feedback_resolved');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ebay_customer_records', function (Blueprint $table) {
            $table->dropColumn(['negative_feedback_causes', 'negative_feedback_resolved', 'negative_feedback_resolved_at']);
        });
    }
};
