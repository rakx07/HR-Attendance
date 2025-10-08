<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{


    protected $commands = [
        \App\Console\Commands\AttendanceSyncZkteco::class,
          \App\Console\Commands\BioTimeImport::class,
    \App\Console\Commands\BioTimeSyncUsers::class,
    ];
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ✅ Pull logs from ZKTeco device every 5 minutes
        $schedule->command('attendance:sync-zkteco')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // ✅ Consolidate raw logs into daily records every 10 minutes
        $schedule->command('attendance:consolidate')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Example: Generate daily summary report at 6:05 PM
        // $schedule->command('reports:generate-daily')->dailyAt('18:05');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        // 🔹 Auto-load all commands inside app/Console/Commands
        $this->load(__DIR__.'/Commands');

        // 🔹 Load custom Artisan routes
        require base_path('routes/console.php');
    }
}
