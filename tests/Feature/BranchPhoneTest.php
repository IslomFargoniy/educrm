<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, ExpenseCategorySeeder::class, DemoSeeder::class]);
    $this->actingAs(User::role(Role::Superadmin->value)->first());
});

it('stores a formatted branch phone in normalized form', function () {
    $this->post(route('admin.branches.store'), ['name' => 'Yangi filial', 'phone' => '+998 71 234 56 78'])
        ->assertSessionHasNoErrors();

    expect(Branch::query()->where('name', 'Yangi filial')->value('phone'))->toBe('+998712345678');
});

it('rejects an incomplete branch phone', function () {
    $this->post(route('admin.branches.store'), ['name' => 'Yangi filial', 'phone' => '+998 71 234'])
        ->assertSessionHasErrors('phone');
});
