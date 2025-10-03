<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $defaultShiftId = 1; // created by ShiftWindowSeeder

        $samples = [
            // Match your mock device user 2019545 (Juan)
            [
                'email'          => 'employee1@example.com',
                'first_name'     => 'Juan',
                'middle_name'    => 'Santos',
                'last_name'      => 'Dela Cruz',
                'password'       => Hash::make('Employee123!'),
                'zkteco_user_id' => 2019545,            // ⬅️ must match attendance_raw.device_user_id
                'shift_window_id'=> $defaultShiftId,
                'department'     => 'IT',
                'active'         => true,
                'role'           => 'Employee',
            ],
            // Match your mock device user 2001856 (Maria)
            [
                'email'          => 'employee2@example.com',
                'first_name'     => 'Maria',
                'middle_name'    => null,
                'last_name'      => 'Santos',
                'password'       => Hash::make('Employee123!'),
                'zkteco_user_id' => 2001856,            // ⬅️ must match attendance_raw.device_user_id
                'shift_window_id'=> $defaultShiftId,
                'department'     => 'HR',
                'active'         => true,
                'role'           => 'Employee',
            ],
            // A third sample with no device mapping yet (will not consolidate until mapped)
            [
                'email'          => 'employee3@example.com',
                'first_name'     => 'Employee',
                'middle_name'    => null,
                'last_name'      => 'Three',
                'password'       => Hash::make('Employee123!'),
                'zkteco_user_id' => null,
                'shift_window_id'=> $defaultShiftId,
                'department'     => 'Finance',
                'active'         => true,
                'role'           => 'Employee',
            ],
        ];

        foreach ($samples as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'first_name'      => $u['first_name'],
                    'middle_name'     => $u['middle_name'],
                    'last_name'       => $u['last_name'],
                    'password'        => $u['password'],
                    'zkteco_user_id'  => $u['zkteco_user_id'],
                    'shift_window_id' => $u['shift_window_id'],
                    'department'      => $u['department'],
                    'active'          => $u['active'],
                ]
            );

            if (!empty($u['role'])) {
                $user->syncRoles([$u['role']]);
            }
        }
    }
}
