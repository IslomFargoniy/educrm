<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Teachers (and admins, managers) may add a student's face photo for the terminals.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::findOrCreate('students.face', 'web');

        Role::query()->whereIn('name', ['admin', 'manager', 'teacher'])->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'students.face')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
