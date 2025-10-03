<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class HolidaysPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // create or get the permission
        $perm = Permission::firstOrCreate([
            'name' => 'holidays.manage',
            'guard_name' => 'web',
        ]);

        // ensure the roles exist, then give the permission
        foreach (['Administrator','IT Admin','HR Officer'] as $name) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if (! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }
    }
}
