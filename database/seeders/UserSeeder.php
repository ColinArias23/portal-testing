<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
  public function run(): void
  {
    $super = User::firstOrCreate(
      ['email' => 'superadmin@demo.test'],
      ['name' => 'Super Admin', 'password' => Hash::make('password')]
    );
    $super->assignRole('SuperAdmin');

    $hr = User::firstOrCreate(
      ['email' => 'hr@demo.test'],
      ['name' => 'HR User', 'password' => Hash::make('password')]
    );
    $hr->assignRole('HR');

    $admin = User::firstOrCreate(
      ['email' => 'admin@demo.test'],
      ['name' => 'Admin Viewer', 'password' => Hash::make('password')]
    );
    $admin->assignRole('Admin');

    $emp = User::firstOrCreate(
      ['email' => 'employee@demo.test'],
      ['name' => 'Employee', 'password' => Hash::make('password')]
    );
    $emp->assignRole('Employee');
  }
}
