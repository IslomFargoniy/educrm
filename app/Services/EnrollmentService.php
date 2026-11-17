<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adding students to groups, moving them between groups and changing their terms.
 */
class EnrollmentService
{
    public function __construct(private FinanceService $finance, private CoverageService $coverage) {}

    /**
     * @param  array{trial_lessons?: int, discount_type?: string|null, discount_value?: int, teacher_percent?: int|null}  $terms
     */
    public function enroll(Student $student, Group $group, CarbonInterface $joinedAt, array $terms = []): Enrollment
    {
        $alreadyStudying = $group->enrollments()->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Trial, EnrollmentStatus::Active])->exists();

        if ($alreadyStudying) {
            throw ValidationException::withMessages(['group_id' => __('The student already studies in this group.')]);
        }

        $enrollment = new Enrollment([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'joined_at' => $joinedAt,
            'trial_lessons' => $terms['trial_lessons'] ?? 0,
            'status' => ($terms['trial_lessons'] ?? 0) > 0 ? EnrollmentStatus::Trial : EnrollmentStatus::Active,
            'discount_type' => $terms['discount_type'] ?? null,
            'discount_value' => $terms['discount_value'] ?? 0,
            'teacher_percent' => $terms['teacher_percent'] ?? null,
        ]);
        $enrollment->setRelation('group', $group);
        $this->assertDiscountDivisible($enrollment, $joinedAt);
        $enrollment->save();

        return $enrollment;
    }

    /**
     * @param  array{discount_type?: string|null, discount_value?: int, teacher_percent?: int|null}  $terms
     */
    public function updateTerms(Enrollment $enrollment, array $terms): void
    {
        $enrollment->fill([
            'discount_type' => $terms['discount_type'] ?? null,
            'discount_value' => ($terms['discount_type'] ?? null) ? ($terms['discount_value'] ?? 0) : 0,
            'teacher_percent' => $terms['teacher_percent'] ?? null,
        ]);
        $this->assertDiscountDivisible($enrollment, today());
        $enrollment->save();
    }

    /**
     * Move the student to another group: unused money moves too and the monthly
     * lesson limit is shared between both groups for the current month.
     */
    public function transfer(Enrollment $enrollment, Group $newGroup, CarbonInterface $date): Enrollment
    {
        if (! $enrollment->status->isStudying()) {
            throw ValidationException::withMessages(['group_id' => __('The student does not study in this group.')]);
        }

        return DB::transaction(function () use ($enrollment, $newGroup, $date) {
            $new = $this->enroll($enrollment->student, $newGroup, $date, [
                'discount_type' => $enrollment->discount_type?->value,
                'discount_value' => $enrollment->discount_value,
            ]);
            $new->update(['transferred_from_id' => $enrollment->id, 'status' => EnrollmentStatus::Active]);
            $enrollment->update(['status' => EnrollmentStatus::Transferred, 'left_at' => $date->copy()->subDay()]);

            $funds = $this->coverage->availableFunds($enrollment);

            if ($funds > 0) {
                $this->finance->transferBalance($enrollment, $new, $funds);
            }

            return $new;
        });
    }

    public function leave(Enrollment $enrollment, CarbonInterface $date): void
    {
        $enrollment->update(['status' => EnrollmentStatus::Left, 'left_at' => $date]);
    }

    public function freeze(Enrollment $enrollment, CarbonInterface $from, CarbonInterface $to, ?string $reason): void
    {
        if ($to->lt($from)) {
            throw ValidationException::withMessages(['to_date' => __('The end date must be after the start date.')]);
        }

        $enrollment->freezes()->create([
            'from_date' => $from,
            'to_date' => $to,
            'reason' => $reason,
            'created_by' => auth()->id(),
        ]);
    }

    private function assertDiscountDivisible(Enrollment $enrollment, CarbonInterface $date): void
    {
        if ($enrollment->discount_type === null || $enrollment->discount_value === 0) {
            return;
        }

        if ($enrollment->discount_type === DiscountType::Percent && $enrollment->discount_value > 100) {
            throw ValidationException::withMessages(['discount_value' => __('The discount cannot be more than 100%.')]);
        }

        $price = $enrollment->group->priceFor($date) ?? $enrollment->group->prices()->first();

        if ($price === null) {
            return;
        }

        $discounted = $enrollment->discountedMonthlyPrice($price->monthly_price);

        if ($discounted % $price->lessons_per_month !== 0) {
            throw ValidationException::withMessages([
                'discount_value' => __('After the discount the price (:price) must divide by :lessons lessons without remainder.', [
                    'price' => number_format($discounted, 0, '.', ' '),
                    'lessons' => $price->lessons_per_month,
                ]),
            ]);
        }
    }
}
