<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Card;
use App\Http\Controllers\Board\CardController;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            $controller = app(CardController::class);
            $cards = Card::whereNotNull('sync_group_id')
                ->where('sync_group_id', '!=', '')
                ->with(['checklists.items'])
                ->get()
                ->groupBy('sync_group_id');

            foreach ($cards as $groupId => $twinCards) {
                if ($twinCards->count() >= 2) {
                    $controller->syncChecklistsAcrossTwins($twinCards->first());
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Migration sync_checklist_review_marks_across_twins warning: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive data harmonization; no down action needed.
    }
};
