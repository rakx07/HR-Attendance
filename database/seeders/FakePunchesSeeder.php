<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use App\Models\User;

class FakePunchesSeeder extends Seeder
{
    /**
     * Seeds COMPLETE days only:
     *  - clears existing seeded punches in the window (to avoid partials)
     *  - writes 4 punches per user per day (AM In/Out, PM In/Out)
     *  - by default seeds up to "yesterday" so today never looks partial
     */
    public function run(): void
    {
        // ---- CONFIG ----
        $tz         = config('app.timezone', 'UTC');      // display/local TZ
        $deviceSn   = 'TESTDEVICE';
        $sourceFlag = 'seed';                             // stored in attendance_raw.source

        // Seed from this date (inclusive) up to yesterday (inclusive)
        $start = CarbonImmutable::create(2025, 10, 2, 0, 0, 0, $tz);
        $end   = CarbonImmutable::now($tz)->subDay()->startOfDay();

        // If start > end (e.g., you run early on the 1st), nothing to do
        if ($start->gt($end)) {
            $this->command->warn('FakePunchesSeeder: window is empty; nothing to seed.');
            return;
        }

        // Users we’ll seed for (must be mapped to device IDs)
        $users = User::query()
            ->whereNotNull('zkteco_user_id')
            ->get(['id','zkteco_user_id']);

        if ($users->isEmpty()) {
            $this->command->warn('FakePunchesSeeder: no users with zkteco_user_id.');
            return;
        }

        // 1) Wipe existing seeded punches for the window so we only keep complete sets
        DB::table('attendance_raw')
            ->whereBetween('punched_at', [$start->toDateTimeString(), $end->endOfDay()->toDateTimeString()])
            ->where('device_sn', $deviceSn)
            ->where('source', $sourceFlag)
            ->delete();

        // 2) Insert 4 punches per user per day
        $rows = [];
        $now  = now();
        $cursor = $start;

        while ($cursor->lte($end)) {
            foreach ($users as $u) {
                // Use fixed times (no randomness) so consolidation is predictable
                $amIn  = $cursor->setTime(8,  0, 0);
                $amOut = $cursor->setTime(12, 0, 0);
                $pmIn  = $cursor->setTime(13, 0, 0);
                $pmOut = $cursor->setTime(17, 0, 0);

                foreach ([$amIn, $amOut, $pmIn, $pmOut] as $ts) {
                    $rows[] = [
                        'device_user_id' => (string)$u->zkteco_user_id,
                        'user_id'        => $u->id,               // helpful, but consolidator will join anyway
                        'punched_at'     => $ts->toDateTimeString(),
                        'device_sn'      => $deviceSn,
                        'source'         => $sourceFlag,          // matches your migration (string up to 20)
                        'punch_type'     => null,                 // keep null; your consolidator orders by time
                        'payload'        => null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }
            $cursor = $cursor->addDay();
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            // idempotent on (device_user_id, punched_at, device_sn)
            DB::table('attendance_raw')->upsert(
                $chunk,
                ['device_user_id', 'punched_at', 'device_sn'],
                ['user_id', 'source', 'punch_type', 'payload', 'updated_at']
            );
        }

        // 3) Backfill any missing user_id, just in case
        DB::statement("
            UPDATE attendance_raw ar
            JOIN users u ON u.zkteco_user_id = ar.device_user_id
            SET ar.user_id = u.id
            WHERE ar.user_id IS NULL
        ");

        $this->command->info('FakePunchesSeeder: complete 4-punch days seeded and old seeded data in window cleared.');
    }
}
