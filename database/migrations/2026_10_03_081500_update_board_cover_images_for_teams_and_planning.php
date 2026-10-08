<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Board;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $smmImg = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/SMM.webp';
        $planningImg = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/ChatGPT%20Image%20Oct%203%202026%2007_59_23%20AM.webp';
        $teamAImg = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/TeamA.webp';
        $teamBImg = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/B.webp';

        // 1. SMM Planning boards
        DB::table('boards')
            ->where(function ($q) {
                $q->where('type', 'smm')
                  ->orWhere('is_active_smm', true)
                  ->orWhereRaw('LOWER(name) LIKE ?', ['%smm%']);
            })
            ->update([
                'cover_type' => 'image',
                'cover_value' => $smmImg,
            ]);

        // 2. Normal Planning boards (excluding SMM)
        DB::table('boards')
            ->where('type', '!=', 'smm')
            ->where('is_active_smm', false)
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%smm%'])
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%planning%'])
                  ->orWhere('type', 'planning')
                  ->orWhere('is_template', true);
            })
            ->update([
                'cover_type' => 'image',
                'cover_value' => $planningImg,
            ]);

        // 3. Team A boards (Workflow board Team A, etc.) not already matched as planning/smm
        DB::table('boards')
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%planning%'])
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%smm%'])
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%team a%'])
                  ->orWhereRaw('LOWER(name) LIKE ?', ['%teama%'])
                  ->orWhereRaw('LOWER(name) LIKE ?', ['%team-a%']);
            })
            ->update([
                'cover_type' => 'image',
                'cover_value' => $teamAImg,
            ]);

        // 4. Team B boards (Workflow board Team B, etc.) not already matched as planning/smm
        DB::table('boards')
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%planning%'])
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%smm%'])
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%team b%'])
                  ->orWhereRaw('LOWER(name) LIKE ?', ['%teamb%'])
                  ->orWhereRaw('LOWER(name) LIKE ?', ['%team-b%']);
            })
            ->update([
                'cover_type' => 'image',
                'cover_value' => $teamBImg,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep non-destructive
    }
};
