<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Card;

class FixSyncedCardMembersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:fix-members {--dry-run : Only show what would be fixed without saving changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize members (assignees) and Assign By (created_by) across all twin cards in each sync group.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool)$this->option('dry-run');
        if ($isDryRun) {
            $this->warn('Running in DRY-RUN mode. No changes will be written to database.');
        }

        $this->info('Fetching all synced cards...');

        $groups = Card::whereNotNull('sync_group_id')
            ->where('sync_group_id', '!=', '')
            ->with(['assignees', 'board'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('sync_group_id');

        $this->info("Found {$groups->count()} sync groups to evaluate.");

        $fixedAssigneesCount = 0;
        $fixedCreatorCount = 0;

        foreach ($groups as $groupId => $cards) {
            if ($cards->count() < 2) {
                continue;
            }

            // 1. Resolve canonical creator (Assign By)
            // Prefer the earliest card that has created_by set
            $canonicalCreatorId = null;
            foreach ($cards as $c) {
                if (!empty($c->created_by)) {
                    $canonicalCreatorId = $c->created_by;
                    break;
                }
            }

            // 2. Resolve canonical assignees (Members)
            // Prefer the card that has assignees assigned
            $canonicalAssigneeData = [];
            foreach ($cards as $c) {
                if ($c->assignees->isNotEmpty()) {
                    $canonicalAssigneeData = $c->assignees->mapWithKeys(fn($u) => [
                        $u->id => ['assigned_at' => $u->pivot->assigned_at ?? now()]
                    ])->all();
                    break;
                }
            }

            // 3. Apply to cards in group
            foreach ($cards as $card) {
                // Check creator
                if ($canonicalCreatorId && $card->created_by !== $canonicalCreatorId) {
                    $fixedCreatorCount++;
                    $boardName = $card->board?->name ?? "Board {$card->board_id}";
                    $this->line("  [Creator] Card #{$card->id} on '{$boardName}': set created_by -> {$canonicalCreatorId}");
                    if (!$isDryRun) {
                        Card::withoutEvents(function () use ($card, $canonicalCreatorId) {
                            $card->created_by = $canonicalCreatorId;
                            $card->save();
                        });
                    }
                }

                // Check assignees
                if (!empty($canonicalAssigneeData)) {
                    $currentIds = $card->assignees->pluck('id')->sort()->values()->all();
                    $targetIds = collect(array_keys($canonicalAssigneeData))->sort()->values()->all();

                    if ($currentIds !== $targetIds) {
                        $fixedAssigneesCount++;
                        $boardName = $card->board?->name ?? "Board {$card->board_id}";
                        $this->line("  [Assignees] Card #{$card->id} on '{$boardName}': synced members -> [" . implode(',', $targetIds) . "]");
                        if (!$isDryRun) {
                            $card->assignees()->sync($canonicalAssigneeData);
                        }
                    }
                }
            }
        }

        $this->newLine();
        $this->info("Complete! Total sync groups checked: {$groups->count()}");
        $this->info("Fixed assignees on {$fixedAssigneesCount} cards.");
        $this->info("Fixed creator (Assign By) on {$fixedCreatorCount} cards.");

        return Command::SUCCESS;
    }
}
