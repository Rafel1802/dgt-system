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
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE cards DROP FOREIGN KEY cards_created_by_foreign');
        } catch (\Exception $e) {}
        
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE cards MODIFY created_by BIGINT UNSIGNED NULL');
        
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE cards ADD CONSTRAINT cards_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL');
        } catch (\Exception $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE cards DROP FOREIGN KEY cards_created_by_foreign');
        } catch (\Exception $e) {}
        
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE cards MODIFY created_by BIGINT UNSIGNED NOT NULL');
        
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE cards ADD CONSTRAINT cards_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE');
        } catch (\Exception $e) {}
    }
};
