<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * Register your Artisan command classes here so they're discoverable.
     * (Add/remove lines based on what you actually have.)
     */
    protected $commands = [
        \App\Console\Commands\AttendanceSyncZkteco::class,
        \App\Console\Commands\BioTimeImport::class,
        \App\Console\Commands\BioTimeSyncUsers::class,
        \App\Console\Commands\AttendanceConsolidate::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Use Manila time for "today" and "now"
        $tz = config('app.timezone', 'Asia/Manila');
        $now = Carbon::now($tz);

        // Build the dynamic ranges:
        $from  = $now->toDateString();                         // YYYY-MM-DD
        $since = $now->copy()->startOfDay()->format('Y-m-d H:i:s'); // YYYY-MM-DD 00:00:00
        $until = $now->format('Y-m-d H:i:s');                  // current timestamp

        // Make the scheduler itself respect Manila
        $schedule->timezone($tz);

        // ---------------------------------------------------------------------
        // ZKTeco sync: every 5 minutes
        // ---------------------------------------------------------------------
        $schedule->command('attendance:sync-zkteco')
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/schedule_zkteco_sync.log'));

        // ---------------------------------------------------------------------
        // BioTime import: today only, every 10 minutes
        //   php artisan biotime:import --from=YYYY-MM-DD --to=YYYY-MM-DD --summary
        // ---------------------------------------------------------------------
        $schedule->command('biotime:import', [
                '--from'    => $from,
                '--to'      => $from,
                '--summary' => true,   // boolean flag
            ])
            ->everyTenMinutes()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/schedule_biotime_import.log'));

        // ---------------------------------------------------------------------
        // Consolidate: from 00:00:00 today up to "now", every 10 minutes
        //   php artisan attendance:consolidate --since="YYYY-MM-DD HH:MM:SS"
        //                                      --until="YYYY-MM-DD HH:MM:SS"
        //                                      --mode=sequence
        // ---------------------------------------------------------------------
        $schedule->command('attendance:consolidate', [
                '--since' => $since,
                '--until' => $until,
                '--mode'  => 'sequence',
            ])
            ->everyTenMinutes()
            ->withoutOverlapping(20)
            ->appendOutputTo(storage_path('logs/schedule_consolidate.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
