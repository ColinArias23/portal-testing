<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('dtr.manage', 'web');

        Role::where('name', 'SuperAdmin')->where('guard_name', 'web')->first()
            ?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'dtr.manage')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
