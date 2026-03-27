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
            'employees.view',
            'employees.manage',
            'analytics.view',
            'trainings.view',
            'trainings.manage',
            'trainings.join',
            'team.view',
            'leave.view',
            'leave.manage',
            'leave.approve',
            'accounts.view',
            'accounts.manage',
            'departments.view',
            'departments.manage',
            'plantilla_items.view',
            'plantilla_items.manage',
            'hiring.view',
            'hiring.manage',
            'announcements.view',
            'announcements.manage',
            'manpower.view',
            'manpower.manage',
            'spms.view',
            'spms.manage',
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

        // ── Roles ──────────────────────────────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => $guard]);
        $admin      = Role::firstOrCreate(['name' => 'Admin',      'guard_name' => $guard]);
        $director   = Role::firstOrCreate(['name' => 'Director',   'guard_name' => $guard]);
        $hr         = Role::firstOrCreate(['name' => 'HR',         'guard_name' => $guard]);
        $head       = Role::firstOrCreate(['name' => 'Head',       'guard_name' => $guard]);
        $supervisor = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => $guard]);
        $staff      = Role::firstOrCreate(['name' => 'Staff',      'guard_name' => $guard]);

        $allPermissions = Permission::where('guard_name', $guard)->get();

        // ── SuperAdmin — all permissions ───────────────────────────────────
        $superAdmin->syncPermissions($allPermissions);

        // ── Admin — all permissions ────────────────────────────────────────
        $admin->syncPermissions($allPermissions);

        // ── Director ───────────────────────────────────────────────────────
        $director->syncPermissions([
            'dashboard.view',
            'employees.view',
            'analytics.view',
            'trainings.view',
            'trainings.join',
            'team.view',
            'leave.view',
            'leave.approve',
            'accounts.view',
            'departments.view',
            'plantilla_items.view',
            'hiring.view',
            'announcements.view',
            'announcements.manage',
            'manpower.view',
            'manpower.manage',
            'spms.view',
            'spms.manage',
            'notifications.view',
        ]);

        // ── HR ─────────────────────────────────────────────────────────────
        $hr->syncPermissions([
            'dashboard.view',
            'employees.view',
            'employees.manage',
            'analytics.view',
            'trainings.view',
            'trainings.manage',
            'trainings.join',
            'team.view',
            'leave.view',
            'leave.manage',
            'leave.approve',
            'accounts.view',
            'accounts.manage',
            'departments.view',
            'departments.manage',
            'plantilla_items.view',
            'plantilla_items.manage',
            'hiring.view',
            'hiring.manage',
            'announcements.view',
            'announcements.manage',
            'manpower.view',
            'manpower.manage',
            'spms.view',
            'spms.manage',
            'users.view',
            'users.manage',
            'notifications.view',
        ]);

        // ── Head ───────────────────────────────────────────────────────────
        $head->syncPermissions([
            'dashboard.view',
            'employees.view',
            'analytics.view',
            'trainings.view',
            'trainings.join',
            'team.view',
            'leave.view',
            'leave.approve',
            'accounts.view',
            'departments.view',
            'plantilla_items.view',
            'hiring.view',
            'announcements.view',
            'announcements.manage',
            'manpower.view',
            'spms.view',
            'spms.manage',
            'notifications.view',
        ]);

        // ── Supervisor ─────────────────────────────────────────────────────
        $supervisor->syncPermissions([
            'dashboard.view',
            'employees.view',
            'trainings.view',
            'trainings.join',
            'team.view',
            'leave.view',
            'leave.approve',
            'announcements.view',
            'announcements.manage',
            'spms.view',
            'spms.manage',
            'notifications.view',
        ]);

        // ── Staff ──────────────────────────────────────────────────────────
        $staff->syncPermissions([
            'dashboard.view',
            'trainings.view',
            'trainings.join',
            'team.view',
            'leave.view',
            'announcements.view',
            'announcements.manage',
            'spms.view',
            'spms.manage',
            'notifications.view',
        ]);

        // ── Assign roles by email ──────────────────────────────────────────
        $this->assignRoleByEmail('superadmin@local.test', 'SuperAdmin');
        $this->assignRoleByEmail('admin@local.test',      'Admin');
        $this->assignRoleByEmail('hr@local.test',         'HR');
        $this->assignRoleByEmail('staff@local.test',      'Staff');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function assignRoleByEmail(string $email, string $role): void
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            $user->syncRoles([$role]);
        }
    }
}