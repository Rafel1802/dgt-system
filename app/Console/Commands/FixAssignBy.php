<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Card;

class FixAssignBy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:assign-by';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fixes Assign By (created_by) for copied/synced cards to match the original card.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Fetching synced cards...");
        $cards = Card::whereNotNull('sync_group_id')->orderBy('created_at', 'asc')->get()->groupBy('sync_group_id');
        $fixed = 0;

        foreach ($cards as $group => $groupCards) {
            $original = $groupCards->first();
            $originalCreator = $original->created_by;
            foreach ($groupCards as $card) {
                if ($card->created_by !== $originalCreator) {
                    $card->created_by = $originalCreator;
                    $card->save();
                    $fixed++;
                }
            }
        }

        $this->info("Fixed " . $fixed . " cards.");
    }
}
