<?php

use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\ExpenseCategory;
use App\Models\Group;
use App\Models\Lead;
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
            ->where('groups.0.teacher_percent', 40)
            ->where('groups.0.monthly_plan', 600_000)
            ->where('groups.0.charged', 150_000)
            ->where('groups.0.revenue', 150_000)
            ->where('groups.0.teacher_share', 60_000)
            ->where('groups.0.center_share', 90_000)
            ->where('courses.0.groups', 1)
            ->where('courses.0.revenue', 150_000)
            ->where('courses.0.teacher_share', 60_000));

    expect(Enrollment::query()->find($enrollment->id)->balance)->toBe(450_000);
});

it('shows the exact change in cashboxes for the period, reversals and expenses included', function () {
    $this->travelTo('2026-11-10 10:00:00');
    $this->actingAs(userWithRole(Role::Superadmin));
    $group = Group::factory()->priced(600_000, 12, '2026-11-01')->create();
    $cashbox = Cashbox::factory()->create(['branch_id' => $group->branch_id]);
    $student = Student::factory()->create(['branch_id' => $group->branch_id]);
    $enrollment = app(EnrollmentService::class)->enroll($student, $group, CarbonImmutable::parse('2026-11-01'));
    $finance = app(FinanceService::class);

    $finance->receivePayment($enrollment, $cashbox, 600_000);
    $mistake = $finance->receivePayment($enrollment->fresh(), $cashbox->fresh(), 200_000);
    $this->travelTo('2026-11-11 10:00:00');
    $finance->reversePayment($mistake->fresh(), 'Xato');
    $finance->expense($cashbox->fresh(), ExpenseCategory::query()->create(['name' => 'Ijara']), 50_000);

    $this->get(route('reports', ['from' => '2026-11-01', 'to' => '2026-11-30']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cashChange', 550_000)
            ->where('totals.student_payment.total', 600_000));

    expect($cashbox->fresh()->balance)->toBe(550_000);
});

it('keeps a closed group in the report for a period it had payments in', function () {
    $this->travelTo('2026-11-10 10:00:00');
    $this->actingAs(userWithRole(Role::Superadmin));
    $group = Group::factory()->priced(600_000, 12, '2026-11-01')->create();
    $cashbox = Cashbox::factory()->create(['branch_id' => $group->branch_id]);
    $enrollment = app(EnrollmentService::class)->enroll(Student::factory()->create(['branch_id' => $group->branch_id]), $group, CarbonImmutable::parse('2026-11-01'));
    app(FinanceService::class)->receivePayment($enrollment, $cashbox, 300_000);
    $group->update(['status' => 'closed']);

    $this->get(route('reports', ['from' => '2026-11-01', 'to' => '2026-11-30']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('groups', 1)->where('groups.0.income', 300_000));

    $this->get(route('reports', ['from' => '2026-12-01', 'to' => '2026-12-31']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('groups', 0));
});

it('shows teacher money as start of period + earned − paid = end of period', function () {
    $this->travelTo('2026-10-05 10:00:00');
    $admin = userWithRole(Role::Superadmin);
    $this->actingAs($admin);

    $teacher = userWithRole(Role::Teacher);
    $group = Group::factory()->priced(600_000, 12, '2026-10-01')->create(['teacher_id' => $teacher->id, 'teacher_percent' => 40, 'days' => [1, 3, 5], 'start_date' => '2026-10-05']);
    app(LessonScheduler::class)->generate($group, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-11-30'));
    $cashbox = Cashbox::factory()->create(['branch_id' => $group->branch_id]);
    $student = Student::factory()->create(['branch_id' => $group->branch_id]);
    $enrollment = app(EnrollmentService::class)->enroll($student, $group, CarbonImmutable::parse('2026-10-05'));
    app(FinanceService::class)->receivePayment($enrollment, $cashbox, 600_000);

    // October lesson earned in October, paid out in November together with a November lesson.
    $this->travelTo('2026-10-05 18:00:00');
    app(AttendanceService::class)->save(Lesson::query()->where('group_id', $group->id)->whereDate('date', '2026-10-05')->firstOrFail(), [$enrollment->id => 'present'], $admin);
    $this->travelTo('2026-11-02 18:00:00');
    app(AttendanceService::class)->save(Lesson::query()->where('group_id', $group->id)->whereDate('date', '2026-11-02')->firstOrFail(), [$enrollment->id => 'present'], $admin);
    app(FinanceService::class)->payTeacher($teacher->fresh(), $cashbox->fresh(), 40_000);

    $this->travelTo('2026-11-19 10:00:00');

    $this->get(route('reports', ['from' => '2026-11-01', 'to' => '2026-11-19']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('teachers.0.opening', 20_000)
            ->where('teachers.0.earned', 20_000)
            ->where('teachers.0.paid', 40_000)
            ->where('teachers.0.closing', 0));
});

it('shows the lead funnel: how many leads joined a group and paid', function () {
    $this->travelTo('2026-11-10 10:00:00');
    $this->actingAs(userWithRole(Role::Superadmin));
    $group = Group::factory()->priced(600_000, 12, '2026-11-01')->create();
    $cashbox = Cashbox::factory()->create(['branch_id' => $group->branch_id]);
    $lead = fn (string $name, string $status) => Lead::query()->create(['branch_id' => $group->branch_id, 'full_name' => $name, 'phone' => '+998901234567', 'source' => 'instagram', 'status' => $status]);

    $lead('New One', 'new');
    $lead('Said No', 'rejected');
    $joined = $lead('Joined One', 'converted');
    $student = Student::factory()->create(['branch_id' => $group->branch_id]);
    $joined->update(['student_id' => $student->id]);
    $enrollment = app(EnrollmentService::class)->enroll($student, $group, CarbonImmutable::parse('2026-11-10'));
    app(FinanceService::class)->receivePayment($enrollment, $cashbox, 100_000);

    $this->get(route('reports', ['from' => '2026-11-01', 'to' => '2026-11-19']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('leadSources.0.total', 3)
            ->where('leadSources.0.contacted', 2)
            ->where('leadSources.0.joined', 1)
            ->where('leadSources.0.studying', 1)
            ->where('leadSources.0.paid', 1)
            ->where('leadSources.0.rejected', 1)
            ->where('studentFlow.joined', 1)
            ->where('studentFlow.left', 0));
});

it('filters the report by group, teacher, course, student and lesson days', function () {
    $this->travelTo('2026-11-10 10:00:00');
    $this->actingAs(userWithRole(Role::Superadmin));
    $teacher = userWithRole(Role::Teacher);
    $odd = Group::factory()->priced(600_000, 12, '2026-11-01')->create(['name' => 'Toq', 'days' => [1, 3, 5], 'teacher_id' => $teacher->id]);
    $even = Group::factory()->priced(600_000, 12, '2026-11-01')->create(['name' => 'Juft', 'days' => [2, 4, 6], 'branch_id' => $odd->branch_id]);
    $cashbox = Cashbox::factory()->create(['branch_id' => $odd->branch_id]);
    $ali = Student::factory()->create(['branch_id' => $odd->branch_id]);
    $vali = Student::factory()->create(['branch_id' => $odd->branch_id]);
    app(FinanceService::class)->receivePayment(app(EnrollmentService::class)->enroll($ali, $odd, CarbonImmutable::parse('2026-11-02')), $cashbox, 100_000);
    app(FinanceService::class)->receivePayment(app(EnrollmentService::class)->enroll($vali, $even, CarbonImmutable::parse('2026-11-03')), $cashbox, 200_000);
    $range = ['from' => '2026-11-01', 'to' => '2026-11-30'];

    $this->get(route('reports', $range))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('groups', 2)->where('totals.student_payment.total', 300_000)->where('cashChange', 300_000));

    $this->get(route('reports', [...$range, 'days' => [2, 4]]))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('groups', 1)->where('groups.0.group', 'Juft')->where('totals.student_payment.total', 200_000)->where('cashChange', null));

    $this->get(route('reports', [...$range, 'teacher_id' => $teacher->id]))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('groups', 1)->where('groups.0.group', 'Toq')->where('totals.student_payment.total', 100_000));

    $this->get(route('reports', [...$range, 'student_id' => $vali->id]))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('groups', 1)->where('groups.0.income', 200_000)->where('filters.student_id', $vali->id));

    $this->get(route('reports', [...$range, 'group_id' => $odd->id]))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('groups', 1)->where('groups.0.group', 'Toq')->has('filterOptions.groups', 2));
});
