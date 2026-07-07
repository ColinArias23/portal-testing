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

        // Recruitment, Selection & Placement
        'hiring.view',
        'hiring.manage',
        'hiring.plantilla.applicants.view',
        'hiring.plantilla.applicants.manage',
        'hiring.plantilla.applications.view',
        'hiring.plantilla.applications.manage',
        'hiring.plantilla.onboarding.view',
        'hiring.plantilla.onboarding.manage',
        'hiring.plantilla.positions.view',
        'hiring.plantilla.positions.manage',
        'hiring.nonplantilla.applicants.view',
        'hiring.nonplantilla.applicants.manage',
        'hiring.nonplantilla.applications.view',
        'hiring.nonplantilla.applications.manage',
        'hiring.nonplantilla.onboarding.view',
        'hiring.nonplantilla.onboarding.manage',
        'leave.dashboard.view',
        'leave.approval.view',
        'leave.approval.manage',
        'leave.balance.view',
        'leave.calendar.view',
        'leave.request.view',
        'leave.request.manage',
        'employees.view',
        'employees.manage',
        'renewals.view',
        'renewals.manage',
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

        // System Management
        'accounts.view',
        'accounts.manage',
        'departments.view',
        'departments.manage',
        'plantilla_items.view',
        'plantilla_items.manage',
        'positions.view',

        // Rewards & Recognition
        'rewards.view',

        // Analytics
        'analytics.view',

        // Announcements
        'announcements.view',
        'announcements.manage',

        // Manpower Mapping
        'manpower.view',
        'manpower.manage',

        // Task Monitoring
        'task_monitoring.view',

        // Users
        'users.view',
        'users.manage',

        // Notifications
        'notifications.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->syncPermissionCatalog();
        $this->reportStalePermissions();

        $roles = $this->createRoles();
        $this->assignRolePermissions($roles);
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
            'SuperAdmin' => Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => self::GUARD]),
            'Admin'      => Role::firstOrCreate(['name' => 'Admin',      'guard_name' => self::GUARD]),
            'Chairman'   => Role::firstOrCreate(['name' => 'Chairman',   'guard_name' => self::GUARD]),
            'Director'   => Role::firstOrCreate(['name' => 'Director',   'guard_name' => self::GUARD]),
            'HR'         => Role::firstOrCreate(['name' => 'HR',         'guard_name' => self::GUARD]),
            'Head'       => Role::firstOrCreate(['name' => 'Head',       'guard_name' => self::GUARD]),
            'OIC'        => Role::firstOrCreate(['name' => 'Officer In Charge', 'guard_name' => self::GUARD]),
            'Supervisor' => Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => self::GUARD]),
            'Staff'      => Role::firstOrCreate(['name' => 'Staff',      'guard_name' => self::GUARD]),
        ];
    }

    private function assignRolePermissions(array $roles): void
    {
        $allPermissions = Permission::where('guard_name', self::GUARD)->get();

        // ─── Full-access tier ────────────────────────────────────────────
        // SuperAdmin, Admin, and HR are the only roles permitted to manage
        // (create/update/delete/pin/archive) announcements, per explicit
        // business decision. They retain full system access otherwise.
        $roles['SuperAdmin']->syncPermissions($allPermissions);
        $roles['Admin']->syncPermissions($allPermissions);
        $roles['HR']->syncPermissions($allPermissions);

        // ─── Oversight tier (Chairman / Director / Head / OIC) ──────────
        // These roles can VIEW announcements (and react/comment/reply,
        // which require no separate permission — gated only by
        // auth:sanctum), but CANNOT create/update/delete/pin/archive.
        $roles['Chairman']->syncPermissions([
            'dashboard.view',
            'hiring.view',
            'hiring.plantilla.applicants.view',
            'hiring.plantilla.applications.view',
            'hiring.plantilla.onboarding.view',
            'hiring.plantilla.positions.view',
            'hiring.nonplantilla.applicants.view',
            'hiring.nonplantilla.applications.view',
            'hiring.nonplantilla.onboarding.view',
            'leave.dashboard.view',
            'leave.approval.view',
            'leave.approval.manage',
            'leave.balance.view',
            'leave.calendar.view',
            'leave.request.view',
            'employees.view',
            'renewals.view',
            'team.view',
            'orientation.view',
            'trainings.view',
            'trainings.join',
            'trainings.skill_gap',
            'trainings.effectiveness',
            'trainings.coaching',
            'spms.view',
            'spms.manage',
            'compensation.view',
            'accounts.view',
            'departments.view',
            'plantilla_items.view',
            'positions.view',
            'rewards.view',
            'analytics.view',
            'announcements.view', // manage removed — Chairman is read-only on announcements
            'manpower.view',
            'manpower.manage',
            'task_monitoring.view',
            'notifications.view',
        ]);

        $roles['Director']->syncPermissions([
            'dashboard.view',
            'hiring.view',
            'hiring.plantilla.applicants.view',
            'hiring.plantilla.applications.view',
            'hiring.plantilla.onboarding.view',
            'hiring.plantilla.positions.view',
            'hiring.nonplantilla.applicants.view',
            'hiring.nonplantilla.applications.view',
            'hiring.nonplantilla.onboarding.view',
            'leave.dashboard.view',
            'leave.approval.view',
            'leave.approval.manage',
            'leave.balance.view',
            'leave.calendar.view',
            'leave.request.view',
            'employees.view',
            'renewals.view',
            'team.view',
            'orientation.view',
            'trainings.view',
            'trainings.join',
            'trainings.skill_gap',
            'trainings.effectiveness',
            'trainings.coaching',
            'spms.view',
            'spms.manage',
            'compensation.view',
            'accounts.view',
            'departments.view',
            'plantilla_items.view',
            'positions.view',
            'rewards.view',
            'analytics.view',
            'announcements.view', // manage removed — Director is read-only on announcements
            'manpower.view',
            'manpower.manage',
            'task_monitoring.view',
            'notifications.view',
        ]);

        $roles['Head']->syncPermissions([
            'dashboard.view',
            'hiring.view',
            'hiring.plantilla.applicants.view',
            'hiring.plantilla.applications.view',
            'hiring.plantilla.onboarding.view',
            'hiring.plantilla.positions.view',
            'hiring.nonplantilla.applicants.view',
            'hiring.nonplantilla.applications.view',
            'hiring.nonplantilla.onboarding.view',
            'leave.dashboard.view',
            'leave.approval.view',
            'leave.approval.manage',
            'leave.balance.view',
            'leave.calendar.view',
            'leave.request.view',
            'employees.view',
            'renewals.view',
            'team.view',
            'orientation.view',
            'trainings.view',
            'trainings.join',
            'trainings.skill_gap',
            'trainings.effectiveness',
            'trainings.coaching',
            'spms.view',
            'spms.manage',
            'compensation.view',
            'accounts.view',
            'departments.view',
            'plantilla_items.view',
            'positions.view',
            'rewards.view',
            'analytics.view',
            'announcements.view', // manage removed — Head is read-only on announcements
            'manpower.view',
            'task_monitoring.view',
            'notifications.view',
        ]);

        // OIC (Officer In Charge) — acting-head level, same scope as Head
        $roles['OIC']->syncPermissions([
            'dashboard.view',
            'hiring.view',
            'hiring.plantilla.applicants.view',
            'hiring.plantilla.applications.view',
            'hiring.plantilla.onboarding.view',
            'hiring.plantilla.positions.view',
            'hiring.nonplantilla.applicants.view',
            'hiring.nonplantilla.applications.view',
            'hiring.nonplantilla.onboarding.view',
            'leave.dashboard.view',
            'leave.approval.view',
            'leave.approval.manage',
            'leave.balance.view',
            'leave.calendar.view',
            'leave.request.view',
            'employees.view',
            'renewals.view',
            'team.view',
            'orientation.view',
            'trainings.view',
            'trainings.join',
            'trainings.skill_gap',
            'trainings.effectiveness',
            'trainings.coaching',
            'spms.view',
            'spms.manage',
            'compensation.view',
            'accounts.view',
            'departments.view',
            'plantilla_items.view',
            'positions.view',
            'rewards.view',
            'analytics.view',
            'announcements.view', // manage removed — OIC is read-only on announcements
            'manpower.view',
            'task_monitoring.view',
            'notifications.view',
        ]);

        // ─── Line-management tier (Supervisor) ───────────────────────────
        $roles['Supervisor']->syncPermissions([
            'dashboard.view',
            'hiring.view',
            'leave.dashboard.view',
            'leave.approval.view',
            'leave.approval.manage',
            'leave.balance.view',
            'leave.calendar.view',
            'leave.request.view',
            'employees.view',
            'team.view',
            'orientation.view',
            'trainings.view',
            'trainings.join',
            'trainings.skill_gap',
            'trainings.effectiveness',
            'trainings.coaching',
            'spms.view',
            'spms.manage',
            'compensation.view',
            'announcements.view', // manage removed — Supervisor is read-only on announcements
            'rewards.view',
            'notifications.view',
        ]);

        // ─── Base employee tier (Staff) ──────────────────────────────────
        $roles['Staff']->syncPermissions([
            'dashboard.view',
            'leave.dashboard.view',
            'leave.balance.view',
            'leave.calendar.view',
            'leave.request.view',
            'leave.request.manage',
            'team.view',
            'orientation.view',
            'trainings.view',
            'trainings.join',
            'spms.view',
            'spms.manage',
            'announcements.view', // manage removed — Staff is read-only on announcements
            'notifications.view',
        ]);
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
        $this->assignRole('SUPERVISOR',    'Supervisor');
        $this->assignRole('STAFF',      'Staff');
    }

    private function assignRole(string $username, string $role): void
    {
        $user = User::where('username', $username)->first();
        if ($user) {
            $user->syncRoles([$role]);
        }
    }
}