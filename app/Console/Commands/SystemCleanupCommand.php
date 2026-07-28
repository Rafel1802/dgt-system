<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActivityLog;
use App\Models\DeviceLog;
use App\Models\LoginAttempt;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class SystemCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:cleanup {--days=30 : The number of days to keep logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old system logs, activity logs, and notifications to free up space.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);
        $notificationCutoff = Carbon::now()->subDays(90); // keep notifications a bit longer

        $this->info("Starting system cleanup...");
        $this->info("Deleting logs older than {$cutoffDate->format('Y-m-d')}");

        // 1. Database Logs
        try {
            $activityCount = ActivityLog::where('created_at', '<', $cutoffDate)->delete();
            $this->info("Deleted {$activityCount} old Activity Logs.");
        } catch (\Exception $e) {}

        try {
            $deviceCount = DeviceLog::where('created_at', '<', $cutoffDate)->delete();
            $this->info("Deleted {$deviceCount} old Device Logs.");
        } catch (\Exception $e) {}

        try {
            $loginCount = LoginAttempt::where('created_at', '<', $cutoffDate)->delete();
            $this->info("Deleted {$loginCount} old Login Attempts.");
        } catch (\Exception $e) {}

        // 2. Notifications
        try {
            $notificationCount = DB::table('notifications')
                ->where('created_at', '<', $notificationCutoff)
                ->delete();
            $this->info("Deleted {$notificationCount} old Notifications (older than 90 days).");
        } catch (\Exception $e) {}

        // 3. File Logs
        $logPath = storage_path('logs/laravel.log');
        if (File::exists($logPath)) {
            $size = File::size($logPath);
            if ($size > 1024 * 1024 * 50) { // 50MB limit
                File::put($logPath, ''); // Empty it instead of deleting to prevent permission issues
                $this->info("Cleared laravel.log (was " . round($size / 1024 / 1024, 2) . " MB).");
            } else {
                $this->info("laravel.log is fine (" . round($size / 1024 / 1024, 2) . " MB).");
            }
        }

        // 4. Temporary Uploads (optional)
        // Cleanup livewire temp uploads or old unattached files if necessary
        $livewireTempPath = storage_path('app/livewire-tmp');
        if (File::exists($livewireTempPath)) {
            $files = File::files($livewireTempPath);
            $deletedFiles = 0;
            foreach ($files as $file) {
                if ($file->getMTime() < $cutoffDate->timestamp) {
                    File::delete($file);
                    $deletedFiles++;
                }
            }
            if ($deletedFiles > 0) {
                $this->info("Deleted {$deletedFiles} old Livewire temporary uploads.");
            }
        }

        $this->info("System cleanup completed successfully!");
    }
}
