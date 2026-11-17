<?php

use App\Enums\AttendanceStatus;
use App\Enums\CashboxMethod;
use App\Enums\ChargeStatus;
use App\Enums\LedgerAccount;
use App\Enums\LessonStatus;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\Branch;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\ExpenseCategory;
use App\Models\Group;
use App\Models\Holiday;
use App\Models\LedgerEntry;
use App\Models\Lesson;
use App\Models\LessonCharge;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BillingService;
use App\Services\CoverageService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use App\Services\LessonScheduler;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

/**
 * One full month (December 2026) of a small center, day by day: payments,
 * attendance, trials, discounts, a transfer, a freeze, substitutes, a holiday,
 * a payment reversal, a refund, a debt write-off, expenses and payouts.
 * Final balances are checked against hand-calculated values and ledger invariants.
 */
it('simulates a full month and keeps every balance consistent', function () {
    $this->travelTo('2026-12-01 08:00:00');
    $admin = userWithRole(Role::Superadmin);
    $this->actingAs($admin);

    $finance = app(FinanceService::class);
    $enrollments = app(EnrollmentService::class);
    $billing = app(BillingService::class);
    $attendance = app(AttendanceService::class);

    $branch = Branch::factory()->create();
    $cash = Cashbox::factory()->create(['branch_id' => $branch->id, 'name' => 'Naqd', 'method' => CashboxMethod::Cash]);
    $click = Cashbox::factory()->create(['branch_id' => $branch->id, 'name' => 'Click', 'method' => CashboxMethod::Click]);

    $aziz = User::factory()->create(['name' => 'Aziz']);
    $bobur = User::factory()->create(['name' => 'Bobur']);
    $malika = User::factory()->create(['name' => 'Malika']);

    $groups = [
        'A' => Group::factory()->priced(600_000, 12, '2026-12-01')->create(['branch_id' => $branch->id, 'name' => 'Ingliz', 'days' => [1, 3, 5], 'teacher_id' => $aziz->id, 'teacher_percent' => 40, 'start_date' => '2026-12-01']),
        'B' => Group::factory()->priced(480_000, 12, '2026-12-01')->create(['branch_id' => $branch->id, 'name' => 'Matematika', 'days' => [2, 4, 6], 'teacher_id' => $bobur->id, 'teacher_percent' => 50, 'start_date' => '2026-12-01']),
        'C' => Group::factory()->priced(1_000_000, 20, '2026-12-01')->create(['branch_id' => $branch->id, 'name' => 'IELTS', 'days' => [1, 2, 3, 4, 5], 'teacher_id' => $malika->id, 'teacher_percent' => 35, 'start_date' => '2026-12-01']),
    ];

    foreach ($groups as $group) {
        app(LessonScheduler::class)->generate($group, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-31'));
    }

    $e = [];
    $enroll = function (string $key, string $group, string $date, array $terms = []) use (&$e, $enrollments, $groups, $branch): Enrollment {
        $student = Student::factory()->create(['full_name' => $key, 'branch_id' => $branch->id]);

        return $e[$key] = $enrollments->enroll($student, $groups[$group], CarbonImmutable::parse($date), $terms);
    };
    $pay = function (string $key, Cashbox $cashbox, int $amount) use ($finance, &$e): Payment {
        return $finance->receivePayment($e[$key]->fresh(), $cashbox->fresh(), $amount);
    };

    $enroll('S1 Ali', 'A', '2026-12-01');
    $enroll('S2 Vali', 'A', '2026-12-01');
    $enroll('S3 Sardor', 'A', '2026-12-01');
    $enroll('S4 Madina', 'A', '2026-12-01', ['discount_type' => 'amount', 'discount_value' => 60_000, 'teacher_percent' => 50]);
    $enroll('S5 Jasur', 'B', '2026-12-01', ['discount_type' => 'percent', 'discount_value' => 10]);
    $enroll('S6 Nilufar', 'B', '2026-12-01', ['trial_lessons' => 2]);
    $enroll('S7 Akmal', 'B', '2026-12-01', ['trial_lessons' => 2]);
    $enroll('S8 Zarina', 'A', '2026-12-01');
    $enroll('S9 Shoxrux', 'C', '2026-12-01');
    $enroll('S10 Dilshod', 'C', '2026-12-01');
    $enroll('S11 Kamola', 'B', '2026-12-01');
    $enroll('S13 Sevara', 'A', '2026-12-01');
    $enroll('S14 Otabek', 'B', '2026-12-01');
    $enrollments->freeze($e['S9 Shoxrux'], CarbonImmutable::parse('2026-12-07'), CarbonImmutable::parse('2026-12-13'), 'Kasal');

    $kamolaFirstPayment = null;

    /** @var array<string, array{morning?: Closure, evening?: Closure}> $events */
    $events = [
        '2026-12-01' => ['morning' => function () use ($pay, $cash, $click, &$kamolaFirstPayment) {
            $pay('S1 Ali', $cash, 600_000);
            $pay('S2 Vali', $cash, 300_000);
            $pay('S4 Madina', $click, 540_000);
            $pay('S5 Jasur', $cash, 432_000);
            $pay('S8 Zarina', $cash, 600_000);
            $pay('S9 Shoxrux', $click, 1_000_000);
            $pay('S10 Dilshod', $cash, 1_000_000);
            $kamolaFirstPayment = $pay('S11 Kamola', $cash, 480_000);
            $pay('S13 Sevara', $cash, 600_000);
            $pay('S14 Otabek', $cash, 200_000);
        }],
        '2026-12-02' => ['morning' => function () use ($finance, $pay, $cash, &$kamolaFirstPayment) {
            $finance->reversePayment(Payment::query()->findOrFail($kamolaFirstPayment->id), 'Boshqa o\'quvchiga yozilib ketgan');
            $pay('S11 Kamola', $cash, 480_000);
        }],
        '2026-12-05' => ['morning' => function () use ($billing, $pay, $cash, &$e) {
            $billing->decideTrial($e['S6 Nilufar']->fresh(), true);
            $billing->decideTrial($e['S7 Akmal']->fresh(), false);
            $pay('S6 Nilufar', $cash, 480_000);
        }],
        '2026-12-09' => ['morning' => fn () => substitute($groups['A'], '2026-12-09', $bobur)],
        '2026-12-14' => ['morning' => function () use ($enrollments, $groups, &$e) {
            $e['S8 Zarina'] = $enrollments->transfer($e['S8 Zarina']->fresh(), $groups['C'], CarbonImmutable::parse('2026-12-14'));
        }],
        '2026-12-15' => ['morning' => function () use ($enroll, $pay, $click) {
            $enroll('S12 Bekzod', 'C', '2026-12-15');
            $pay('S12 Bekzod', $click, 500_000);
            $pay('S2 Vali', $click, 300_000);
        }],
        '2026-12-16' => ['morning' => function () use ($enrollments, $finance, $cash, &$e) {
            $enrollments->leave($e['S13 Sevara']->fresh(), CarbonImmutable::parse('2026-12-15'));
            $finance->refund($e['S13 Sevara']->fresh(), $cash->fresh(), app(CoverageService::class)->availableFunds($e['S13 Sevara']->fresh()), 'Ketdi');
        }],
        '2026-12-20' => ['morning' => function () use ($branch, $pay, $cash) {
            $holiday = Holiday::query()->create(['branch_id' => $branch->id, 'date' => '2026-12-31', 'name' => 'Yangi yil arafasi']);
            app(LessonScheduler::class)->applyHoliday($holiday);
            $pay('S8 Zarina', $cash, 200_000);
        }],
        '2026-12-22' => ['morning' => fn () => substitute($groups['C'], '2026-12-22', $aziz)],
        '2026-12-25' => ['evening' => function () use ($finance, $cash, $click) {
            $finance->expense($cash->fresh(), ExpenseCategory::query()->create(['name' => 'Ijara']), 1_500_000, 'Dekabr ijarasi');
            $finance->expense($click->fresh(), ExpenseCategory::query()->create(['name' => 'Reklama']), 300_000, 'Instagram');
        }],
        '2026-12-26' => ['evening' => fn () => $finance->transferBetweenCashboxes($click->fresh(), $cash->fresh(), $click->fresh()->balance, 'Inkassatsiya')],
        '2026-12-31' => ['evening' => function () use ($finance, $cash, &$e) {
            $finance->writeOffDebt($e['S14 Otabek']->fresh(), 'Kelishuv bo\'yicha');

            foreach (User::query()->whereIn('name', ['Aziz', 'Bobur', 'Malika'])->get() as $teacher) {
                $finance->payTeacher($teacher, $cash->fresh(), $teacher->balance);
            }
        }],
    ];

    $statusFor = function (string $student, string $date): AttendanceStatus {
        return match (true) {
            $student === 'S2 Vali' && $date === '2026-12-07' => AttendanceStatus::Absent,
            $student === 'S10 Dilshod' && in_array($date, ['2026-12-03', '2026-12-10', '2026-12-17'], true) => AttendanceStatus::Excused,
            default => AttendanceStatus::Present,
        };
    };

    foreach (CarbonPeriod::create('2026-12-01', '2026-12-31') as $day) {
        $date = $day->toDateString();
        $this->travelTo($date.' 08:00:00');
        ($events[$date]['morning'] ?? fn () => null)();

        $this->travelTo($date.' 18:00:00');

        foreach (Lesson::query()->whereDate('date', $date)->where('status', LessonStatus::Planned)->get() as $lesson) {
            $statuses = $attendance->rosterFor($lesson)
                ->mapWithKeys(fn (Enrollment $enrollment) => [$enrollment->id => $statusFor($enrollment->student->full_name, $date)->value])
                ->all();
            $attendance->save($lesson, $statuses, $admin);
        }

        ($events[$date]['evening'] ?? fn () => null)();
    }

    $balanceOf = fn (string $key): int => $e[$key]->fresh()->balance;
    $billedCount = fn (string $key) => $e[$key]->charges()->where('status', ChargeStatus::Active)->count();

    // Lessons: A = 13 (Mon/Wed/Fri), B = 14 − 1 holiday, C = 23 weekdays − 1 holiday.
    expect(Lesson::query()->where('group_id', $groups['A']->id)->where('status', LessonStatus::Held)->count())->toBe(13)
        ->and(Lesson::query()->where('group_id', $groups['B']->id)->where('status', LessonStatus::Held)->count())->toBe(13)
        ->and(Lesson::query()->where('group_id', $groups['C']->id)->where('status', LessonStatus::Held)->count())->toBe(22)
        ->and(Lesson::query()->whereDate('date', '2026-12-31')->where('status', LessonStatus::Cancelled)->count())->toBe(2);

    // Students.
    expect($balanceOf('S1 Ali'))->toBe(0)->and($billedCount('S1 Ali'))->toBe(12)
        ->and($balanceOf('S2 Vali'))->toBe(0)
        ->and($balanceOf('S3 Sardor'))->toBe(-600_000)
        ->and($balanceOf('S4 Madina'))->toBe(0)
        ->and($balanceOf('S5 Jasur'))->toBe(0)
        ->and($balanceOf('S6 Nilufar'))->toBe(0)->and($billedCount('S6 Nilufar'))->toBe(12)
        ->and($e['S7 Akmal']->fresh()->charges()->where('status', ChargeStatus::Active)->count())->toBe(0)
        ->and($balanceOf('S8 Zarina'))->toBe(0)->and($billedCount('S8 Zarina'))->toBe(11)
        ->and($balanceOf('S9 Shoxrux'))->toBe(150_000)->and($billedCount('S9 Shoxrux'))->toBe(17)
        ->and($balanceOf('S10 Dilshod'))->toBe(50_000)->and($billedCount('S10 Dilshod'))->toBe(19)
        ->and($balanceOf('S11 Kamola'))->toBe(0)
        ->and($balanceOf('S12 Bekzod'))->toBe(-100_000)
        ->and($balanceOf('S13 Sevara'))->toBe(0)
        ->and($balanceOf('S14 Otabek'))->toBe(0);

    // Teachers earned (all paid out on Dec 31).
    $earned = fn (User $teacher) => (int) LedgerEntry::query()
        ->where('account_type', LedgerAccount::Teacher)->where('account_id', $teacher->id)
        ->whereHas('transaction', fn ($q) => $q->where('type', '!=', TransactionType::TeacherPayout))
        ->sum('amount');

    expect($earned($aziz))->toBe(937_500)
        ->and($earned($bobur))->toBe(898_500)
        ->and($earned($malika))->toBe(927_500)
        ->and($aziz->fresh()->balance)->toBe(0)
        ->and($bobur->fresh()->balance)->toBe(0)
        ->and($malika->fresh()->balance)->toBe(0);

    // Cash: 7 232 000 in − 300 000 refund − 1 800 000 expenses − 2 763 500 payouts.
    expect($cash->fresh()->balance)->toBe(2_368_500)
        ->and($click->fresh()->balance)->toBe(0)
        ->and($finance->prepaidTotal([$branch->id]))->toBe(200_000)
        ->and($finance->freeProfit([$branch->id]))->toBe(2_168_500);

    // Owner can take exactly the free profit; more needs confirmation.
    expect(fn () => $finance->withdrawProfit($cash->fresh(), 2_168_501, false))->toThrow(ValidationException::class);
    $finance->withdrawProfit($cash->fresh(), 2_168_500, false);
    expect($cash->fresh()->balance)->toBe(200_000)
        ->and($finance->freeProfit([$branch->id]))->toBe(0);

    // Ledger invariants: cached balance = sum of entries = last balance_after, for every account.
    $accounts = [
        [LedgerAccount::Cashbox, Cashbox::query()->get()],
        [LedgerAccount::Teacher, User::query()->get()],
        [LedgerAccount::Enrollment, Enrollment::query()->get()],
    ];

    foreach ($accounts as [$type, $models]) {
        foreach ($models as $model) {
            $entries = LedgerEntry::query()->where('account_type', $type)->where('account_id', $model->id)->orderBy('id');
            expect((int) $entries->sum('amount'))->toBe($model->balance)
                ->and($entries->get()->last()?->balance_after ?? 0)->toBe($model->balance);
        }
    }

    // Every active charge is fully covered or written off except real debts, and coverage never exceeds the amount.
    expect(LessonCharge::query()->whereColumn('covered_amount', '>', 'amount')->count())->toBe(0);
});

function substitute(Group $group, string $date, User $teacher): void
{
    Lesson::query()->where('group_id', $group->id)->whereDate('date', $date)->firstOrFail()
        ->update(['teacher_id' => $teacher->id, 'is_substitute' => true]);
}
