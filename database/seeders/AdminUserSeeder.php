<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrator', 'password' => bcrypt('ChangeMe123!')]
        );

        if (!$user->hasRole('Administrator')) {
            $user->assignRole('Administrator');
        }
    }
}
