<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create SuperAdmin user
        $this->upsertUser(
            email: 'superadmin@local.test',
            username: 'superadmin',
            role: 'SuperAdmin'
        );

        // Create HR user
        $this->upsertUser(
            email: 'hr@local.test',
            username: 'hr',
            role: 'HR'
        );
    }

    private function upsertUser(
        string $email,
        string $username,
        ?string $role = null
    ): void {
        DB::transaction(function () use ($email, $username, $role) {

            $user = User::firstOrNew([
                'email' => $email
            ]);

            $user->forceFill([
                'username'        => $username,
                'password'        => Hash::make('password'),
                'approval_status' => 'APPROVED',
                'approved_at'     => now(),
                'approved_by'     => null,
            ]);

            $user->save();

            // Assign role if provided
            if ($role) {
                $user->syncRoles([$role]);
            }
        });
    }
}