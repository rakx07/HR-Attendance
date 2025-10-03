<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Add the new permission here
        $permissions = [
            // general
            'reports.view.org',
            'reports.export',

            // HR
            'employees.create',
            'employees.update',
            'employees.import',
            'schedules.manage',
            'attendance.edit',

            // NEW
            'departments.manage',
        ];

        // create permissions if missing
        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name'       => $perm,
                'guard_name' => 'web',
            ]);
        }

        // Give roles their permissions
        $roles = [
            'GIA Staff' => [
                'reports.view.org',
                'reports.export',
            ],

            'HR Officer' => [
                'reports.view.org',
                'reports.export',
                'employees.create',
                'employees.update',
                'employees.import',
                'schedules.manage',
                'attendance.edit',
                'departments.manage', // NEW
            ],

            'IT Admin' => [
                'reports.view.org',
                'reports.export',
                'employees.create',
                'employees.update',
                'employees.import',
                'schedules.manage',
                'attendance.edit',
                'departments.manage', // NEW
            ],

            'Employee' => [
                // keep empty for now
            ],

            'Administrator' => [
                'reports.view.org',
                'reports.export',
                'employees.create',
                'employees.update',
                'employees.import',
                'schedules.manage',
                'attendance.edit',
                'departments.manage', // NEW
            ],
        ];

        foreach ($roles as $roleName => $give) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions(array_unique($give));
        }
    }
}
