<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics', function (Blueprint $table) {
            $table->foreignId('trucking_company_id')->nullable()->after('assigned_to')->constrained('trucking_companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('logistics', function (Blueprint $table) {
            $table->dropForeign(['trucking_company_id']);
            $table->dropColumn('trucking_company_id');
        });
    }
};
