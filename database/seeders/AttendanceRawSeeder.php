<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceRawSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            // Juan 2019545 (from your zkteco_users)
            ['device_user_id' => 2019545, 'punched_at' => $now->copy()->subHours(8), 'device_sn' => 'TESTDEVICE', 'source' => 'manual'],
            ['device_user_id' => 2019545, 'punched_at' => $now->copy()->subHours(4), 'device_sn' => 'TESTDEVICE', 'source' => 'manual'],

            // Maria 2001856
            ['device_user_id' => 2001856, 'punched_at' => $now->copy()->subHours(8)->subMinutes(5), 'device_sn' => 'TESTDEVICE', 'source' => 'manual'],
            ['device_user_id' => 2001856, 'punched_at' => $now->copy()->subHours(4)->subMinutes(10),'device_sn' => 'TESTDEVICE', 'source' => 'manual'],
        ];

        foreach ($rows as $r) {
            DB::table('attendance_raw')->updateOrInsert(
                ['device_user_id' => $r['device_user_id'], 'punched_at' => $r['punched_at'], 'device_sn' => $r['device_sn']],
                $r + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        // Backfill new rows’ user_id (in case this seeder runs after migration)
        DB::statement("
            UPDATE attendance_raw ar
            LEFT JOIN users u ON u.zkteco_user_id = ar.device_user_id
            SET ar.user_id = u.id
            WHERE ar.user_id IS NULL
        ");
    }
}
