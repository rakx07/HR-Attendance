<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ShiftWindowSeeder::class,
            AdminUserSeeder::class,
            SampleDataSeeder::class,
            ZktecoUsersSeeder::class,
            AttendanceRawSeeder::class,
            FakePunchesSeeder::class,
            ShiftPresetsSeeder::class,
            HolidaysPermissionSeeder::class,
        ]);
    }
}
