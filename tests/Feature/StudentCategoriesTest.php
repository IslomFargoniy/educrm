<?php

use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->travelTo('2026-11-10 12:00:00');
    $this->admin = userWithRole(Role::Superadmin);
    $this->actingAs($this->admin);
    $this->group = Group::factory()->priced(600_000, 12, '2026-11-01')->create(['teacher_id' => userWithRole(Role::Teacher)->id]);
});

function categoryNames(string $category): array
{
    $names = [];
    test()->get(route('students.index', ['category' => $category]))
        ->assertInertia(function (AssertableInertia $page) use (&$names) {
            $names = collect($page->toArray()['props']['students']['data'])->pluck('full_name')->all();
        });

    return $names;
}

it('sorts students into new, active, debtors and archive', function () {
    $enroll = fn (string $name) => app(EnrollmentService::class)->enroll(Student::factory()->create(['branch_id' => $this->group->branch_id, 'full_name' => $name]), $this->group, CarbonImmutable::parse('2026-11-02'));
    $newcomer = $enroll('Yangi Bola');
    $payer = $enroll('Aktiv Bola');
    $gone = $enroll('Ketgan Bola');
    app(FinanceService::class)->receivePayment($payer, Cashbox::factory()->create(['branch_id' => $this->group->branch_id]), 50_000);

    foreach (['2026-11-03', '2026-11-05'] as $day) {
        $lesson = Lesson::factory()->create(['group_id' => $this->group->id, 'teacher_id' => $this->group->teacher_id, 'is_substitute' => false, 'date' => $day]);
        $this->travelTo($day.' 18:00:00');
        app(AttendanceService::class)->save($lesson, [$newcomer->id => 'present', $payer->id => 'present'], $this->admin);
    }
    $this->travelTo('2026-11-10 12:00:00');
    app(EnrollmentService::class)->leave($gone, today(), 'graduated');

    expect(categoryNames('new'))->toBe(['Yangi Bola'])
        ->and(categoryNames('active'))->toBe(['Aktiv Bola'])
        ->and(categoryNames('archive'))->toBe(['Ketgan Bola'])
        ->and(categoryNames('debtors'))->toContain('Yangi Bola');
});

it('tags a debtor phone with the call result', function () {
    $student = Student::factory()->create(['branch_id' => $this->group->branch_id]);
    Enrollment::factory()->create(['student_id' => $student->id, 'group_id' => $this->group->id, 'balance' => -50_000]);

    $this->patch(route('students.call-status', $student), ['call_status' => 'no_answer'])->assertSessionHasNoErrors();
    expect($student->fresh()->call_status)->toBe('no_answer');

    $this->patch(route('students.call-status', $student), ['call_status' => 'something'])->assertSessionHasErrors('call_status');

    $this->get(route('students.index', ['category' => 'debtors', 'call_status' => 'no_answer']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('students.data', 1)->where('debtTotals.debt', 50_000));
});

it('lists parents with their children', function () {
    $student = Student::factory()->create(['branch_id' => $this->group->branch_id, 'full_name' => 'Ali Valiyev']);
    $parent = User::factory()->create(['name' => 'Vali Valiyev']);
    $student->guardians()->attach($parent, ['relation' => 'father']);

    $this->get(route('students.parents'))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('students/parents')
        ->where('parents.data.0.name', 'Vali Valiyev')
        ->where('parents.data.0.children.0.full_name', 'Ali Valiyev'));
});
