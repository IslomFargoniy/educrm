<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Create permissions and roles. Existing roles keep their (possibly edited) permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (Permissions::defaults() as $roleName => $permissions) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

            if ($role === null) {
                Role::create(['name' => $roleName, 'guard_name' => 'web'])->syncPermissions($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
