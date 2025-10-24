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
         * BioTime import — every 1 hour
         */
        $schedule->call(function () use ($tz) {
            $from = Carbon::now($tz)->toDateString();
            Artisan::call('biotime:import', [
                '--from'    => $from,
                '--to'      => $from,
                '--summary' => true,
            ]);
        })
        ->name('biotime-import-today')
        ->hourly() // ← CHANGED HERE
        ->withoutOverlapping(60)
        ->appendOutputTo(storage_path('logs/schedule_biotime_import.log'));

        /**
         * Attendance consolidate — every 1 hour
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
        ->name('attendance-consolidate-today')
        ->hourly() // ← CHANGED HERE
        ->withoutOverlapping(60)
        ->appendOutputTo(storage_path('logs/schedule_consolidate.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
