<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Add new category_id FK (nullable — keeps old data safe)
            $table->foreignId('category_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('product_categories')
                  ->nullOnDelete();

            // Add updated_by for audit trail
            $table->foreignId('updated_by')
                  ->nullable()
                  ->after('created_by')
                  ->constrained('users')
                  ->nullOnDelete();

            // Add unified status column
            $table->string('status')->default('active')->after('is_active')
                  ->comment('active | inactive | discontinued');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['category_id', 'updated_by', 'status']);
        });
    }
};
