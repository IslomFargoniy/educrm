<?php

use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use App\Services\LessonScheduler;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

it('shows for each student how many lessons of the month they attended, paid and still owe', function () {
    $this->travelTo('2026-11-01 10:00:00');
    $admin = userWithRole(Role::Superadmin);
    $this->actingAs($admin);
    $teacher = userWithRole(Role::Teacher);
    $group = Group::factory()->priced(600_000, 12, '2026-11-01')->create(['teacher_id' => $teacher->id, 'teacher_percent' => 40, 'days' => [1, 3, 5], 'start_date' => '2026-11-02']);
    $teacher->branches()->attach($group->branch_id);
    app(LessonScheduler::class)->generate($group, CarbonImmutable::parse('2026-11-02'), CarbonImmutable::parse('2026-11-30'));
    $cashbox = Cashbox::factory()->create(['branch_id' => $group->branch_id]);
    $student = Student::factory()->create(['branch_id' => $group->branch_id, 'full_name' => 'Ali Valiyev']);
    $enrollment = app(EnrollmentService::class)->enroll($student, $group, CarbonImmutable::parse('2026-11-02'));
    app(FinanceService::class)->receivePayment($enrollment, $cashbox, 100_000);

    foreach (['2026-11-02' => 'present', '2026-11-04' => 'present', '2026-11-06' => 'absent'] as $day => $status) {
        $this->travelTo($day.' 18:00:00');
        app(AttendanceService::class)->save(Lesson::query()->where('group_id', $group->id)->whereDate('date', $day)->firstOrFail(), [$enrollment->id => $status], $admin);
    }

    $this->get(route('teachers.show', ['teacher' => $teacher->id, 'month' => '2026-11']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('month', '2026-11')
            ->where('report.0.lessons_per_month', 12)
            ->where('report.0.lessons_held', 3)
            ->where('report.0.students.0.student', 'Ali Valiyev')
            ->where('report.0.students.0.present', 2)
            ->where('report.0.students.0.absent', 1)
            ->where('report.0.students.0.billed', 3)
            ->where('report.0.students.0.paid', 2)
            ->where('report.0.students.0.unpaid', 1)
            ->where('report.0.students.0.debt', 50_000)
            ->where('report.0.students.0.share_received', 40_000)
            ->where('report.0.students.0.share_waiting', 20_000));
});

it('downloads a table as an Excel file', function () {
    $this->actingAs(userWithRole(Role::Superadmin));

    $response = $this->postJson(route('export.xlsx'), ['filename' => 'Aziza Karimova 2026-11', 'headers' => ['A', 'B'], 'rows' => [['x', 1], ['y', 2]]]);

    $response->assertOk()->assertDownload('aziza-karimova-2026-11.xlsx');
});

it('does not export for guests', function () {
    $this->postJson(route('export.xlsx'), ['filename' => 'x', 'headers' => ['A'], 'rows' => []])->assertUnauthorized();
});

it('rates the teacher: new students kept, graduated and left, lessons held, attendance', function () {
    $this->travelTo('2026-11-20 10:00:00');
    $admin = userWithRole(Role::Superadmin);
    $this->actingAs($admin);
    $teacher = userWithRole(Role::Teacher);
    $group = Group::factory()->priced(600_000, 12, '2026-11-01')->create(['teacher_id' => $teacher->id]);
    $teacher->branches()->attach($group->branch_id);
    $enroll = fn (string $day) => app(EnrollmentService::class)->enroll(Student::factory()->create(['branch_id' => $group->branch_id]), $group, CarbonImmutable::parse($day));

    $stays = $enroll('2026-11-02');
    $quits = $enroll('2026-11-03');
    $old = $enroll('2026-10-01');
    app(EnrollmentService::class)->leave($quits, CarbonImmutable::parse('2026-11-10'), 'dropped');
    app(EnrollmentService::class)->leave($old, CarbonImmutable::parse('2026-11-15'), 'graduated');
    app(FinanceService::class)->receivePayment($stays, Cashbox::factory()->create(['branch_id' => $group->branch_id]), 50_000);

    $this->get(route('teachers.show', ['teacher' => $teacher->id, 'month' => '2026-11']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('kpi.retention.joined', 2)
            ->where('kpi.retention.stayed', 1)
            ->where('kpi.retention.paid', 1)
            ->where('kpi.outcomes.graduated', 1)
            ->where('kpi.outcomes.dropped', 1)
            ->where('kpi.outcomes.studying', 1));
});
