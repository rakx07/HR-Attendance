<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'first_name'  => 'System',
                'last_name'   => 'Administrator',
                'middle_name' => null,
                'email'       => 'admin@example.com',
                'password'    => Hash::make('ChangeMe123!'),
                'role'        => 'Administrator',
            ],
            [
                'first_name'  => 'HR',
                'last_name'   => 'Officer',
                'middle_name' => null,
                'email'       => 'hr@example.com',
                'password'    => Hash::make('WelcomeHR123!'),
                'role'        => 'HR Officer',
            ],
            [
                'first_name'  => 'IT',
                'last_name'   => 'Admin',
                'middle_name' => null,
                'email'       => 'itadmin@example.com',
                'password'    => Hash::make('ItAdmin123!'),
                'role'        => 'IT Admin',
            ],
            [
                'first_name'  => 'GIA',
                'last_name'   => 'Staff',
                'middle_name' => null,
                'email'       => 'gia@example.com',
                'password'    => Hash::make('GiaStaff123!'),
                'role'        => 'GIA Staff',
            ],
            [
                'first_name'  => 'Employee',
                'last_name'   => 'User',
                'middle_name' => null,
                'email'       => 'employee@example.com',
                'password'    => Hash::make('Employee123!'),
                'role'        => 'Employee',
            ],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'first_name'  => $u['first_name'],
                    'last_name'   => $u['last_name'],
                    'middle_name' => $u['middle_name'],
                    'password'    => $u['password'],
                ]
            );

            if (! $user->hasRole($u['role'])) {
                $user->syncRoles([$u['role']]);
            }
        }
    }
}
