<?php

use App\Enums\Role;
use App\Models\Branch;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('staff with permission can visit the dashboard', function () {
    $this->actingAs(userWithRole(Role::Admin, [Branch::factory()->create()]));

    $this->get(route('dashboard'))->assertOk();
});

test('teachers are sent to their lessons instead of the dashboard', function () {
    $this->actingAs(userWithRole(Role::Teacher, [Branch::factory()->create()]));

    $this->get(route('dashboard'))->assertForbidden();
    $this->get(route('home'))->assertRedirect(route('attendance.index'));
});
