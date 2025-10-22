<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Run schedules in Manila time
        $tz = config('app.timezone', 'Asia/Manila');
        $schedule->timezone($tz);

        /**
         * BioTime import — every 10 minutes
         * Equivalent to:
         *   php artisan biotime:import --from=YYYY-MM-DD --to=YYYY-MM-DD --summary
         * Note: --summary is a flag (no value).
         */
        $schedule->call(function () use ($tz) {
            $from = Carbon::now($tz)->toDateString(); // today (YYYY-MM-DD)
            Artisan::call('biotime:import', [
                '--from'    => $from,
                '--to'      => $from,
                '--summary' => true,   // boolean flag (no value)
            ]);
        })
        ->name('biotime-import-today')           // ← REQUIRED when using withoutOverlapping on closures
        ->everyTenMinutes()
        ->withoutOverlapping(10)
        ->appendOutputTo(storage_path('logs/schedule_biotime_import.log'));

        /**
         * Attendance consolidate — every 10 minutes
         * Equivalent to:
         *   php artisan attendance:consolidate --since="YYYY-MM-DD 00:00:00" --until="YYYY-MM-DD HH:MM:SS" --mode=sequence
         * Range: start of today → now (recomputed each tick).
         */
        $schedule->call(function () use ($tz) {
            $now   = Carbon::now($tz);
            $since = $now->copy()->startOfDay()->format('Y-m-d H:i:s');
            $until = $now->format('Y-m-d H:i:s');

            Artisan::call('attendance:consolidate', [
                '--since' => $since,
                '--until' => $until,
                '--mode'  => 'sequence',
            ]);
        })
        ->name('attendance-consolidate-today')   // ← REQUIRED name
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
