<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\EnrollmentStatus;
use Carbon\CarbonInterface;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_id', 'group_id', 'joined_at', 'left_at', 'leave_reason', 'leave_note', 'status', 'trial_lessons', 'transferred_from_id', 'teacher_percent', 'discount_type', 'discount_value'])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'left_at' => 'date',
            'status' => EnrollmentStatus::class,
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'teacher_percent' => 'integer',
            'trial_lessons' => 'integer',
            'balance' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function transferredFrom(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'transferred_from_id');
    }

    /**
     * @return HasMany<EnrollmentFreeze, $this>
     */
    public function freezes(): HasMany
    {
        return $this->hasMany(EnrollmentFreeze::class);
    }

    /**
     * @return HasMany<LessonCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(LessonCharge::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Teacher percent applied to this student's lessons.
     */
    public function effectiveTeacherPercent(): int
    {
        return $this->teacher_percent ?? $this->group->teacher_percent;
    }

    /**
     * Monthly price after the student's discount.
     */
    public function discountedMonthlyPrice(int $monthlyPrice): int
    {
        $discount = match ($this->discount_type) {
            DiscountType::Percent => intdiv($monthlyPrice * $this->discount_value, 100),
            DiscountType::Amount => $this->discount_value,
            default => 0,
        };

        return max(0, $monthlyPrice - $discount);
    }

    /**
     * Whether the student is frozen on the given date.
     */
    public function isFrozenOn(CarbonInterface $date): bool
    {
        return $this->freezes
            ->contains(fn (EnrollmentFreeze $freeze) => $date->betweenIncluded($freeze->from_date, $freeze->to_date));
    }

    /**
     * Whether the student belongs to the lesson list on the given date.
     */
    public function attendsOn(CarbonInterface $date): bool
    {
        if ($this->joined_at->gt($date)) {
            return false;
        }

        if ($this->left_at !== null && $this->left_at->lt($date)) {
            return false;
        }

        return ! $this->isFrozenOn($date);
    }
}
