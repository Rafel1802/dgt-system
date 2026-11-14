<?php

use App\Models\Board;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Hide all boards across the system except November 2026 boards.
     */
    public function up(): void
    {
        if (!Schema::hasTable('boards')) {
            return;
        }

        $boards = Board::all();
        foreach ($boards as $board) {
            $displayName = (string) $board->name;
            $rawName = (string) ($board->getAttributes()['name'] ?? '');
            $slug = (string) $board->slug;

            $isNovember = str_contains(strtolower($displayName), 'november')
                || str_contains(strtolower($rawName), 'november')
                || str_contains(strtolower($slug), 'november')
                || (
                    !str_contains(strtolower($displayName), 'august')
                    && !str_contains(strtolower($displayName), 'september')
                    && !str_contains(strtolower($displayName), 'october')
                    && !str_contains(strtolower($displayName), 'december')
                    && $board->created_at
                    && $board->created_at->format('Y-m') === '2026-11'
                );

            $board->is_hidden = ! $isNovember;
            $board->saveQuietly();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};

