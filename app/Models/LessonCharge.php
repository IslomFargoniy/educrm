<?php

namespace App\Models;

use App\Enums\ChargeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['enrollment_id', 'lesson_id', 'attendance_id', 'lesson_date', 'month', 'amount', 'covered_amount', 'written_off_amount', 'teacher_id', 'teacher_percent', 'is_substitute', 'status'])]
class LessonCharge extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lesson_date' => 'date',
            'status' => ChargeStatus::class,
            'amount' => 'integer',
            'covered_amount' => 'integer',
            'written_off_amount' => 'integer',
            'teacher_percent' => 'integer',
            'is_substitute' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return HasMany<ChargeCoverage, $this>
     */
    public function coverages(): HasMany
    {
        return $this->hasMany(ChargeCoverage::class);
    }

    /**
     * Amount of the charge not yet covered by money or written off.
     */
    public function uncoveredAmount(): int
    {
        return $this->amount - $this->covered_amount - $this->written_off_amount;
    }

    /**
     * Teacher share of the covered amount so far.
     */
    public function teacherShareOf(int $coveredAmount): int
    {
        return intdiv($coveredAmount * $this->teacher_percent, 100);
    }
}
