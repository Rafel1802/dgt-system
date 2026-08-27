<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Run the SMM card distribution sync automatically every five minutes
Schedule::command('smm:distribute-cards')->everyFiveMinutes()->withoutOverlapping();

// Automatically clear CRM notifications older than 1 month at the beginning of each month
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('notifications')
        ->where('data->module', 'crm')
        ->where('created_at', '<', now()->subMonth())
        ->delete();
})->monthly()->name('crm-notifications-cleanup')->withoutOverlapping();

// Clean up trashed items older than 2 days
Schedule::command('app:cleanup-trash')->daily();
