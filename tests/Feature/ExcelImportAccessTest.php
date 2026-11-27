<?php

use App\Enums\Role;
use App\Models\Branch;

it('lets a manager load students from Excel, but not a teacher', function () {
    $branch = Branch::factory()->create();

    $this->actingAs(userWithRole(Role::Manager, [$branch]))
        ->get(route('admin.import.index'))->assertOk();

    $this->actingAs(userWithRole(Role::Teacher, [$branch]))
        ->get(route('admin.import.index'))->assertForbidden();
});
