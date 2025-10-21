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
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Resolve "today" and "now" at each scheduler tick
        $today = Carbon::now()->toDateString();          // e.g. 2025-10-21
        $now   = Carbon::now()->format('Y-m-d H:i:s');   // e.g. 2025-10-21 14:37:00

        // ------------------------------------------------------------------
        // ✅ Pull logs from ZKTeco device every 5 minutes
        // ------------------------------------------------------------------
        $schedule->command('attendance:sync-zkteco')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schedule_zkteco_sync.log'));

        // ------------------------------------------------------------------
        // ✅ BioTime → attendance_raw import every 15 minutes (today only)
        // ------------------------------------------------------------------
        $schedule->command("biotime:import --from={$today} --to={$today} --summary")
            ->everyFifteenMinutes()
            ->onOneServer()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schedule_biotime_import.log'));

        // ------------------------------------------------------------------
        // ✅ Consolidate today's raw logs up to *now* every 10 minutes
        //    since = today's 00:00:00 ; until = current timestamp (now)
        // ------------------------------------------------------------------
        $schedule->command("attendance:consolidate --since={$today} 00:00:00 --until=\"{$now}\" --mode=sequence")
            ->everyTenMinutes()
            ->onOneServer()
            ->withoutOverlapping(20)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schedule_consolidate.log'));

        // Example:
        // $schedule->command('reports:generate-daily')->dailyAt('18:05');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
