<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Managers (cashiers) may load students from Excel into their branch, not only admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::findOrCreate('import.run', 'web');

        Role::query()->where('name', 'manager')->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('name', 'manager')->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->revokePermissionTo('import.run'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
