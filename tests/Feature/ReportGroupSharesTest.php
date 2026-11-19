<?php

use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use App\Services\LessonScheduler;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

it('counts group shares from lessons paid in the period, so prepaid money never makes the center share negative', function () {
    $this->travelTo('2026-10-25 10:00:00');
    $admin = userWithRole(Role::Superadmin);
    $this->actingAs($admin);

    $group = Group::factory()->priced(600_000, 12, '2026-10-01')->create(['teacher_percent' => 40, 'days' => [1, 3, 5], 'start_date' => '2026-11-02']);
    app(LessonScheduler::class)->generate($group, CarbonImmutable::parse('2026-11-02'), CarbonImmutable::parse('2026-11-30'));
    $cashbox = Cashbox::factory()->create(['branch_id' => $group->branch_id]);
    $student = Student::factory()->create(['branch_id' => $group->branch_id]);
    $enrollment = app(EnrollmentService::class)->enroll($student, $group, CarbonImmutable::parse('2026-11-02'));

    // Paid in October for November lessons.
    app(FinanceService::class)->receivePayment($enrollment, $cashbox, 600_000);

    foreach (['2026-11-02', '2026-11-04', '2026-11-06'] as $day) {
        $this->travelTo($day.' 18:00:00');
        $lesson = Lesson::query()->where('group_id', $group->id)->whereDate('date', $day)->firstOrFail();
        app(AttendanceService::class)->save($lesson, [$enrollment->id => 'present'], $admin);
    }

    $this->travelTo('2026-11-19 10:00:00');

    $this->get(route('reports', ['from' => '2026-11-01', 'to' => '2026-11-19']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('groups.0.income', 0)
            ->where('groups.0.revenue', 150_000)
            ->where('groups.0.teacher_share', 60_000)
            ->where('groups.0.center_share', 90_000));

    expect(Enrollment::query()->find($enrollment->id)->balance)->toBe(450_000);
});
