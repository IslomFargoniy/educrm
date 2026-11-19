<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Group;
use App\Models\Lead;
use App\Models\Student;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->actingAs(userWithRole(Role::Superadmin));
});

it('stores first and last name separately and shows them as one name', function () {
    $this->post(route('students.store'), ['first_name' => ' Firdavs ', 'last_name' => 'Sobirov', 'branch_id' => $this->branch->id])
        ->assertSessionHasNoErrors();

    $student = Student::query()->latest('id')->firstOrFail();

    expect($student->first_name)->toBe('Firdavs')
        ->and($student->last_name)->toBe('Sobirov')
        ->and($student->full_name)->toBe('Firdavs Sobirov');
});

it('requires the last name of a student', function () {
    $this->post(route('students.store'), ['first_name' => 'Firdavs'])
        ->assertSessionHasErrors('last_name');
});

it('splits a full name given by older paths', function () {
    $student = Student::factory()->create(['full_name' => 'Ali  Vali o\'g\'li']);

    expect($student->first_name)->toBe('Ali')
        ->and($student->last_name)->toBe('Vali o\'g\'li');
});

it('keeps the course name on groups and leads and follows a rename', function () {
    $course = Course::query()->create(['name' => 'IELTS']);
    $group = Group::factory()->create(['branch_id' => $this->branch->id]);
    $group->update(['course_id' => $course->id]);
    $lead = Lead::query()->create(['branch_id' => $this->branch->id, 'first_name' => 'Aziz', 'phone' => '+998901234567', 'source' => 'other', 'course_id' => $course->id]);

    expect($group->fresh()->subject)->toBe('IELTS')
        ->and($lead->fresh()->interested_course)->toBe('IELTS')
        ->and($lead->fresh()->full_name)->toBe('Aziz');

    $this->put(route('admin.courses.update', $course), ['name' => 'IELTS Intensive', 'is_active' => true])
        ->assertSessionHasNoErrors();

    expect($group->fresh()->subject)->toBe('IELTS Intensive')
        ->and($lead->fresh()->interested_course)->toBe('IELTS Intensive');
});

it('rejects a duplicate course name', function () {
    Course::query()->create(['name' => 'IELTS']);

    $this->post(route('admin.courses.store'), ['name' => 'IELTS'])->assertSessionHasErrors('name');
});
