<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Http\Controllers\Board\CardController;

class SyncPlanningWeekCardsCommand extends Command
{
    protected $signature = 'cards:sync-planning-weeks {--week= : Specific week number to sync, e.g. 2} {--dry-run : Report movements without updating database}';
    protected $description = 'Synchronize planning week lists across connected planning boards (e.g. SMM Planning Board to Team Planning Boards)';

    public function handle()
    {
        $targetWeek = $this->option('week');
        $isDryRun = $this->option('dry-run');

        $this->info("Scanning for SMM Planning boards and synced cards..." . ($targetWeek ? " (Targeting Week {$targetWeek})" : " (All weeks)"));
        if ($isDryRun) {
            $this->warn("DRY RUN MODE: No database changes will be saved.");
        }

        // 1. Locate all SMM Planning boards
        $smmBoards = Board::where(function ($q) {
            $q->where('type', 'smm')
              ->orWhere('name', 'like', '%smm%');
        })->where(function ($q) {
            $q->where('name', 'like', '%planning%')
              ->orWhere('is_template', false);
        })->where('name', 'not like', '%workflow%')->get();

        if ($smmBoards->isEmpty()) {
            $this->warn("No SMM Planning boards found.");
            return 0;
        }

        $totalMoved = 0;
        $totalChecked = 0;

        foreach ($smmBoards as $smmBoard) {
            $this->line("Checking board: <info>{$smmBoard->name}</info> (ID: {$smmBoard->id})");

            // Get lists on this SMM board
            $smmLists = $smmBoard->lists()->orderBy('position')->get();

            foreach ($smmLists as $smmList) {
                $listName = trim($smmList->name);
                $weekNum = null;
                if (preg_match('/^Week\s*(\d+)/i', $listName, $m)) {
                    $weekNum = $m[1];
                }

                // If specific week was requested, skip other lists
                if ($targetWeek !== null) {
                    if ($weekNum === null || (string)$weekNum !== (string)$targetWeek) {
                        continue;
                    }
                } else {
                    // Only process Week lists or Urgent/Priority
                    if ($weekNum === null && stripos($listName, 'urgent') === false && stripos($listName, 'priority') === false) {
                        continue;
                    }
                }

                // Find cards in this list with sync_group_id
                $cards = Card::where('board_list_id', $smmList->id)
                    ->whereNotNull('sync_group_id')
                    ->where('sync_group_id', '!=', '')
                    ->get();

                if ($cards->isEmpty()) {
                    continue;
                }

                $this->line("  List <comment>{$listName}</comment>: {$cards->count()} synced card(s)");

                foreach ($cards as $smmCard) {
                    $totalChecked++;
                    $twins = Card::where('sync_group_id', $smmCard->sync_group_id)
                        ->where('id', '!=', $smmCard->id)
                        ->get();

                    foreach ($twins as $twin) {
                        $twinBoard = $twin->board;
                        if (!$twinBoard) {
                            continue;
                        }

                        // Never touch workflow boards
                        if (stripos($twinBoard->name, 'Workflow') !== false) {
                            continue;
                        }

                        // Must be a Planning board or have Week lists
                        $isTwinPlanning = stripos($twinBoard->name, 'Planning') !== false
                            || $twinBoard->lists()->where('name', 'like', 'Week%')->exists();
                        if (!$isTwinPlanning) {
                            continue;
                        }

                        // Find matching list on twin board
                        $twinList = $twinBoard->lists->first(function ($list) use ($listName, $weekNum) {
                            $lName = trim($list->name);
                            if ($weekNum !== null) {
                                if (preg_match('/^Week\s*(\d+)/i', $lName, $lm)) {
                                    return $lm[1] === $weekNum;
                                }
                                return false;
                            }
                            if (strcasecmp($lName, $listName) === 0) {
                                return true;
                            }
                            if (stripos($listName, 'urgent') !== false && stripos($lName, 'urgent') !== false) {
                                return true;
                            }
                            return false;
                        });

                        if ($twinList && $twin->board_list_id !== $twinList->id) {
                            $oldListName = $twin->boardList?->name ?? 'Unknown list';

                            if ($isDryRun) {
                                $this->info("    [DRY-RUN] Would move card #{$twin->id} '{$twin->title}' on [{$twinBoard->name}] from '{$oldListName}' to '{$twinList->name}'");
                            } else {
                                $twin->update(['board_list_id' => $twinList->id]);

                                $twin->comments()->create([
                                    'user_id' => 1,
                                    'content' => "Card automatically moved from **{$oldListName}** to **{$twinList->name}** (Synced to match {$smmBoard->name}).",
                                    'is_system' => true,
                                ]);

                                try {
                                    app(CardController::class)->logCardActivity(
                                        $twin,
                                        'moved',
                                        "moved this card from **{$oldListName}** to **{$twinList->name}** (Synced to match {$smmBoard->name})"
                                    );
                                } catch (\Throwable $e) {
                                    // Activity logging is non-fatal
                                }

                                $this->info("    ✓ Moved card #{$twin->id} '{$twin->title}' on [{$twinBoard->name}] from '{$oldListName}' to '{$twinList->name}'");
                            }
                            $totalMoved++;
                        }
                    }
                }
            }
        }

        $this->newLine();
        $this->info("Complete: checked {$totalChecked} SMM card(s), synced {$totalMoved} twin card(s)." . ($isDryRun ? " (Dry-run mode, no changes made)" : ""));
        return 0;
    }
}
