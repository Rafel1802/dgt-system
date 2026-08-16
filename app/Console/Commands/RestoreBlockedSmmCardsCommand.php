<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\ActivityLog;

class RestoreBlockedSmmCardsCommand extends Command
{
    protected $signature = 'cards:restore-block-smm';
    protected $description = 'Restore cards in Block/Waiting on SMM boards back to their correct Week list';

    public function handle()
    {
        $this->info("Scanning for SMM Planning boards...");

        // Find SMM Planning boards
        $smmBoards = Board::where('name', 'like', '%smm%')
            ->orWhere('name', 'like', '%planning%')
            ->get();

        $count = 0;

        foreach ($smmBoards as $board) {
            // Find Block/Waiting list on this board
            $blockLists = $board->lists()->where('name', 'like', '%block%')->get();
            
            foreach ($blockLists as $blockList) {
                $blockedCards = Card::where('board_list_id', $blockList->id)->get();
                
                foreach ($blockedCards as $card) {
                    $originalListName = null;
                    
                    // 1. Try to find the original list from a twin card (copied_by_automation)
                    if ($card->sync_group_id) {
                        $twins = Card::where('sync_group_id', $card->sync_group_id)
                                    ->where('id', '!=', $card->id)
                                    ->get();
                        
                        foreach ($twins as $twin) {
                            $activity = ActivityLog::where('subject_id', $twin->id)
                                            ->where('subject_type', Card::class)
                                            ->where('action', 'card.copied_by_automation')
                                            ->orderBy('created_at', 'desc')
                                            ->first();
                                            
                            if ($activity && preg_match('/\*\*([^*]+)\*\*/', $activity->description, $matches)) {
                                $originalListName = $matches[1];
                                break;
                            }
                        }
                    }

                    // 2. Try to find from this card's own move history (before Block/Waiting)
                    if (!$originalListName) {
                        $moveActivity = ActivityLog::where('subject_id', $card->id)
                                            ->where('subject_type', Card::class)
                                            ->where('action', 'card.moved')
                                            ->where('description', 'like', '%Block/Waiting%')
                                            ->orderBy('created_at', 'desc')
                                            ->first();
                        
                        if ($moveActivity && preg_match('/from \*\*([^*]+)\*\*/', $moveActivity->description, $matches)) {
                            $originalListName = $matches[1];
                        }
                    }

                    // 3. Fallback based on start_date
                    if (!$originalListName && $card->start_date) {
                        $day = date('j', strtotime($card->start_date));
                        if ($day <= 7) $originalListName = 'Week 1';
                        elseif ($day <= 14) $originalListName = 'Week 2';
                        elseif ($day <= 21) $originalListName = 'Week 3';
                        elseif ($day <= 28) $originalListName = 'Week 4';
                        else $originalListName = 'Week 5';
                    }

                    // Fallback to Week 1 if all else fails
                    if (!$originalListName) {
                        $originalListName = 'Week 1';
                    }

                    // Find or create the target list on the SMM board
                    $targetList = $board->lists()->where('name', $originalListName)->first();
                    
                    if (!$targetList) {
                        // Create it if it somehow doesn't exist
                        $targetList = $board->lists()->create([
                            'name' => $originalListName,
                            'position' => $board->lists()->max('position') + 1,
                        ]);
                    }

                    // Move the card back
                    $card->update(['board_list_id' => $targetList->id]);
                    $this->info("Moved card '{$card->title}' to {$targetList->name}");
                    $count++;
                }
            }
        }

        $this->info("Done! Restored {$count} cards.");
        return 0;
    }
}
