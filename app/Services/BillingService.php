<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ChargeStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\TransactionType;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCharge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns attendance into lesson charges.
 *
 * Rules: a lesson costs (monthly price − discount) / N, where N is the group's
 * lessons per month; at most N lessons are billed per calendar month (counted
 * across groups when the student was moved between groups); excused absences
 * are not billed; trial lessons wait for the trial decision.
 */
class BillingService
{
    public function __construct(private Ledger $ledger, private CoverageService $coverage) {}

    /**
     * Bring the charge of an attendance in line with its current status.
     */
    public function syncAttendance(Attendance $attendance): void
    {
        $existing = $attendance->lesson->charges()
            ->where('enrollment_id', $attendance->enrollment_id)
            ->whereIn('status', [ChargeStatus::Active, ChargeStatus::PendingTrial])
            ->first();

        if ($existing !== null && $attendance->status->isBillable() && $existing->teacher_id === $attendance->lesson->teacher_id) {
            $existing->update(['attendance_id' => $attendance->id]);

            return;
        }

        if ($existing !== null) {
            $this->reverseCharge($existing);
        }

        $this->chargeFor($attendance);
    }

    /**
     * Re-bill a whole lesson, e.g. after the teacher was changed to a substitute.
     */
    public function rebillLesson(Lesson $lesson): void
    {
        $lesson->charges()->whereIn('status', [ChargeStatus::Active, ChargeStatus::PendingTrial])->get()
            ->each(fn (LessonCharge $charge) => $this->reverseCharge($charge));

        $lesson->attendances()->with('enrollment.group', 'enrollment.student', 'lesson')->get()
            ->each(fn (Attendance $attendance) => $this->chargeFor($attendance));
    }

    /**
     * Reverse every charge of a lesson (lesson cancelled after it was marked).
     */
    public function reverseLesson(Lesson $lesson): void
    {
        $lesson->charges()->whereIn('status', [ChargeStatus::Active, ChargeStatus::PendingTrial])->get()
            ->each(fn (LessonCharge $charge) => $this->reverseCharge($charge));
    }

    public function chargeFor(Attendance $attendance): ?LessonCharge
    {
        if (! $attendance->status->isBillable()) {
            return null;
        }

        $lesson = $attendance->lesson;
        $enrollment = $attendance->enrollment->loadMissing('group', 'student');
        $price = $enrollment->group->priceFor($lesson->date);

        if ($price === null || $price->lessons_per_month === 0) {
            return null;
        }

        $month = $lesson->date->format('Y-m');
        $lessonsPerMonth = $price->lessons_per_month;

        if (! $this->hasCapacity($enrollment, $month, $lessonsPerMonth)) {
            return null;
        }

        $amount = intdiv($enrollment->discountedMonthlyPrice($price->monthly_price), $lessonsPerMonth);
        $isTrial = $enrollment->status === EnrollmentStatus::Trial;

        $charge = $enrollment->charges()->create([
            'lesson_id' => $lesson->id,
            'attendance_id' => $attendance->id,
            'lesson_date' => $lesson->date,
            'month' => $month,
            'amount' => $amount,
            'teacher_id' => $lesson->teacher_id,
            'teacher_percent' => $enrollment->effectiveTeacherPercent(),
            'is_substitute' => $lesson->is_substitute,
            'status' => $isTrial ? ChargeStatus::PendingTrial : ChargeStatus::Active,
        ]);

        if (! $isTrial) {
            $this->debit($charge, $attendance->status, $lessonsPerMonth);
        }

        return $charge;
    }

    /**
     * Cancel a charge and give the money back to the student's balance.
     */
    public function reverseCharge(LessonCharge $charge): void
    {
        if ($charge->status === ChargeStatus::PendingTrial) {
            $charge->update(['status' => ChargeStatus::Cancelled]);

            return;
        }

        $enrollment = $charge->enrollment->loadMissing('group', 'student');
        $this->coverage->releaseCharge($charge);
        $refund = $charge->amount - $charge->written_off_amount;

        $this->ledger->record(TransactionType::ChargeReversal, $refund, [[$enrollment, $refund]], [
            'branch_id' => $enrollment->group->branch_id,
            'meta' => $this->chargeMeta($charge, $enrollment),
        ]);

        $charge->update(['status' => ChargeStatus::Reversed]);
        $this->coverage->cover($enrollment);
    }

    /**
     * Apply the trial decision: continue (bill the trial lessons) or stop (drop them).
     */
    public function decideTrial(Enrollment $enrollment, bool $continues): void
    {
        DB::transaction(function () use ($enrollment, $continues) {
            $pending = $enrollment->charges()->where('status', ChargeStatus::PendingTrial)
                ->with('lesson', 'enrollment')->orderBy('lesson_date')->get();

            if (! $continues) {
                $pending->each->update(['status' => ChargeStatus::Cancelled]);
                $enrollment->update(['status' => EnrollmentStatus::Left, 'left_at' => today()]);

                return;
            }

            $enrollment->update(['status' => EnrollmentStatus::Active]);

            foreach ($pending as $charge) {
                $price = $enrollment->group->priceFor($charge->lesson_date);
                $lessonsPerMonth = $price?->lessons_per_month ?? 0;
                $charge->update(['status' => ChargeStatus::Cancelled]);

                if ($lessonsPerMonth === 0 || ! $this->hasCapacity($enrollment, $charge->month, $lessonsPerMonth)) {
                    continue;
                }

                $charge->update(['status' => ChargeStatus::Active]);
                $attendanceStatus = $charge->attendance_id !== null
                    ? Attendance::query()->find($charge->attendance_id)?->status ?? AttendanceStatus::Present
                    : AttendanceStatus::Present;
                $this->debit($charge, $attendanceStatus, $lessonsPerMonth);
            }
        });
    }

    /**
     * Number of lessons already billed for the enrollment in the month.
     */
    public function billedLessonsInMonth(Enrollment $enrollment, string $month): int
    {
        return $enrollment->charges()
            ->where('month', $month)
            ->whereIn('status', [ChargeStatus::Active, ChargeStatus::PendingTrial])
            ->whereNotNull('lesson_id')
            ->count();
    }

    /**
     * Monthly lesson limit for the enrollment, reduced by lessons billed in groups
     * the student was moved from during the same month.
     */
    public function monthlyLimit(Enrollment $enrollment, string $month, int $lessonsPerMonth): int
    {
        $usedShare = 0.0;
        $previous = $enrollment->transferredFrom;
        $guard = 0;

        while ($previous !== null && $guard++ < 10) {
            $previousPrice = $previous->group->priceFor(Carbon::parse($month.'-01'));
            $previousLessons = $previousPrice?->lessons_per_month ?? 0;

            if ($previousLessons > 0) {
                $usedShare += $this->billedLessonsInMonth($previous, $month) / $previousLessons;
            }

            $previous = $previous->transferredFrom;
        }

        return max(0, (int) floor($lessonsPerMonth * (1 - $usedShare) + 1e-9));
    }

    private function hasCapacity(Enrollment $enrollment, string $month, int $lessonsPerMonth): bool
    {
        return $this->billedLessonsInMonth($enrollment, $month) < $this->monthlyLimit($enrollment, $month, $lessonsPerMonth);
    }

    private function debit(LessonCharge $charge, AttendanceStatus $attendanceStatus, int $lessonsPerMonth): void
    {
        $enrollment = $charge->enrollment->loadMissing('group', 'student');

        $this->ledger->record(TransactionType::LessonCharge, $charge->amount, [[$enrollment, -$charge->amount]], [
            'branch_id' => $enrollment->group->branch_id,
            'meta' => [
                ...$this->chargeMeta($charge, $enrollment),
                'attendance' => $attendanceStatus->value,
                'seq' => $this->billedLessonsInMonth($enrollment, $charge->month),
                'lessons_per_month' => $lessonsPerMonth,
            ],
        ]);

        $this->coverage->cover($enrollment);
    }

    /**
     * @return array<string, mixed>
     */
    private function chargeMeta(LessonCharge $charge, Enrollment $enrollment): array
    {
        return [
            'student' => $enrollment->student->full_name,
            'student_id' => $enrollment->student_id,
            'group' => $enrollment->group->name,
            'group_id' => $enrollment->group_id,
            'lesson_id' => $charge->lesson_id,
            'lesson_date' => $charge->lesson_date->toDateString(),
            'charge_id' => $charge->id,
        ];
    }
}
