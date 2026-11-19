<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Cashbox;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('gives the teacher list the branch cashboxes so salaries can be paid from it', function () {
    $branch = Branch::factory()->create();
    $other = Branch::factory()->create();
    $cashbox = Cashbox::factory()->create(['branch_id' => $branch->id, 'balance' => 0]);
    Cashbox::factory()->create(['branch_id' => $other->id]);
    $teacher = userWithRole(Role::Teacher, [$branch]);

    $this->actingAs(userWithRole(Role::Manager, [$branch]))
        ->get(route('teachers.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('teachers/index')
            ->where('teachers.0.id', $teacher->id)
            ->has('cashboxes', 1)
            ->where('cashboxes.0.id', $cashbox->id));
});

it('sends no cashboxes to users who cannot pay salaries', function () {
    $branch = Branch::factory()->create();
    Cashbox::factory()->create(['branch_id' => $branch->id]);
    $viewer = User::factory()->create();
    $viewer->branches()->attach($branch->id);
    userWithRole(Role::Teacher, [$branch]);
    $viewer->givePermissionTo('teachers.view');

    $this->actingAs($viewer)
        ->get(route('teachers.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('cashboxes', 0));
});
