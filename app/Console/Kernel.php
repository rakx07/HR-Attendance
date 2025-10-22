<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\AttendanceSyncZkteco::class,
        \App\Console\Commands\BioTimeImport::class,
        \App\Console\Commands\BioTimeSyncUsers::class,
        \App\Console\Commands\AttendanceConsolidate::class, // <- make sure this exists
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Always compute times at each tick in Asia/Manila
        $tz    = config('app.timezone', 'Asia/Manila');
        $today = Carbon::now($tz)->toDateString();                 // e.g. 2025-10-22
        $now   = Carbon::now($tz)->format('Y-m-d H:i:s');          // e.g. 2025-10-22 08:11:00
        $since = "{$today} 00:00:00";                              // midnight today

        // Optional: force scheduler itself to Manila so crontab math uses +08:00
        $schedule->timezone($tz);

        // ---------------------------------------------
        // ZKTeco: every 5 minutes
        // ---------------------------------------------
        $schedule->command('attendance:sync-zkteco')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schedule_zkteco_sync.log'));

        // ---------------------------------------------
        // BioTime import: every 10 minutes (today only)
        // ---------------------------------------------
        $schedule->command("biotime:import --from=\"{$today}\" --to=\"{$today}\" --summary")
            ->everyTenMinutes()
            ->onOneServer()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schedule_biotime_import.log'));

        // ---------------------------------------------
        // Consolidate: every 10 minutes (00:00:00 → now)
        // ---------------------------------------------
        $schedule->command("attendance:consolidate --since=\"{$since}\" --until=\"{$now}\" --mode=sequence")
            ->everyTenMinutes()
            ->onOneServer()
            ->withoutOverlapping(20)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schedule_consolidate.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
