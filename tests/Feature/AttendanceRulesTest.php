<?php

use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lesson;
use App\Services\AttendanceService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2026-11-19 12:00:00');
});

it('never takes attendance for a future lesson, not even from an admin', function () {
    $admin = userWithRole(Role::Superadmin);
    $group = Group::factory()->create();
    $enrollment = Enrollment::factory()->create(['group_id' => $group->id]);
    $lesson = Lesson::factory()->create(['group_id' => $group->id, 'date' => '2026-11-20']);

    expect(fn () => app(AttendanceService::class)->save($lesson, [$enrollment->id => 'present'], $admin))
        ->toThrow(ValidationException::class);
    expect($lesson->attendances()->count())->toBe(0);
});

it('lets a teacher with the catch-up permission mark own past lessons only', function () {
    $teacher = userWithRole(Role::Teacher);
    $group = Group::factory()->create(['teacher_id' => $teacher->id]);
    $teacher->branches()->attach($group->branch_id);
    $own = Lesson::factory()->create(['group_id' => $group->id, 'teacher_id' => $teacher->id, 'date' => '2026-11-17']);
    $other = Lesson::factory()->create(['group_id' => $group->id, 'teacher_id' => userWithRole(Role::Teacher)->id, 'date' => '2026-11-17']);
    $service = app(AttendanceService::class);

    expect($service->canMark($teacher, $own))->toBeFalse();

    $teacher->givePermissionTo('attendance.mark_past');

    expect($service->canMark($teacher->fresh(), $own))->toBeTrue()
        ->and($service->canMark($teacher->fresh(), $other))->toBeFalse();
});
