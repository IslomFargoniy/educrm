<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Create a user with a role (permissions seeded) attached to the given branches.
 *
 * @param  list<Branch>  $branches
 */
function userWithRole(Role $role, array $branches = []): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole($role->value);
    $user->branches()->attach(collect($branches)->pluck('id'));

    return $user;
}
