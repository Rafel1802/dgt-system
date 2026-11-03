<?php

namespace App\Console\Commands;

use App\Models\Board;
use App\Models\Card;
use Illuminate\Console\Command;

class FixMisplacedSmmTwinsCommand extends Command
{
    protected $signature = 'cards:fix-misplaced-smm-twins {--board= : SMM board ID to check (default: all SMM boards)} {--dry-run : Report only, do not change anything}';
    protected $description = 'Move team Planning board copies of SMM cards that landed in a different month\'s Planning board into the Planning board of the same month (same week list as the SMM card).';

    private const MONTH_RE = '/(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i';

    public function handle()
    {
        $dry = (bool) $this->option('dry-run');

        $smmBoards = Board::where(function ($q) {
            $q->where('type', 'smm')->orWhere('name', 'like', '%smm%');
        })->where('name', 'not like', '%workflow%');

        if ($this->option('board')) {
            $smmBoards->where('id', (int) $this->option('board'));
        }

        $moved = 0;
        foreach ($smmBoards->get() as $smmBoard) {
            if (!preg_match(self::MONTH_RE, $smmBoard->name, $m)) {
                continue;
            }
            $month = strtolower($m[0]);
            $this->line("SMM board: <info>{$smmBoard->name}</info> (ID {$smmBoard->id})" . ($dry ? ' [DRY RUN]' : ''));

            $cards = Card::where('board_id', $smmBoard->id)->whereNotNull('sync_group_id')->where('sync_group_id', '!=', '')->with('boardList')->get();

            foreach ($cards as $smmCard) {
                $twins = Card::where('sync_group_id', $smmCard->sync_group_id)->where('id', '!=', $smmCard->id)->with('board')->get();

                foreach ($twins as $twin) {
                    $tb = $twin->board;
                    if (!$tb) continue;
                    $n = strtolower($tb->name);
                    if (!str_contains($n, 'planning') || str_contains($n, 'smm') || str_contains($n, 'workflow')) continue;

                    // Already in the correct month?
                    if (str_contains($n, $month)) continue;

                    // Find same-month planning board in the same workspace
                    $target = Board::where('workspace_id', $tb->workspace_id)
                        ->where('is_archived', false)
                        ->where('name', 'like', '%planning%')
                        ->where('name', 'like', '%' . $m[0] . '%')
                        ->where('name', 'not like', '%smm%')
                        ->where('name', 'not like', '%workflow%')
                        ->first();

                    if (!$target) {
                        $this->warn("  No {$m[0]} planning board in workspace {$tb->workspace_id} for card #{$twin->id} '{$twin->title}'");
                        continue;
                    }

                    $weekNum = null;
                    $smmListName = trim($smmCard->boardList?->name ?? '');
                    if (preg_match('/^Week\s*(\d+)/i', $smmListName, $wm)) $weekNum = $wm[1];

                    $targetList = $target->lists()->orderBy('position')->get()->first(function ($l) use ($weekNum, $smmListName) {
                        $ln = trim($l->name);
                        if ($weekNum !== null) {
                            return preg_match('/^Week\s*(\d+)/i', $ln, $lm) && $lm[1] === $weekNum;
                        }
                        return strcasecmp($ln, $smmListName) === 0;
                    }) ?? $target->lists()->orderBy('position')->first();

                    if (!$targetList) continue;

                    $this->info("  #{$twin->id} '{$twin->title}': [{$tb->name}] -> [{$target->name}] / {$targetList->name}");

                    if (!$dry) {
                        $oldName = $tb->name;
                        $position = (int) Card::where('board_list_id', $targetList->id)->max('position') + 1;
                        $twin->update([
                            'board_id' => $target->id,
                            'board_list_id' => $targetList->id,
                            'position' => $position,
                        ]);
                        try {
                            $twin->comments()->create([
                                'user_id' => $twin->created_by ?? 1,
                                'content' => "Card moved from **{$oldName}** to **{$target->name}** (List: {$targetList->name}) to match the SMM board month.",
                                'is_system' => true,
                            ]);
                        } catch (\Throwable $e) {}
                    }
                    $moved++;
                }
            }
        }

        $this->info(($dry ? 'Would move ' : 'Moved ') . "{$moved} card(s).");
        return 0;
    }
}
