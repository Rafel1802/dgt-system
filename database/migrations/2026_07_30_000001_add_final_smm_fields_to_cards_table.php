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
        Schema::table('cards', function (Blueprint $table) {
            if (!Schema::hasColumn('cards', 'smm_cluster_label')) {
                $table->string('smm_cluster_label')->nullable()->after('sub_label');
            }
            if (!Schema::hasColumn('cards', 'content_public_date')) {
                $table->date('content_public_date')->nullable()->after('start_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            if (Schema::hasColumn('cards', 'smm_cluster_label')) {
                $table->dropColumn('smm_cluster_label');
            }
            if (Schema::hasColumn('cards', 'content_public_date')) {
                $table->dropColumn('content_public_date');
            }
        });
    }
};
