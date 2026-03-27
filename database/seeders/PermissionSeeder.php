<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
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
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
        }

        $this->command->info('Permissions seeded: ' . count($permissions));
    }
}