<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceRawSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $rows = [];

        // Generate 5 days of punches for both users
        for ($i = 4; $i >= 0; $i--) { // last 5 days (including today)
            $day = $now->copy()->subDays($i)->startOfDay();

            // === Juan (2019545) ===
            $rows[] = ['device_user_id' => 2019545, 'punched_at' => $day->copy()->setTime(8,  random_int(0, 5)),  'device_sn' => 'TESTDEVICE', 'source' => 'manual'];
            $rows[] = ['device_user_id' => 2019545, 'punched_at' => $day->copy()->setTime(12, random_int(0, 5)),  'device_sn' => 'TESTDEVICE', 'source' => 'manual'];
            $rows[] = ['device_user_id' => 2019545, 'punched_at' => $day->copy()->setTime(13, random_int(0, 5)),  'device_sn' => 'TESTDEVICE', 'source' => 'manual'];
            $rows[] = ['device_user_id' => 2019545, 'punched_at' => $day->copy()->setTime(17, random_int(0, 5)),  'device_sn' => 'TESTDEVICE', 'source' => 'manual'];

            // === Maria (2001856) ===
            $rows[] = ['device_user_id' => 2001856, 'punched_at' => $day->copy()->setTime(8,  random_int(0, 5)),  'device_sn' => 'TESTDEVICE', 'source' => 'manual'];
            $rows[] = ['device_user_id' => 2001856, 'punched_at' => $day->copy()->setTime(12, random_int(0, 5)),  'device_sn' => 'TESTDEVICE', 'source' => 'manual'];
            $rows[] = ['device_user_id' => 2001856, 'punched_at' => $day->copy()->setTime(13, random_int(0, 5)),  'device_sn' => 'TESTDEVICE', 'source' => 'manual'];
            $rows[] = ['device_user_id' => 2001856, 'punched_at' => $day->copy()->setTime(17, random_int(0, 5)),  'device_sn' => 'TESTDEVICE', 'source' => 'manual'];
        }

        foreach ($rows as $r) {
            DB::table('attendance_raw')->updateOrInsert(
                [
                    'device_user_id' => $r['device_user_id'],
                    'punched_at'     => $r['punched_at'],
                    'device_sn'      => $r['device_sn']
                ],
                $r + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        // Backfill user_id from users.zkteco_user_id
        DB::statement("
            UPDATE attendance_raw ar
            JOIN users u ON u.zkteco_user_id = ar.device_user_id
            SET ar.user_id = u.id
            WHERE ar.user_id IS NULL
        ");

        echo "✅ Seeded 5 days of attendance punches (".count($rows)." total) for users 2019545 (Juan) and 2001856 (Maria).\n";
    }
}
