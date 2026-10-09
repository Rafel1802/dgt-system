<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Card;
use App\Http\Controllers\Board\CardController;

class SyncChecklistsAcrossTwinsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cards:sync-checklists {--dry-run : Only show what would be synced without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Harmonize and synchronize checklist items, review marks (green tick/red issue), and completion status across twin cards in all sync groups.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scanning all sync groups for checklist items...');

        $groups = Card::whereNotNull('sync_group_id')
            ->where('sync_group_id', '!=', '')
            ->with(['checklists.items'])
            ->get()
            ->groupBy('sync_group_id');

        $this->info("Found {$groups->count()} sync groups to evaluate.");

        $controller = app(CardController::class);
        $syncedCount = 0;

        foreach ($groups as $groupId => $cards) {
            if ($cards->count() < 2) {
                continue;
            }

            $firstCard = $cards->first();
            $changed = $controller->syncChecklistsAcrossTwins($firstCard);
            if ($changed) {
                $syncedCount++;
                $this->line("  ✓ Synced checklists for group: <info>{$groupId}</info> ({$cards->count()} cards)");
            }
        }

        $this->info("Completed checklist sync. Updated {$syncedCount} sync group(s).");
        return 0;
    }
}
