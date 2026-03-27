<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->upsertUser('superadmin@local.test', 'SuperAdmin');
        $this->upsertUser('admin@local.test',      'Admin');
        $this->upsertUser('hr@local.test',         'HR');
        $this->upsertUser('staff@local.test',      'Staff');
    }

    private function upsertUser(string $email, ?string $role): void
    {
        $user = User::firstOrNew(['email' => $email]);

        $user->forceFill([
            'password'        => Hash::make('password'),
            'approval_status' => 'APPROVED',
            'approved_at'     => now(),
            'approved_by'     => null,
            'employee_id'     => null,
        ])->save();

        if ($role) {
            $user->fresh()->syncRoles([$role]);
        }
    }
}