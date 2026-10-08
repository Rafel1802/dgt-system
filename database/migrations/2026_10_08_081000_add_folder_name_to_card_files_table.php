<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('card_files', 'folder_name')) {
            Schema::table('card_files', function (Blueprint $table) {
                $table->string('folder_name', 255)->nullable()->after('original_name')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('card_files', 'folder_name')) {
            Schema::table('card_files', function (Blueprint $table) {
                $table->dropIndex(['folder_name']);
                $table->dropColumn('folder_name');
            });
        }
    }
};
