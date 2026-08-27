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
    protected $description = 'Permanently delete trashed cards and lists older than 2 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $threshold = Carbon::now()->subDays(2);

        $deletedLists = \App\Models\BoardList::onlyTrashed()->where('deleted_at', '<=', $threshold)->forceDelete();
        $deletedCards = \App\Models\Card::onlyTrashed()->where('deleted_at', '<=', $threshold)->forceDelete();

        $this->info("Trash cleanup complete. Trashed Lists deleted: $deletedLists, Trashed Cards deleted: $deletedCards");
    }
}
