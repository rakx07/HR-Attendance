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
            // general
            'reports.view.org','reports.export',
            // HR
            'employees.create','employees.update','employees.import',
            'schedules.manage',
            'attendance.edit' // allow editing daily in/out with audit
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $roles = [
            'GIA Staff'     => ['reports.view.org','reports.export'],
            'HR Officer'    => [
                'reports.view.org','reports.export',
                'employees.create','employees.update','employees.import',
                'schedules.manage','attendance.edit'
            ],
            'IT Admin'      => array_unique($permissions),
            'Employee'      => [], // can add 'self.view' if you later add a self portal
            'Administrator' => array_unique($permissions),
        ];

        foreach ($roles as $roleName => $give) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($give);
        }
    }
}
