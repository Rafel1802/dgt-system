<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SmmCardDistributionJob;

class SmmDistributeCards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smm:distribute-cards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Distributes approved cards from active SMM Planning Boards to the main workflow board.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Dispatching SMM Card Distribution Job...');
        
        SmmCardDistributionJob::dispatchSync();
        
        $this->info('Job completed successfully.');
        
        return Command::SUCCESS;
    }
}
