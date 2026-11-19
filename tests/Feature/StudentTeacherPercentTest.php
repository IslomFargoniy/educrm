<?php

use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Group;
use Inertia\Testing\AssertableInertia;

it('lets the group page change one student\'s teacher percent without touching the discount', function () {
    $group = Group::factory()->priced(600_000, 12)->create(['teacher_percent' => 35]);
    $enrollment = Enrollment::factory()->create(['group_id' => $group->id, 'discount_type' => 'percent', 'discount_value' => 10]);
    $this->actingAs(userWithRole(Role::Admin, [$group->branch]));

    $this->get(route('groups.show', $group))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('students.0.teacher_percent', null)
            ->where('students.0.discount_type', 'percent')
            ->where('students.0.discount_value', 10));

    // Exactly what the group page's percent dialog submits.
    $this->patch(route('enrollments.update', $enrollment), ['discount_type' => 'percent', 'discount_value' => 10, 'teacher_percent' => 50])
        ->assertSessionHasNoErrors();

    $enrollment->refresh();
    expect($enrollment->teacher_percent)->toBe(50)
        ->and($enrollment->discount_type?->value)->toBe('percent')
        ->and($enrollment->discount_value)->toBe(10)
        ->and($enrollment->effectiveTeacherPercent())->toBe(50);

    // Clearing it falls back to the group percent.
    $this->patch(route('enrollments.update', $enrollment), ['discount_type' => 'percent', 'discount_value' => 10, 'teacher_percent' => ''])
        ->assertSessionHasNoErrors();

    expect($enrollment->fresh()->effectiveTeacherPercent())->toBe(35);
});
