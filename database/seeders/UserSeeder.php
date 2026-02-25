<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
// use Spatie\Permission\Models\Role;
// use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
        {
        $this->upsertUser('superadmin@local.test', 'Super Admin');
        $this->upsertUser('admin@local.test', 'Admin');
        $this->upsertUser('hr@local.test', 'HR');
        $this->upsertUser('employee@local.test', 'Employee');
    }

    private function upsertUser(string $email, string $role): User
    {
        $user = User::firstOrNew(['email' => $email]);

        $user->forceFill([
            // 'name' => $role,
            'password' => Hash::make('password'),
            'approval_status' => 'APPROVED',
            'approved_at' => now(),
            'approved_by' => null,
            'employee_id' => null,
        ])->save();

        return $user->fresh();
    }
    // {
    //     app(PermissionRegistrar::class)->forgetCachedPermissions();

    //     $guard = 'web';

    //     $super = $this->upsertUser('superadmin@local.test', null);
    //     $admin = $this->upsertUser('admin@local.test', $super->id);
    //     $hr    = $this->upsertUser('hr@local.test', $super->id);
    //     $emp   = $this->upsertUser('employee@local.test', $super->id);

    //     // Assign Spatie roles
    //     $super->syncRoles([Role::findByName('SuperAdmin', $guard)]);
    //     $admin->syncRoles([Role::findByName('Admin', $guard)]);
    //     $hr->syncRoles([Role::findByName('HR', $guard)]);
    //     $emp->syncRoles([Role::findByName('Employee', $guard)]);

    //     app(PermissionRegistrar::class)->forgetCachedPermissions();
    // }

    // private function upsertUser(string $email, ?int $approvedBy): User
    // {
    //     $user = User::firstOrNew(['email' => $email]);

    //     $user->forceFill([
    //         'password' => Hash::make('password'),
    //         'approval_status' => 'APPROVED',
    //         'approved_at' => now(),
    //         'approved_by' => $approvedBy,
    //         'employee_id' => null,
    //     ])->save();

    //     return $user->fresh();
    // }
}