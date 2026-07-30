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
            if (!Schema::hasColumn('cards', 'smm_class_label')) {
                $table->string('smm_class_label')->nullable()->after('sub_label');
            }
            if (!Schema::hasColumn('cards', 'smm_team_label')) {
                $table->string('smm_team_label')->nullable()->after('smm_class_label');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['smm_class_label', 'smm_team_label']);
        });
    }
};
