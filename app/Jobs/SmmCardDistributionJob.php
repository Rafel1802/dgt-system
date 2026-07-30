<?php

namespace App\Jobs;

use App\Models\Board;
use App\Models\Card;
use App\Enums\CardStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SmmCardDistributionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     * Distribute approved SMM cards to the main Workflow Board.
     */
    public function handle()
    {
        // Find the active SMM Planning Board
        $activeSmmBoards = Board::where('type', 'smm')->where('is_active_smm', true)->get();
        if ($activeSmmBoards->isEmpty()) {
            return;
        }

        // Find the main Kanban Workflow Board (or create if not found)
        $mainWorkflowBoard = Board::where('slug', 'workflow-board')
            ->orWhere('name', 'Workflow Board')
            ->first();

        if (!$mainWorkflowBoard) {
            return;
        }
        
        $mainTodoList = $mainWorkflowBoard->activeLists()->orderBy('position')->first();
        if (!$mainTodoList) {
            return; // No list to drop cards into
        }

        foreach ($activeSmmBoards as $smmBoard) {
            // Find Final Captions list
            $finalCaptionsList = $smmBoard->activeLists()->where('name', 'like', '%Final Captions%')->first();
            if (!$finalCaptionsList) continue;

            // Get Approved cards in Final Captions that haven't been synced to the main board yet.
            // A synced card has sync_id != null and there exists another card with the same sync_id on the main board.
            $cardsToDistribute = Card::where('board_list_id', $finalCaptionsList->id)
                ->where('status', CardStatus::Approved->value)
                ->get();

            foreach ($cardsToDistribute as $card) {
                // Check if this card is already on the main board
                $alreadySynced = false;
                if ($card->sync_id) {
                    $alreadySynced = Card::where('board_id', $mainWorkflowBoard->id)
                                         ->where('sync_id', $card->sync_id)
                                         ->exists();
                }

                if (!$alreadySynced) {
                    // Force a sync_id if not present
                    if (!$card->sync_id) {
                        Card::withoutEvents(function () use ($card) {
                            $card->sync_id = \Illuminate\Support\Str::uuid();
                            $card->save();
                        });
                    }

                    // Replicate to Main Workflow Board
                    $clone = $card->replicateRelationally($mainWorkflowBoard->id, $mainTodoList->id);
                    
                    // Assign to correct status in Kanban
                    $clone->update(['status' => CardStatus::Todo->value]);
                }
            }
        }
    }
}
