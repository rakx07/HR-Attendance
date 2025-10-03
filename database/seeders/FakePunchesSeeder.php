<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;

class FakePunchesSeeder extends Seeder
{
    /**
     * Seed 4 punches per day (AM in/out, PM in/out) for users that have zkteco_user_id.
     * Adjust $start/$end to your needs.
     */
    public function run(): void
    {
        // Choose the window to seed
        $start = Carbon::create(2025, 10, 2); // inclusive
        $end   = Carbon::create(2025, 10, 2); // inclusive (same day for your demo)

        $deviceSn = 'TESTDEVICE';
        $now = now();

        // Pick users that have a mapped device ID
        $users = User::query()
            ->whereNotNull('zkteco_user_id')
            ->get(['id', 'zkteco_user_id']);

        if ($users->isEmpty()) {
            $this->command->warn('No users with zkteco_user_id found; nothing to seed.');
            return;
        }

        $rows = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            foreach ($users as $u) {
                // Build 4 punches: 08:00 (in), 12:00 (out), 13:00 (in), 17:00 (out)
                // Add small randomness so upserts don't collide if rerun with identical times.
                $amIn   = $d->copy()->setTime(8, 0)->addMinutes(random_int(0, 3));
                $amOut  = $d->copy()->setTime(12, 0)->addMinutes(random_int(0, 3));
                $pmIn   = $d->copy()->setTime(13, 0)->addMinutes(random_int(0, 3));
                $pmOut  = $d->copy()->setTime(17, 0)->addMinutes(random_int(0, 3));

                foreach ([$amIn, $amOut, $pmIn, $pmOut] as $ts) {
                    $rows[] = [
                        'device_user_id' => (string)$u->zkteco_user_id,
                        'user_id'        => $u->id,          // helpful but not required
                        'punched_at'     => $ts->toDateTimeString(),
                        'device_sn'      => $deviceSn,
                        'source'         => 'seed',
                        'punch_type'     => null,
                        'payload'        => null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }
        }

        // Idempotent write: upsert by (device_user_id, punched_at, device_sn)
        if (!empty($rows)) {
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('attendance_raw')->upsert(
                    $chunk,
                    ['device_user_id', 'punched_at', 'device_sn'],
                    ['user_id', 'source', 'punch_type', 'payload', 'updated_at']
                );
            }
        }

        // Backfill user_id if any nulls remained
        DB::statement("
            UPDATE attendance_raw ar
            JOIN users u ON u.zkteco_user_id = ar.device_user_id
            SET ar.user_id = u.id
            WHERE ar.user_id IS NULL
        ");

        $this->command->info('Fake punches seeded (4 per day per user).');
    }
}
