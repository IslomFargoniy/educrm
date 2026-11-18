<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Lesson;
use Inertia\Testing\AssertableInertia as Assert;

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

test('the dashboard lists today lessons of the user branches only', function () {
    $branch = Branch::factory()->create();
    $this->actingAs(userWithRole(Role::Admin, [$branch]));
    $own = Group::factory()->create(['branch_id' => $branch->id, 'name' => 'Ingliz-1']);
    $other = Group::factory()->create();
    Lesson::factory()->create(['group_id' => $own->id, 'date' => today()]);
    Lesson::factory()->create(['group_id' => $own->id, 'date' => today()->addDay()]);
    Lesson::factory()->create(['group_id' => $other->id, 'date' => today()]);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('todayLessons', 1)
        ->where('todayLessons.0.group', 'Ingliz-1'));
});
