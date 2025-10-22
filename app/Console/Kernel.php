<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\BioTimeImport::class,
        \App\Console\Commands\AttendanceConsolidate::class,
        // Other command classes can go here if needed
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Use Manila timezone
        $tz = config('app.timezone', 'Asia/Manila');
        $now = Carbon::now($tz);

        // Build dynamic date/time ranges
        $from  = $now->toDateString();                                 // 2025-10-22
        $since = $now->copy()->startOfDay()->format('Y-m-d H:i:s');     // 2025-10-22 00:00:00
        $until = $now->format('Y-m-d H:i:s');                           // 2025-10-22 08:15:00

        $schedule->timezone($tz);

        // ---------------------------------------------------------------------
        // BioTime import: every 10 minutes (today only)
        // php artisan biotime:import --from=YYYY-MM-DD --to=YYYY-MM-DD --summary
        // ---------------------------------------------------------------------
        $schedule->command('biotime:import', [
                '--from'    => $from,
                '--to'      => $from,
                '--summary' => true,
            ])
            ->everyTenMinutes()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/schedule_biotime_import.log'));

        // ---------------------------------------------------------------------
        // Attendance consolidate: every 10 minutes
        // php artisan attendance:consolidate --since="YYYY-MM-DD 00:00:00"
        //                                     --until="YYYY-MM-DD HH:MM:SS"
        //                                     --mode=sequence
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

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
