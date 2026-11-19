<?php

namespace App\Models;

use App\Concerns\HasPersonName;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'full_name', 'first_name', 'last_name', 'phone', 'source', 'interested_course', 'course_id', 'status', 'assigned_to', 'next_contact_at', 'trial_at', 'trial_group_id', 'student_id', 'note'])]
class Lead extends Model
{
    use HasPersonName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'next_contact_at' => 'date',
            'trial_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The course name is kept on the row for display; a chosen course always wins.
        static::saving(function (self $model): void {
            if ($model->isDirty('course_id')) {
                $model->interested_course = $model->course_id === null ? null : Course::query()->whereKey($model->course_id)->value('name');
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
     * Group of the first (trial) lesson.
     *
     * @return BelongsTo<Group, $this>
     */
    public function trialGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'trial_group_id');
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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<LeadNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }
}
