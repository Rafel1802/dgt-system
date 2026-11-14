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

// Daily Google Search Console Performance Data Sync
Schedule::command('gsc:sync --days=30')->dailyAt('03:00')->withoutOverlapping()->name('gsc-sync-daily');

// Daily Automated Google Search Console URL Inspection & Indexing Sync (Nightly quiet hours)
Schedule::command('gsc:auto-inspect --limit=200')->dailyAt('03:30')->withoutOverlapping()->name('gsc-auto-inspect-daily');

// Every 5 minutes Central Website & Blog Analytics Synchronization across Hostinger accounts
Schedule::command('dgt:sync-websites --sync')->everyFiveMinutes()->withoutOverlapping()->name('dgt-website-sync-5m');

// Every 5 minutes: auto-convert chat images older than 5 minutes to WebP to save server storage
Schedule::command('chat:convert-images-webp')->everyFiveMinutes()->withoutOverlapping()->name('chat-convert-images-webp-5m');

// ── Production Reports Scheduled Automation (Asia/Phnom_Penh) ────────────────
// Daily 4:45 PM Cambodia reminder — exactly 15 minutes before the 5:00 PM deadline (Mon–Sat).
// NOTE: weekdays()->saturdays() would overwrite each other (last call wins) and only run on Saturdays,
// so the day list is set explicitly instead.
Schedule::command('production-reports:process-schedule --task=daily-reminder')
    ->days([1, 2, 3, 4, 5, 6])
    ->at('16:45')
    ->timezone('Asia/Phnom_Penh')
    ->withoutOverlapping()
    ->name('pr-daily-reminder-1645');

// Daily 5:05 PM Cambodia overdue check (after 5:00 PM deadline)
Schedule::command('production-reports:process-schedule --task=daily-overdue')
    ->days([1, 2, 3, 4, 5, 6])
    ->at('17:05')
    ->timezone('Asia/Phnom_Penh')
    ->withoutOverlapping()
    ->name('pr-daily-overdue-1705');

// Friday 4:00 PM Cambodia: auto-prepare weekly team report drafts for Dara (Team A) & Kim (Team B)
Schedule::command('production-reports:process-schedule --task=weekly-draft')
    ->fridays()
    ->at('16:00')
    ->timezone('Asia/Phnom_Penh')
    ->withoutOverlapping()
    ->name('pr-weekly-draft-fri-1600');

// Saturday 7:00 AM Cambodia: final reminder before 8:00 AM weekly team report deadline
Schedule::command('production-reports:process-schedule --task=weekly-reminder')
    ->saturdays()
    ->at('07:00')
    ->timezone('Asia/Phnom_Penh')
    ->withoutOverlapping()
    ->name('pr-weekly-reminder-sat-0700');

// Saturday 8:05 AM Cambodia: mark unsubmitted weekly reports overdue and alert Supervisor Somalika
Schedule::command('production-reports:process-schedule --task=weekly-overdue')
    ->saturdays()
    ->at('08:05')
    ->timezone('Asia/Phnom_Penh')
    ->withoutOverlapping()
    ->name('pr-weekly-overdue-sat-0805');

// Monthly 26th at 9:00 AM Cambodia: auto-prepare monthly report drafts from daily reports
Schedule::command('production-reports:process-schedule --task=monthly-draft')
    ->monthlyOn(26, '09:00')
    ->timezone('Asia/Phnom_Penh')
    ->withoutOverlapping()
    ->name('pr-monthly-draft-26');

// Monthly 28th at 4:00 PM Cambodia: monthly deadline reminder
Schedule::command('production-reports:process-schedule --task=monthly-reminder')
    ->monthlyOn(28, '16:00')
    ->timezone('Asia/Phnom_Penh')
    ->withoutOverlapping()
    ->name('pr-monthly-reminder-28');




