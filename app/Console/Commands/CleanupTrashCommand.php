<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class CleanupTrashCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-trash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete trashed cards and lists older than 7 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Delete items that have been in the trash for more than 7 days
        $threshold = Carbon::now()->subDays(7);

        $deletedLists = \App\Models\BoardList::onlyTrashed()->where('deleted_at', '<=', $threshold)->forceDelete();
        $deletedCards = \App\Models\Card::onlyTrashed()->where('deleted_at', '<=', $threshold)->forceDelete();

        $this->info("Trash cleanup complete. Trashed Lists deleted: $deletedLists, Trashed Cards deleted: $deletedCards");
    }
}
