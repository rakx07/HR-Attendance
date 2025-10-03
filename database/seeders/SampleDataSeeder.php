<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Assuming ShiftWindowSeeder created at least one record with id=1
        $defaultShiftId = 1;

        $samples = [
            [
                'email'          => 'employee1@example.com',
                'first_name'     => 'Employee',
                'middle_name'    => null,
                'last_name'      => 'One',
                'password'       => Hash::make('Employee123!'),
                'zkteco_user_id' => 101,
                'shift_window_id'=> $defaultShiftId,
                'department'     => 'IT',
                'active'         => true,
                'role'           => 'Employee',
            ],
            [
                'email'          => 'employee2@example.com',
                'first_name'     => 'Employee',
                'middle_name'    => 'A.',
                'last_name'      => 'Two',
                'password'       => Hash::make('Employee123!'),
                'zkteco_user_id' => 102,
                'shift_window_id'=> $defaultShiftId,
                'department'     => 'HR',
                'active'         => true,
                'role'           => 'Employee',
            ],
            [
                'email'          => 'employee3@example.com',
                'first_name'     => 'Juan',
                'middle_name'    => 'Santos',
                'last_name'      => 'Dela Cruz',
                'password'       => Hash::make('Employee123!'),
                'zkteco_user_id' => 103,
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
                    'zkteco_user_id'  => $u['zkteco_user_id'] ?? null,
                    'shift_window_id' => $u['shift_window_id'] ?? null,
                    'department'      => $u['department'] ?? null,
                    'active'          => $u['active'] ?? true,
                ]
            );

            if (!empty($u['role']) && ! $user->hasRole($u['role'])) {
                $user->syncRoles([$u['role']]);
            }
        }
    }
}
