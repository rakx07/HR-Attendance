<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZktecoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $deviceUsers = [
            [
                'device_user_id' => 2019545, // school ID
                'name'           => 'Juan Dela Cruz',
                'card_no'        => '1001',
                'privilege'      => 0,
                'enabled'        => 1,
                'device_sn'      => 'TESTDEVICE',
                'pulled_at'      => $now,
                'raw'            => json_encode(['face'=>1]),
            ],
            [
                'device_user_id' => 2001856,
                'name'           => 'Maria Santos',
                'card_no'        => '1002',
                'privilege'      => 0,
                'enabled'        => 1,
                'device_sn'      => 'TESTDEVICE',
                'pulled_at'      => $now,
                'raw'            => json_encode(['face'=>1]),
            ],
        ];

        foreach ($deviceUsers as $u) {
            DB::table('zkteco_users')->updateOrInsert(
                ['device_user_id' => $u['device_user_id'], 'device_sn' => $u['device_sn']],
                $u + ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
