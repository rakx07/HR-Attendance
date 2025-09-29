<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'users.manage','device.manage','schedules.manage',
            'reports.view.org','reports.export','team.approve','self.view'
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $roles = [
            'IT Admin'      => ['users.manage','device.manage','schedules.manage','reports.view.org','reports.export'],
            'HR Officer'    => ['schedules.manage','reports.view.org','reports.export'],
            'Supervisor'    => ['team.approve','reports.export'],
            'Employee'      => ['self.view'],
            'Administrator' => $permissions,
        ];

        foreach ($roles as $roleName => $give) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($give);
        }
    }
}
