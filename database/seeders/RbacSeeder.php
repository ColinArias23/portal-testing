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

        $guard = 'web';

        $permissions = [
            'dashboard.view',
            'hiring.view',
            'hiring.manage',
            'leave.view',
            'leave.manage',
            'leave.approve',
            'employees.view',
            'employees.manage',
            'team.view',
            'orientation.view',
            'trainings.view',
            'trainings.manage',
            'trainings.join',
            'trainings.skill_gap',
            'trainings.effectiveness',
            'spms.view',
            'spms.manage',
            'compensation.view',
            'accounts.view',
            'accounts.manage',
            'departments.view',
            'departments.manage',
            'plantilla_items.view',
            'plantilla_items.manage',
            'positions.view',
            'rewards.view',
            'analytics.view',
            'announcements.view',
            'announcements.manage',
            'manpower.view',
            'manpower.manage',
            'bug-reports.view',
            'users.view',
            'users.manage',
            'notifications.view',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name'       => $name,
                'guard_name' => $guard,
            ]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => $guard]);
        $admin      = Role::firstOrCreate(['name' => 'Admin',      'guard_name' => $guard]);
        $director   = Role::firstOrCreate(['name' => 'Director',   'guard_name' => $guard]);
        $hr         = Role::firstOrCreate(['name' => 'HR',         'guard_name' => $guard]);
        $head       = Role::firstOrCreate(['name' => 'Head',       'guard_name' => $guard]);
        $supervisor = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => $guard]);
        $staff      = Role::firstOrCreate(['name' => 'Staff',      'guard_name' => $guard]);

        $allPermissions = Permission::where('guard_name', $guard)->get();

        $superAdmin->syncPermissions($allPermissions);
        $admin->syncPermissions($allPermissions);
        $hr->syncPermissions($allPermissions);

        $director->syncPermissions([
            'dashboard.view',
            'hiring.view',
            'leave.view',
            'leave.approve',
            'employees.view',
            'team.view',
            'orientation.view',
            'trainings.view',
            'trainings.join',
            'trainings.skill_gap',
            'trainings.effectiveness',
            'spms.view',
            'spms.manage',
            'compensation.view',
            'accounts.view',
            'departments.view',
            'plantilla_items.view',
            'positions.view',
            'rewards.view',
            'analytics.view',
            'announcements.view',
            'announcements.manage',
            'manpower.view',
            'manpower.manage',
            'bug-reports.view',
            'notifications.view',
        ]);

        $head->syncPermissions([
            'dashboard.view',
            'hiring.view',
            'leave.view',
            'leave.approve',
            'employees.view',
            'team.view',
            'orientation.view',
            'trainings.view',
            'trainings.join',
            'trainings.skill_gap',
            'trainings.effectiveness',
            'spms.view',
            'spms.manage',
            'compensation.view',
            'accounts.view',
            'departments.view',
            'plantilla_items.view',
            'positions.view',
            'rewards.view',
            'analytics.view',
            'announcements.view',
            'announcements.manage',
            'manpower.view',
            'bug-reports.view',
            'notifications.view',
        ]);

        $supervisor->syncPermissions([
            'dashboard.view',
            'hiring.view',
            'leave.view',
            'leave.approve',
            'employees.view',
            'team.view',
            'orientation.view',
            'trainings.view',
            'trainings.join',
            'trainings.skill_gap',
            'trainings.effectiveness',
            'spms.view',
            'spms.manage',
            'compensation.view',
            'announcements.view',
            'announcements.manage',
            'rewards.view',
            'bug-reports.view',
            'notifications.view',
        ]);

        $staff->syncPermissions([
            'leave.view',
            'team.view',
            'orientation.view',
            'trainings.view',
            'trainings.join',
            'spms.view',
            'spms.manage',
            'announcements.view',
            'announcements.manage',
            'notifications.view',
        ]);

        $this->assignRole('SUPERADMIN', 'SuperAdmin');
        $this->assignRole('ADMIN',      'Admin');
        $this->assignRole('HR',         'HR');
        $this->assignRole('STAFF',      'Staff');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function assignRole(string $username, string $role): void
    {
        $user = User::where('username', $username)->first();
        if ($user) {
            $user->syncRoles([$role]);
        }
    }
}