<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

class RbacSeeder extends Seeder
{
    private const GUARD = 'web';

        private const PERMISSIONS = [
        // Dashboard
        'dashboard.view',

        // Recruitment, Selection & Placement — Hiring (general/calendar)
        'hiring.view',
        'hiring.manage',

        // Recruitment — Plantilla (PSB) postings
        'hiring.plantilla.postings.view',
        'hiring.plantilla.postings.manage',

        // Recruitment — Plantilla (PSB) applications/interviews
        'hiring.plantilla.applications.view',
        'hiring.plantilla.applications.manage',

        // Recruitment — Plantilla (PSB) onboarding
        'hiring.plantilla.onboarding.view',
        'hiring.plantilla.onboarding.manage',

        // Recruitment — Non-Plantilla (external) applicants
        'hiring.nonplantilla.applicants.view',
        'hiring.nonplantilla.applicants.manage',

        // Recruitment — Non-Plantilla applications
        'hiring.nonplantilla.applications.view',
        'hiring.nonplantilla.applications.manage',

        // Recruitment — Non-Plantilla onboarding
        'hiring.nonplantilla.onboarding.view',
        'hiring.nonplantilla.onboarding.manage',

        // Leave
        'leave.dashboard.view',
        'leave.approval.view',
        'leave.approval.manage',
        'leave.balance.view',
        'leave.balance.manage',
        'leave.calendar.view',
        'leave.request.view',
        'leave.request.manage',
        'leave.holidays.manage',
        'leave.types.manage',
        'leave.suspensions.manage',

        // Employees
        'employees.view',
        'employees.manage',

        // Renewals
        'renewals.view',
        'renewals.manage',

        // Team / Orientation
        'team.view',
        'orientation.view',

        // Learning & Development
        'trainings.view',
        'trainings.manage',
        'trainings.join',
        'trainings.skill_gap',
        'trainings.effectiveness',
        'trainings.coaching',

        // SPMS
        'spms.view',
        'spms.manage',

        // Compensation & Benefits
        'compensation.view',
        'compensation.manage',

        // System Management — accounts, departments, plantilla items
        'accounts.view',
        'accounts.manage',
        'departments.view',
        'departments.manage',
        'plantilla_items.view',
        'plantilla_items.manage',
        'positions.view',
        'roles.view',
        'roles.manage',

        // Rewards & Recognition
        'rewards.view',

        // Analytics
        'analytics.view',

        // Announcements
        'announcements.view',
        'announcements.manage',
        'announcements.manage.all',

        // Manpower Mapping
        'manpower.view',
        'manpower.manage',

        // Task Monitoring
        'task_monitoring.view',

        // Users (RBAC administration)
        'users.view',
        'users.manage',
        'users.manage.roles',

        // Notifications
        'notifications.view',

        // Audit Logs
        'audit_logs.view',

        // DTR
        'dtr.view',

        // Bug Reports
        'bug-reports.view',

        // AI Assistant
        'ai_chat.use',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->syncPermissionCatalog();
        $this->reportStalePermissions();

        $roles = $this->createRoles();
        $this->stripRolePermissions($roles);
        $this->assignUsersToRoles();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncPermissionCatalog(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name'       => $name,
                'guard_name' => self::GUARD,
            ]);
        }
    }

    private function reportStalePermissions(): void
    {
        $stale = Permission::where('guard_name', self::GUARD)
            ->whereNotIn('name', self::PERMISSIONS)
            ->pluck('name');

        if ($stale->isNotEmpty()) {
            $message = 'RbacSeeder: stale permissions found in DB but not in canonical list: '
                . $stale->implode(', ');

            Log::warning($message);
            $this->command?->warn($message);
            $this->command?->warn('Run "php artisan rbac:prune-permissions --dry-run" to review before removing.');
        }
    }

    private function createRoles(): array
    {
        return [
            'SuperAdmin' => Role::firstOrCreate(['name' => 'SuperAdmin',           'guard_name' => self::GUARD]),
            'Admin'      => Role::firstOrCreate(['name' => 'Admin',                'guard_name' => self::GUARD]),
            'Chairman'   => Role::firstOrCreate(['name' => 'Chairman',             'guard_name' => self::GUARD]),
            'Director'   => Role::firstOrCreate(['name' => 'Director',             'guard_name' => self::GUARD]),
            'HR'         => Role::firstOrCreate(['name' => 'HR',                   'guard_name' => self::GUARD]),
            'Head'       => Role::firstOrCreate(['name' => 'Head',                 'guard_name' => self::GUARD]),
            'OIC'        => Role::firstOrCreate(['name' => 'Officer In Charge',    'guard_name' => self::GUARD]),
            'Supervisor' => Role::firstOrCreate(['name' => 'Supervisor',           'guard_name' => self::GUARD]),
            'Staff'      => Role::firstOrCreate(['name' => 'Staff',                'guard_name' => self::GUARD]),
            'MCC'        => Role::firstOrCreate(['name' => 'Medical Center Chief', 'guard_name' => self::GUARD]),
        ];
    }

    private function stripRolePermissions(array $roles): void
    {   
        foreach ($roles as $name => $role) {
            if ($name === 'SuperAdmin') {
                $role->syncPermissions(self::PERMISSIONS);
            } else {
                $role->syncPermissions([]);
            }
        }
}

    private function assignUsersToRoles(): void
    {
        $this->assignRole('SUPERADMIN', 'SuperAdmin');
        $this->assignRole('ADMIN',      'Admin');
        $this->assignRole('DIRECTOR',   'Director');
        $this->assignRole('CHAIRMAN',   'Chairman');
        $this->assignRole('HR',         'HR');
        $this->assignRole('OIC',        'Officer In Charge');
        $this->assignRole('HEAD',       'Head');
        $this->assignRole('SUPERVISOR', 'Supervisor');
        $this->assignRole('STAFF',      'Staff');
        $this->assignRole('MCC',        'Medical Center Chief');
    }

    private function assignRole(string $username, string $role): void
    {
        $user = User::where('username', $username)->first();
        if (! $user) {
            return;
        }

        $user->syncRoles([$role]);
    }
}