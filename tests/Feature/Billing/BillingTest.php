<?php

use App\Enums\AttendanceStatus;
use App\Enums\ChargeStatus;
use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BillingService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2026-11-30 18:00:00');
    $this->admin = userWithRole(Role::Superadmin);
    $this->actingAs($this->admin);

    $this->teacherA = User::factory()->create(['name' => 'Aziz']);
    $this->teacherB = User::factory()->create(['name' => 'Bobur']);
    $this->group = Group::factory()->priced(600_000, 12)->create(['teacher_id' => $this->teacherA->id, 'teacher_percent' => 40]);
    $this->cashbox = Cashbox::factory()->create(['branch_id' => $this->group->branch_id]);
    $this->enrollment = Enrollment::factory()->create(['group_id' => $this->group->id, 'teacher_percent' => 70]);
});

function lessonOn(Group $group, string $date, ?User $teacher = null, bool $substitute = false): Lesson
{
    return Lesson::factory()->create([
        'group_id' => $group->id,
        'date' => $date,
        'teacher_id' => ($teacher ?? $group->teacher)->id,
        'is_substitute' => $substitute,
    ]);
}

function mark(Lesson $lesson, Enrollment $enrollment, AttendanceStatus $status): void
{
    app(AttendanceService::class)->save($lesson, [$enrollment->id => $status->value], test()->admin);
}

function pay(Enrollment $enrollment, Cashbox $cashbox, int $amount)
{
    return app(FinanceService::class)->receivePayment($enrollment->fresh(), $cashbox, $amount);
}

it('follows the worked example of the plan', function () {
    pay($this->enrollment, $this->cashbox, 100_000);
    mark(lessonOn($this->group, '2026-11-03'), $this->enrollment, AttendanceStatus::Present);
    mark(lessonOn($this->group, '2026-11-05', $this->teacherB, true), $this->enrollment, AttendanceStatus::Present);
    mark(lessonOn($this->group, '2026-11-07'), $this->enrollment, AttendanceStatus::Absent);
    mark(lessonOn($this->group, '2026-11-10'), $this->enrollment, AttendanceStatus::Excused);

    expect($this->enrollment->fresh()->balance)->toBe(-50_000)
        ->and($this->teacherA->fresh()->balance)->toBe(35_000)
        ->and($this->teacherB->fresh()->balance)->toBe(35_000);

    pay($this->enrollment, $this->cashbox, 600_000);

    expect($this->enrollment->fresh()->balance)->toBe(550_000)
        ->and($this->teacherA->fresh()->balance)->toBe(70_000)
        ->and($this->cashbox->fresh()->balance)->toBe(700_000);

    app(FinanceService::class)->payTeacher($this->teacherA->fresh(), $this->cashbox->fresh(), 70_000);

    expect($this->teacherA->fresh()->balance)->toBe(0)
        ->and($this->cashbox->fresh()->balance)->toBe(630_000);
});

it('bills at most N lessons in a calendar month', function () {
    foreach (range(1, 14) as $day) {
        mark(lessonOn($this->group, sprintf('2026-11-%02d', $day)), $this->enrollment, AttendanceStatus::Present);
    }

    expect($this->enrollment->fresh()->balance)->toBe(-600_000)
        ->and($this->enrollment->charges()->where('status', ChargeStatus::Active)->count())->toBe(12);
});

it('uses the group lessons per month as divider', function () {
    $group = Group::factory()->priced(800_000, 20)->create(['teacher_id' => $this->teacherA->id]);
    $enrollment = Enrollment::factory()->create(['group_id' => $group->id]);

    mark(lessonOn($group, '2026-11-02'), $enrollment, AttendanceStatus::Present);

    expect($enrollment->fresh()->balance)->toBe(-40_000);
});

it('gives the teacher nothing until the student pays and credits the teacher who taught', function () {
    mark(lessonOn($this->group, '2026-11-03', $this->teacherB, true), $this->enrollment, AttendanceStatus::Present);

    expect($this->teacherB->fresh()->balance)->toBe(0);

    pay($this->enrollment, $this->cashbox, 30_000);
    expect($this->teacherB->fresh()->balance)->toBe(21_000);

    pay($this->enrollment, $this->cashbox, 20_000);
    expect($this->teacherB->fresh()->balance)->toBe(35_000)
        ->and($this->teacherA->fresh()->balance)->toBe(0);
});

it('re-bills when attendance changes from absent to excused', function () {
    $lesson = lessonOn($this->group, '2026-11-03');
    pay($this->enrollment, $this->cashbox, 50_000);
    mark($lesson, $this->enrollment, AttendanceStatus::Absent);

    expect($this->teacherA->fresh()->balance)->toBe(35_000);

    mark($lesson, $this->enrollment, AttendanceStatus::Excused);

    expect($this->enrollment->fresh()->balance)->toBe(50_000)
        ->and($this->teacherA->fresh()->balance)->toBe(0);
});

it('takes teacher money back when a payment is cancelled', function () {
    $payment = pay($this->enrollment, $this->cashbox, 50_000);
    mark(lessonOn($this->group, '2026-11-03'), $this->enrollment, AttendanceStatus::Present);

    app(FinanceService::class)->reversePayment($payment, 'Xato');

    expect($this->enrollment->fresh()->balance)->toBe(-50_000)
        ->and($this->teacherA->fresh()->balance)->toBe(0)
        ->and($this->cashbox->fresh()->balance)->toBe(0);
});

it('bills trial lessons only when the student continues', function () {
    $trial = Enrollment::factory()->trial(1)->create(['group_id' => $this->group->id]);
    mark(lessonOn($this->group, '2026-11-03'), $trial, AttendanceStatus::Present);

    expect($trial->fresh()->balance)->toBe(0);

    app(BillingService::class)->decideTrial($trial->fresh(), true);

    expect($trial->fresh()->balance)->toBe(-50_000);

    $other = Enrollment::factory()->trial(1)->create(['group_id' => $this->group->id]);
    mark(lessonOn($this->group, '2026-11-05'), $other, AttendanceStatus::Present);
    app(BillingService::class)->decideTrial($other->fresh(), false);

    expect($other->fresh()->balance)->toBe(0)
        ->and($other->charges()->where('status', ChargeStatus::Active)->count())->toBe(0);
});

it('shares the monthly limit between groups when the student is moved', function () {
    foreach (range(2, 6) as $day) {
        mark(lessonOn($this->group, sprintf('2026-11-%02d', $day)), $this->enrollment, AttendanceStatus::Present);
    }

    $newGroup = Group::factory()->priced(600_000, 12)->create(['branch_id' => $this->group->branch_id, 'teacher_id' => $this->teacherB->id]);
    $new = app(EnrollmentService::class)->transfer($this->enrollment->fresh(), $newGroup, now()->setDate(2026, 11, 9));

    foreach (range(9, 20) as $day) {
        mark(lessonOn($newGroup, sprintf('2026-11-%02d', $day)), $new, AttendanceStatus::Present);
    }

    expect($new->charges()->where('status', ChargeStatus::Active)->count())->toBe(7);
});

it('writes off debt without paying the teacher', function () {
    mark(lessonOn($this->group, '2026-11-03'), $this->enrollment, AttendanceStatus::Present);

    app(FinanceService::class)->writeOffDebt($this->enrollment->fresh(), 'Ketdi');

    expect($this->enrollment->fresh()->balance)->toBe(0)
        ->and($this->teacherA->fresh()->balance)->toBe(0);

    pay($this->enrollment, $this->cashbox, 50_000);
    expect($this->teacherA->fresh()->balance)->toBe(0)
        ->and($this->enrollment->fresh()->balance)->toBe(50_000);
});

it('does not let a teacher take more than the balance', function () {
    pay($this->enrollment, $this->cashbox, 100_000);
    mark(lessonOn($this->group, '2026-11-03'), $this->enrollment, AttendanceStatus::Present);

    app(FinanceService::class)->payTeacher($this->teacherA->fresh(), $this->cashbox->fresh(), 40_000);
})->throws(ValidationException::class);

it('asks for confirmation before withdrawing more than the free profit', function () {
    pay($this->enrollment, $this->cashbox, 100_000);

    expect(app(FinanceService::class)->freeProfit([$this->group->branch_id]))->toBe(0);

    expect(fn () => app(FinanceService::class)->withdrawProfit($this->cashbox->fresh(), 50_000, false))
        ->toThrow(ValidationException::class);

    $transaction = app(FinanceService::class)->withdrawProfit($this->cashbox->fresh(), 50_000, true);

    expect($transaction->is_flagged)->toBeTrue()
        ->and($this->cashbox->fresh()->balance)->toBe(50_000);
});

it('rejects a discount that does not divide by the lessons per month', function () {
    app(EnrollmentService::class)->updateTerms($this->enrollment, ['discount_type' => 'amount', 'discount_value' => 5_000]);
})->throws(ValidationException::class);

it('moves unused money between groups of the same student', function () {
    $other = Enrollment::factory()->create(['student_id' => $this->enrollment->student_id, 'group_id' => Group::factory()->priced()->create(['branch_id' => $this->group->branch_id])->id]);
    pay($this->enrollment, $this->cashbox, 100_000);

    app(FinanceService::class)->transferBalance($this->enrollment->fresh(), $other, 60_000);

    expect($this->enrollment->fresh()->balance)->toBe(40_000)
        ->and($other->fresh()->balance)->toBe(60_000);
});
