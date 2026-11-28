<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Student;

beforeEach(function () {
    $this->travelTo('2026-11-28 10:00:00');
    $this->group = Group::factory()->priced(360_000, 12, '2026-11-01')->create(['teacher_percent' => 65]);
    $this->manager = userWithRole(Role::Manager, [$this->group->branch]);
});

it('lets a manager edit a group without the hidden teacher percent field', function () {
    $this->actingAs($this->manager)->put(route('groups.update', $this->group), [
        'branch_id' => $this->group->branch_id,
        'name' => 'Yangi nom',
        'days' => [2, 6],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'start_date' => '2026-11-01',
        'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($this->group->fresh())->name->toBe('Yangi nom')->teacher_percent->toBe(65);
});

it('adds several students to a group at once and skips those already in it', function () {
    $students = Student::factory()->count(3)->create(['branch_id' => $this->group->branch_id]);
    $this->actingAs($this->manager);

    $this->getJson(route('groups.student-candidates', $this->group))->assertOk()->assertJsonCount(3, 'data');

    $this->post(route('groups.enrollments.store', $this->group), [
        'student_ids' => $students->take(2)->pluck('id')->all(),
        'joined_at' => '2026-11-28',
    ])->assertSessionHasNoErrors();
    expect($this->group->enrollments()->count())->toBe(2);
    $this->getJson(route('groups.student-candidates', $this->group))->assertJsonCount(1, 'data');

    $this->post(route('groups.enrollments.store', $this->group), [
        'student_ids' => $students->pluck('id')->all(),
        'joined_at' => '2026-11-28',
    ])->assertSessionHasNoErrors();
    expect($this->group->enrollments()->count())->toBe(3);
});

it('refuses students of another branch', function () {
    $stranger = Student::factory()->create(['branch_id' => Branch::factory()->create()->id]);

    $this->actingAs($this->manager)->post(route('groups.enrollments.store', $this->group), [
        'student_ids' => [$stranger->id],
        'joined_at' => '2026-11-28',
    ])->assertSessionHasErrors('student_ids');
    expect($this->group->enrollments()->count())->toBe(0);
});
