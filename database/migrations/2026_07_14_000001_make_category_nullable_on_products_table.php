<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products have used `category_id` (FK to product_categories) as the
     * real categorization field since the 2026_07_01_000002 migration — the
     * "New Product" form only submits category_id, never the legacy
     * `category` enum column. But that column was left NOT NULL with no
     * default, so every product creation failed at the DB layer with
     * "Field 'category' doesn't have a default value". Product::category
     * is already nullable-safe everywhere it's read (getCategoryNameAttribute,
     * the Logistic quick-add dropdown), so this just makes the schema match
     * how the column has actually been treated since category_id landed.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable(false)->change();
        });
    }
};
