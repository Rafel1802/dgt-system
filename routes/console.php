<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Run the SMM card distribution sync automatically every five minutes
Schedule::command('smm:distribute-cards')->everyFiveMinutes()->withoutOverlapping();


// Clean up trashed items older than 7 days
Schedule::command('app:cleanup-trash')->daily();

// System health auto-repair disabled

// Auto-clear activity and security logs older than 7 days (1 week retention)
Schedule::command('system:cleanup --days=7')->daily()->name('system-cleanup-weekly-logs')->withoutOverlapping();


