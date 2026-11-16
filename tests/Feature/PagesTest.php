<?php

use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Group;
use App\Models\Lead;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, ExpenseCategorySeeder::class, DemoSeeder::class]);
    auth()->logout();
});

it('renders every staff page for the superadmin', function () {
    $this->actingAs(User::role(Role::Superadmin->value)->first());

    $urls = [
        route('dashboard'), route('students.index'), route('students.show', Student::query()->first()),
        route('groups.index'), route('groups.show', Group::query()->first()), route('attendance.index'),
        route('lessons.show', Lesson::query()->first()), route('schedule'), route('teachers.index'),
        route('teachers.show', User::role(Role::Teacher->value)->first()), route('cashboxes.index'),
        route('cashboxes.show', Cashbox::query()->first()), route('reports'), route('leads.index'),
        route('leads.show', Lead::query()->first()), route('admin.branches.index'), route('admin.users.index'),
        route('admin.roles.index'), route('admin.holidays.index'), route('admin.expense-categories.index'),
        route('admin.import.index'), route('payments.receipt', Payment::query()->first()), route('profile.edit'),
    ];

    foreach ($urls as $url) {
        $this->get($url)->assertOk();
    }
});

it('shows parents only their children', function () {
    $parent = Student::query()->first()->guardians()->first();

    $this->actingAs($parent)->get(route('home'))->assertRedirect(route('portal'));
    $this->actingAs($parent)->get(route('portal'))->assertOk();
    $this->actingAs($parent)->get(route('students.index'))->assertForbidden();
    $this->actingAs($parent)->get(route('cashboxes.index'))->assertForbidden();
});

it('lets a teacher open own lessons but not finance', function () {
    $teacher = User::role(Role::Teacher->value)->first();
    $lesson = Lesson::query()->where('teacher_id', $teacher->id)->first();

    $this->actingAs($teacher)->get(route('attendance.index'))->assertOk();
    $this->actingAs($teacher)->get(route('lessons.show', $lesson))->assertOk();
    $this->actingAs($teacher)->get(route('teachers.show', $teacher))->assertOk();
    $this->actingAs($teacher)->get(route('cashboxes.index'))->assertForbidden();
    $this->actingAs($teacher)->get(route('reports'))->assertForbidden();
});
