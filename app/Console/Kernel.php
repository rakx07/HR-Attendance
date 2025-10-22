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
        // Make the scheduler run in Manila time
        $tz = config('app.timezone', 'Asia/Manila');
        $schedule->timezone($tz);

        /**
         * BioTime import — every 10 minutes
         * Correct: pass --summary as a BOOLEAN FLAG via Artisan::call (no "=1").
         * Range: today only (from/to = YYYY-MM-DD)
         */
        $schedule->call(function () use ($tz) {
            $from = Carbon::now($tz)->toDateString(); // e.g. 2025-10-22
            // Equivalent to: php artisan biotime:import --from=YYYY-MM-DD --to=YYYY-MM-DD --summary
            Artisan::call('biotime:import', [
                '--from'    => $from,
                '--to'      => $from,
                '--summary' => true,   // flag, no value
            ]);
        })
        ->everyTenMinutes()
        ->withoutOverlapping(10)
        ->appendOutputTo(storage_path('logs/schedule_biotime_import.log'));

        /**
         * Attendance consolidate — every 10 minutes
         * Range: 00:00:00 today → NOW (freshly computed each tick)
         */
        $schedule->call(function () use ($tz) {
            $now   = Carbon::now($tz);
            $since = $now->copy()->startOfDay()->format('Y-m-d H:i:s');
            $until = $now->format('Y-m-d H:i:s');

            // Equivalent to:
            // php artisan attendance:consolidate --since="YYYY-MM-DD 00:00:00" --until="YYYY-MM-DD HH:MM:SS" --mode=sequence
            Artisan::call('attendance:consolidate', [
                '--since' => $since,
                '--until' => $until,
                '--mode'  => 'sequence',
            ]);
        })
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
