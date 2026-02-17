<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RbacSeeder extends Seeder
{
  public function run(): void
  {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $permissions = [
      'manpower.view',
      'orgchart.view',

      'employees.view',
      'employees.manage',

      'roles.manage',
      'permissions.manage',
    ];

    foreach ($permissions as $p) {
      Permission::firstOrCreate(['name' => $p]);
    }

    $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin']);
    $admin      = Role::firstOrCreate(['name' => 'Admin']);
    $hr         = Role::firstOrCreate(['name' => 'HR']);
    $employee   = Role::firstOrCreate(['name' => 'Employee']);

    $superAdmin->syncPermissions(Permission::all());
    $admin->syncPermissions([
      'manpower.view',
      'orgchart.view',
      'employees.view',
    ]);
    $hr->syncPermissions([
      'manpower.view',
      'orgchart.view',
      'employees.view',
      'employees.manage',
    ]);
    $employee->syncPermissions([
      'orgchart.view',
    ]);
  }
}
