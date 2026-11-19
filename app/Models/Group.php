<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int|null $students_count
 */
#[Fillable(['branch_id', 'name', 'subject', 'course_id', 'teacher_id', 'room_id', 'teacher_percent', 'days', 'start_time', 'end_time', 'start_date', 'end_date', 'status'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'teacher_percent' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The course name is kept on the row for display; a chosen course always wins.
        static::saving(function (self $model): void {
            if ($model->isDirty('course_id')) {
                $model->subject = $model->course_id === null ? null : Course::query()->whereKey($model->course_id)->value('name');
            }
        });
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<GroupPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(GroupPrice::class)->orderByDesc('effective_from');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Price record in force for the month of the given date.
     */
    public function priceFor(CarbonInterface $date): ?GroupPrice
    {
        return $this->prices()
            ->whereDate('effective_from', '<=', $date->copy()->startOfMonth())
            ->first();
    }
}
