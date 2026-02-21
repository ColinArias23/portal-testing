<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        $super = $this->upsertUser('superadmin@local.test', 'SuperAdmin', null);
        $admin = $this->upsertUser('admin@local.test', 'Admin', $super->id);
        $hr    = $this->upsertUser('hr@local.test', 'HR', $super->id);
        $emp   = $this->upsertUser('employee@local.test', 'Employee', $super->id);

        // Assign Spatie roles (web guard)
        $super->syncRoles([Role::findByName('SuperAdmin', $guard)]);
        $admin->syncRoles([Role::findByName('Admin', $guard)]);
        $hr->syncRoles([Role::findByName('HR', $guard)]);
        $emp->syncRoles([Role::findByName('Employee', $guard)]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function upsertUser(string $email, string $role, ?int $approvedBy): User
    {
        $user = User::firstOrNew(['email' => $email]);

        // ✅ force set attributes (works even if fillable/guarded is strict)
        $user->forceFill([
            'password' => Hash::make('password'),
            'role' => $role,
            'approval_status' => 'APPROVED',
            'approved_at' => now(),
            'approved_by' => $approvedBy,
            'employee_id' => null,
        ])->save();

        return $user->fresh();
    }
}
