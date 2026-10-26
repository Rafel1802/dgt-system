<?php

namespace App\Console\Commands;

use App\Models\Board;
use App\Models\Card;
use Illuminate\Console\Command;

class CleanupNonSmmWorkflowCardsCommand extends Command
{
    protected $signature = 'cards:cleanup-non-smm-workflow {--title= : Specific card title to target} {--dry-run : Check without deleting or moving}';
    protected $description = 'Find cards on Workflow boards that do not have an SMM label and move them back to their Planning board or remove unwanted workflow copies';

    public function handle(): int
    {
        $targetTitle = $this->option('title');
        $isDryRun = $this->option('dry-run');

        $this->info("Scanning Workflow boards for non-SMM cards...");

        $workflowBoards = Board::where('is_archived', false)
            ->where(function ($q) {
                $q->where('name', 'like', '%Workflow%')
                  ->orWhere('type', 'workflow');
            })
            ->get();

        if ($workflowBoards->isEmpty()) {
            $this->warn("No active workflow boards found.");
            return 0;
        }

        $totalChecked = 0;
        $totalRemoved = 0;
        $totalMoved = 0;

        foreach ($workflowBoards as $board) {
            $this->line("\nChecking Workflow Board #{$board->id}: {$board->name}");

            $query = Card::where('board_id', $board->id)
                ->where('is_archived', false)
                ->with(['labels', 'boardList', 'checklists.items']);

            if ($targetTitle) {
                $query->where('title', 'like', "%{$targetTitle}%");
            }

            $cards = $query->get();

            foreach ($cards as $card) {
                $totalChecked++;

                // If card has an SMM label, it belongs on the workflow board
                if ($card->hasSmmLabel()) {
                    continue;
                }

                $this->warn("Found non-SMM card #{$card->id}: '{$card->title}' on list '{$card->boardList?->name}'");

                // Check if a twin card exists on a Planning Board
                $planningTwin = null;
                if ($card->sync_group_id) {
                    $planningTwin = Card::where('sync_group_id', $card->sync_group_id)
                        ->where('id', '!=', $card->id)
                        ->whereHas('board', function ($q) {
                            $q->where('name', 'like', '%Planning%')
                              ->orWhere('type', 'smm');
                        })
                        ->with('board', 'boardList')
                        ->first();
                }

                if (!$planningTwin) {
                    // Fallback: match by title on any non-workflow board
                    $planningTwin = Card::where('title', $card->title)
                        ->where('id', '!=', $card->id)
                        ->whereHas('board', function ($q) {
                            $q->where('name', 'not like', '%Workflow%')
                              ->where('type', '!=', 'workflow');
                        })
                        ->with('board', 'boardList')
                        ->first();
                }

                if ($planningTwin) {
                    $this->info("  -> Twin card #{$planningTwin->id} already exists on Planning Board '{$planningTwin->board?->name}' (List: '{$planningTwin->boardList?->name}').");
                    $this->info("  -> Removing duplicate copy #{$card->id} from Workflow Board '{$board->name}'...");

                    if (!$isDryRun) {
                        $card->delete();
                    }
                    $totalRemoved++;
                } else {
                    // Find planning board to move it back to
                    $planningBoard = Board::where('workspace_id', $board->workspace_id)
                        ->where('is_archived', false)
                        ->where(function ($q) {
                            $q->where('name', 'like', '%Planning%')
                              ->orWhere('type', 'planning');
                        })
                        ->first();

                    if (!$planningBoard) {
                        $planningBoard = Board::where('is_archived', false)
                            ->where(function ($q) {
                                $q->where('name', 'like', '%Planning%');
                            })
                            ->first();
                    }

                    if ($planningBoard) {
                        $targetList = $planningBoard->lists()->first();
                        $this->info("  -> Moving card #{$card->id} back to Planning Board '{$planningBoard->name}' (List: '{$targetList?->name}')...");

                        if (!$isDryRun && $targetList) {
                            $card->update([
                                'board_id'      => $planningBoard->id,
                                'board_list_id' => $targetList->id,
                            ]);
                        }
                        $totalMoved++;
                    } else {
                        $this->error("  -> Could not find planning board to move card #{$card->id} back to.");
                    }
                }
            }
        }

        $this->newLine();
        $this->info("Complete! Total checked: {$totalChecked} | Removed from workflow: {$totalRemoved} | Moved back to planning: {$totalMoved}" . ($isDryRun ? " (DRY-RUN)" : ""));

        return 0;
    }
}
