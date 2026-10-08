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
        if (Schema::hasTable('cards') && !Schema::hasColumn('cards', 'team')) {
            Schema::table('cards', function (Blueprint $table) {
                $table->string('team', 10)->nullable()->index()->after('sub_label');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cards') && Schema::hasColumn('cards', 'team')) {
            Schema::table('cards', function (Blueprint $table) {
                $table->dropColumn('team');
            });
        }
    }
};
