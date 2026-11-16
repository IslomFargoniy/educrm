<?php

namespace App\Services;

use App\Enums\ChargeStatus;
use App\Enums\TransactionType;
use App\Models\ChargeCoverage;
use App\Models\Enrollment;
use App\Models\LessonCharge;
use App\Models\User;

/**
 * Matches money on a student's balance with billed lessons (oldest first) and
 * credits the teacher share of every covered lesson to the teacher who taught it.
 */
class CoverageService
{
    public function __construct(private Ledger $ledger) {}

    /**
     * Money received for the enrollment that is not yet matched to a lesson.
     */
    public function availableFunds(Enrollment $enrollment): int
    {
        $uncovered = (int) $enrollment->charges()
            ->where('status', ChargeStatus::Active)
            ->selectRaw('COALESCE(SUM(amount - covered_amount - written_off_amount), 0) as total')
            ->value('total');

        return $enrollment->fresh()->balance + $uncovered;
    }

    /**
     * Cover unpaid lessons with available funds, oldest lesson first.
     */
    public function cover(Enrollment $enrollment): void
    {
        $funds = $this->availableFunds($enrollment);

        if ($funds <= 0) {
            return;
        }

        $charges = $enrollment->charges()
            ->where('status', ChargeStatus::Active)
            ->whereRaw('amount > covered_amount + written_off_amount')
            ->orderBy('lesson_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($charges as $charge) {
            if ($funds <= 0) {
                break;
            }

            $take = min($funds, $charge->uncoveredAmount());
            $this->coverCharge($enrollment, $charge, $take);
            $funds -= $take;
        }
    }

    /**
     * Undo coverages (newest first) when the balance no longer backs them, e.g. after a payment reversal.
     */
    public function uncoverIfNeeded(Enrollment $enrollment): void
    {
        if ($this->availableFunds($enrollment) >= 0) {
            return;
        }

        $coverages = ChargeCoverage::query()
            ->whereNull('reversed_at')
            ->whereHas('charge', fn ($q) => $q->where('enrollment_id', $enrollment->id))
            ->orderByDesc('covered_at')
            ->orderByDesc('id')
            ->get();

        foreach ($coverages as $coverage) {
            if ($this->availableFunds($enrollment) >= 0) {
                break;
            }

            $this->releaseCoverage($coverage);
        }

        $this->cover($enrollment);
    }

    /**
     * Release every coverage of a charge (used when the charge itself is reversed).
     */
    public function releaseCharge(LessonCharge $charge): void
    {
        $charge->coverages()->whereNull('reversed_at')->get()
            ->each(fn (ChargeCoverage $coverage) => $this->releaseCoverage($coverage));
    }

    private function coverCharge(Enrollment $enrollment, LessonCharge $charge, int $take): void
    {
        $alreadyPaidToTeacher = (int) $charge->coverages()->whereNull('reversed_at')->sum('teacher_amount');
        $newCovered = $charge->covered_amount + $take;
        $teacherAmount = max(0, $charge->teacherShareOf($newCovered) - $alreadyPaidToTeacher);

        $charge->update(['covered_amount' => $newCovered]);

        $coverage = $charge->coverages()->create([
            'amount' => $take,
            'teacher_amount' => $teacherAmount,
            'teacher_id' => $charge->teacher_id,
            'covered_at' => now(),
        ]);

        if ($teacherAmount === 0 || $charge->teacher_id === null) {
            return;
        }

        $enrollment->loadMissing('student', 'group');
        $teacher = User::query()->findOrFail($charge->teacher_id);

        $transaction = $this->ledger->record(TransactionType::TeacherEarning, $teacherAmount, [[$teacher, $teacherAmount]], [
            'branch_id' => $enrollment->group->branch_id,
            'meta' => [
                'student' => $enrollment->student->full_name,
                'student_id' => $enrollment->student_id,
                'group' => $enrollment->group->name,
                'group_id' => $enrollment->group_id,
                'lesson_id' => $charge->lesson_id,
                'lesson_date' => $charge->lesson_date->toDateString(),
                'lesson_amount' => $charge->amount,
                'covered' => $take,
                'percent' => $charge->teacher_percent,
                'is_substitute' => $charge->is_substitute,
                'is_late_payment' => $charge->lesson_date->lt(today()),
                'charge_id' => $charge->id,
            ],
        ]);

        $coverage->update(['transaction_id' => $transaction->id]);
    }

    private function releaseCoverage(ChargeCoverage $coverage): void
    {
        $charge = $coverage->charge;
        $coverage->update(['reversed_at' => now()]);
        $charge->update(['covered_amount' => $charge->covered_amount - $coverage->amount]);

        if ($coverage->teacher_amount === 0 || $coverage->teacher_id === null) {
            return;
        }

        $charge->loadMissing('enrollment.student', 'enrollment.group');
        $teacher = User::query()->findOrFail($coverage->teacher_id);

        $this->ledger->record(TransactionType::EarningReversal, $coverage->teacher_amount, [[$teacher, -$coverage->teacher_amount]], [
            'branch_id' => $charge->enrollment->group->branch_id,
            'reverses_id' => $coverage->transaction_id,
            'meta' => [
                'student' => $charge->enrollment->student->full_name,
                'student_id' => $charge->enrollment->student_id,
                'group' => $charge->enrollment->group->name,
                'group_id' => $charge->enrollment->group_id,
                'lesson_date' => $charge->lesson_date->toDateString(),
                'charge_id' => $charge->id,
            ],
        ]);
    }
}
