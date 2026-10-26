<?php

namespace App\Console\Commands;

use App\Models\Board;
use App\Models\Card;
use App\Services\BoardWorkflowService;
use Illuminate\Console\Command;

class SyncCompletedPlanningCardsToWorkflowCommand extends Command
{
    protected $signature = 'cards:sync-planning-to-workflow {--board= : Specific planning board ID or slug}';
    protected $description = 'Scan cards on Planning Boards and auto-copy cards with 100% completed checklists to their respective Team A or Team B Workflow Board on the Draft list';

    public function handle(BoardWorkflowService $workflowService): int
    {
        $this->info("Scanning planning boards for cards with 100% completed checklists...");

        $boardQuery = Board::where('is_archived', false)
            ->where(function ($q) {
                $q->where('name', 'like', '%Planning%')
                  ->orWhere('type', 'smm');
            });

        $boardOpt = $this->option('board');
        if ($boardOpt) {
            $boardQuery->where(function ($q) use ($boardOpt) {
                if (is_numeric($boardOpt)) {
                    $q->where('id', (int) $boardOpt);
                } else {
                    $q->where('slug', $boardOpt);
                }
            });
        }

        $planningBoards = $boardQuery->get();
        if ($planningBoards->isEmpty()) {
            $this->warn("No active planning boards found.");
            return 0;
        }

        $totalChecked = 0;
        $totalCopied = 0;
        $totalSkipped = 0;

        foreach ($planningBoards as $board) {
            $this->line("\nChecking Board #{$board->id}: {$board->name}");

            $cards = Card::where('board_id', $board->id)
                ->where('is_archived', false)
                ->with(['checklists.items', 'assignees'])
                ->get();

            foreach ($cards as $card) {
                $totalChecked++;

                $checklists = $card->checklists;
                if ($checklists->isEmpty()) {
                    continue;
                }

                $allItems = $checklists->flatMap(fn($cl) => $cl->items);
                if ($allItems->isEmpty()) {
                    continue;
                }

                $totalItems = $allItems->count();
                $completedItems = $allItems->filter(fn($it) => (bool)$it->is_completed)->count();

                if ($completedItems !== $totalItems) {
                    continue; // Checklist not 100% completed
                }

                if (!$card->hasSmmLabel()) {
                    continue; // Card has no SMM label
                }

                $copiedCard = $workflowService->autoCopyCompletedPlanningCardToWorkflow($card);
                if ($copiedCard) {
                    $copiedCard->unsetRelations();
                    $targetBoard = $copiedCard->board()->first();
                    $targetList = $copiedCard->boardList()->first();
                    $targetTeam = $copiedCard->team ?? 'A';
                    $this->info("  ✔ Card #{$card->id} [{$completedItems}/{$totalItems} done]: '{$card->title}' -> Copied to Team {$targetTeam} ({$targetBoard?->name} / {$targetList?->name})");
                    $totalCopied++;
                } else {
                    $totalSkipped++;
                }
            }
        }

        $this->newLine();
        $this->info("Sync completed! Checked: {$totalChecked} cards | Newly copied: {$totalCopied} | Already copied/skipped: {$totalSkipped}");

        return 0;
    }
}
