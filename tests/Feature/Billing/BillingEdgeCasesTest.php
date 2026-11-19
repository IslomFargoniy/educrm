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
use App\Services\FinanceService;
use Illuminate\Validation\ValidationException;

/**
 * Regressions found by the billing audit: re-billing a lesson (teacher change,
 * attendance edit) must keep FIFO coverage, write-offs and rejected trials intact,
 * and money that already left a group cannot be taken back by a payment reversal.
 */
beforeEach(function () {
    $this->travelTo('2026-11-30 18:00:00');
    $this->admin = userWithRole(Role::Superadmin);
    $this->actingAs($this->admin);
    $this->group = Group::factory()->priced(600_000, 12)->create(['teacher_percent' => 50]);
    $this->teacherA = userWithRole(Role::Teacher, [$this->group->branch]);
    $this->teacherB = userWithRole(Role::Teacher, [$this->group->branch]);
    $this->group->update(['teacher_id' => $this->teacherA->id]);
    $this->cashbox = Cashbox::factory()->create(['branch_id' => $this->group->branch_id]);
    $this->enrollment = Enrollment::factory()->create(['group_id' => $this->group->id]);
});

function edgeLesson(Group $group, string $date): Lesson
{
    return Lesson::factory()->create(['group_id' => $group->id, 'date' => $date, 'teacher_id' => $group->teacher_id, 'is_substitute' => false]);
}

function edgeMark(Lesson $lesson, Enrollment $enrollment, AttendanceStatus $status): void
{
    app(AttendanceService::class)->save($lesson, [$enrollment->id => $status->value], test()->admin);
}

function edgeChangeTeacher(Lesson $lesson, User $teacher): void
{
    test()->patch(route('lessons.update', $lesson->fresh()), ['action' => 'teacher', 'teacher_id' => $teacher->id])
        ->assertSessionHasNoErrors()->assertRedirect();
}

it('gives a substitute set after the fact the share of the paid lesson, keeping oldest-first coverage', function () {
    app(FinanceService::class)->receivePayment($this->enrollment->fresh(), $this->cashbox, 50_000);
    $first = edgeLesson($this->group, '2026-11-03');
    $second = edgeLesson($this->group, '2026-11-05');
    edgeMark($first, $this->enrollment, AttendanceStatus::Present);
    edgeMark($second, $this->enrollment, AttendanceStatus::Present);

    edgeChangeTeacher($first, $this->teacherB);

    expect($this->teacherB->fresh()->balance)->toBe(25_000)
        ->and($this->teacherA->fresh()->balance)->toBe(0)
        ->and($first->charges()->where('status', ChargeStatus::Active)->first()->covered_amount)->toBe(50_000)
        ->and($second->charges()->where('status', ChargeStatus::Active)->first()->covered_amount)->toBe(0);
});

it('never bills a rejected trial lesson again', function (string $how) {
    $trial = Enrollment::factory()->trial(1)->create(['group_id' => $this->group->id]);
    $lesson = edgeLesson($this->group, '2026-11-03');
    edgeMark($lesson, $trial, AttendanceStatus::Present);
    app(BillingService::class)->decideTrial($trial->fresh(), false);

    if ($how === 'teacher change') {
        edgeChangeTeacher($lesson, $this->teacherB);
    } else {
        edgeMark($lesson, $trial, AttendanceStatus::Excused);
        edgeMark($lesson, $trial, AttendanceStatus::Present);
    }

    expect($trial->fresh()->balance)->toBe(0)
        ->and($trial->charges()->where('status', ChargeStatus::Active)->count())->toBe(0);
})->with(['teacher change', 'attendance edit']);

it('keeps a written-off lesson written off when it is re-billed', function () {
    $lesson = edgeLesson($this->group, '2026-11-03');
    edgeMark($lesson, $this->enrollment, AttendanceStatus::Present);
    app(FinanceService::class)->writeOffDebt($this->enrollment->fresh(), 'Kelishuv');

    edgeChangeTeacher($lesson, $this->teacherB);

    $charge = $lesson->charges()->where('status', ChargeStatus::Active)->first();
    expect($this->enrollment->fresh()->balance)->toBe(0)
        ->and($charge->written_off_amount)->toBe(50_000)
        ->and($this->teacherB->fresh()->balance)->toBe(0);
});

it('refuses to reverse a payment whose money was already refunded', function () {
    $finance = app(FinanceService::class);
    $finance->receivePayment(Enrollment::factory()->create(['group_id' => $this->group->id]), $this->cashbox->fresh(), 1_000_000);
    $payment = $finance->receivePayment($this->enrollment->fresh(), $this->cashbox->fresh(), 100_000);
    $finance->refund($this->enrollment->fresh(), $this->cashbox->fresh(), 100_000);

    expect(fn () => $finance->reversePayment($payment->fresh(), 'Xato'))->toThrow(ValidationException::class);
    expect($this->enrollment->fresh()->balance)->toBe(0)
        ->and($this->cashbox->fresh()->balance)->toBe(1_000_000)
        ->and($payment->fresh()->reversed_at)->toBeNull();
});

it('returns the first payment for a repeated idempotency key and rejects it for another student', function () {
    $finance = app(FinanceService::class);
    $first = $finance->receivePayment($this->enrollment->fresh(), $this->cashbox->fresh(), 100_000, idempotencyKey: 'key-1');
    $again = $finance->receivePayment($this->enrollment->fresh(), $this->cashbox->fresh(), 100_000, idempotencyKey: 'key-1');

    expect($again->id)->toBe($first->id)
        ->and($this->enrollment->fresh()->balance)->toBe(100_000);

    $other = Enrollment::factory()->create(['group_id' => $this->group->id]);
    expect(fn () => $finance->receivePayment($other, $this->cashbox->fresh(), 100_000, idempotencyKey: 'key-1'))->toThrow(ValidationException::class);
});

it('only lets a teacher of the branch be set on a lesson', function () {
    $lesson = edgeLesson($this->group, '2026-11-03');
    $outsider = userWithRole(Role::Teacher);
    $notTeacher = userWithRole(Role::Manager, [$this->group->branch]);

    $this->patch(route('lessons.update', $lesson), ['action' => 'teacher', 'teacher_id' => $outsider->id])->assertSessionHasErrors('teacher_id');
    $this->patch(route('lessons.update', $lesson), ['action' => 'teacher', 'teacher_id' => $notTeacher->id])->assertSessionHasErrors('teacher_id');
    expect($lesson->fresh()->teacher_id)->toBe($this->teacherA->id);
});

it('pays a salary only from a cashbox of a branch the teacher works in', function () {
    $lesson = edgeLesson($this->group, '2026-11-03');
    app(FinanceService::class)->receivePayment($this->enrollment->fresh(), $this->cashbox, 50_000);
    edgeMark($lesson, $this->enrollment, AttendanceStatus::Present);
    $foreignCashbox = Cashbox::factory()->create(['balance' => 0]);
    app(FinanceService::class)->receivePayment(Enrollment::factory()->create(['group_id' => Group::factory()->priced()->create(['branch_id' => $foreignCashbox->branch_id])->id]), $foreignCashbox, 100_000);

    $this->post(route('teachers.payouts.store', $this->teacherA), ['cashbox_id' => $foreignCashbox->id, 'amount' => 25_000])->assertSessionHasErrors('cashbox_id');
    $this->post(route('teachers.payouts.store', $this->teacherA), ['cashbox_id' => $this->cashbox->id, 'amount' => 25_000])->assertSessionHasNoErrors();

    expect($this->teacherA->fresh()->balance)->toBe(0);
});

it('does not save a new price when a current student discount would not divide', function () {
    $this->enrollment->update(['discount_type' => 'percent', 'discount_value' => 10]);

    // 612 060 / 12 = 51 005, but with 10% off 550 854 does not divide by 12.
    $this->post(route('groups.prices.store', $this->group), ['monthly_price' => 612_060, 'lessons_per_month' => 12, 'effective_from' => '2026-12'])
        ->assertSessionHasErrors('monthly_price');
    $this->post(route('groups.prices.store', $this->group), ['monthly_price' => 720_000, 'lessons_per_month' => 12, 'effective_from' => '2026-12'])
        ->assertSessionHasNoErrors();
});
