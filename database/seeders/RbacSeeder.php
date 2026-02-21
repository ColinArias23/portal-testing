<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ✅ Use web guard (Sanctum typically authenticates as web guard)
        $guard = 'web';

        $permissions = [
            'dashboard.view',

            'departments.view',
            'departments.manage',

            'divisions.view',
            'divisions.manage',

            'employees.view',
            'employees.manage',

            'plantilla_items.view',
            'plantilla_items.manage',

            'salary_grades.view',
            'salary_grades.manage',

            'step_increments.view',
            'step_increments.manage',

            'item_numbers.view',
            'item_numbers.manage',

            'users.view',
            'users.manage',

            'manpower.view',

            'roles.manage',
            'permissions.manage',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name'       => $name,
                'guard_name' => $guard,
            ]);
        }

        // Roles
        $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => $guard]);
        $admin      = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guard]);
        $hr         = Role::firstOrCreate(['name' => 'HR', 'guard_name' => $guard]);
        $employee   = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => $guard]);

        // SuperAdmin = all permissions
        $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->get());

        // Admin
        $admin->syncPermissions(
            'dashboard.view',
            'manpower.view',
        );

        // HR
        $hr->syncPermissions(
            'manpower.view',
        );

        // Employee
        $employee->syncPermissions(
            'dashboard.view',
        );

        // Assign roles by email (only if they exist)
        $this->assignRoleByEmail('superadmin@local.test', 'SuperAdmin');
        $this->assignRoleByEmail('admin@local.test', 'Admin');
        $this->assignRoleByEmail('hr@local.test', 'HR');
        $this->assignRoleByEmail('employee@local.test', 'Employee');

        // ✅ clear permission cache again to be safe
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function assignRoleByEmail(string $email, string $role): void
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            // make sure roles are attached with correct guard
            $user->syncRoles([$role]);
        }
    }
}
