<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lesson_id', 'enrollment_id', 'status', 'note', 'marked_by'])]
class Attendance extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => AttendanceStatus::class];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return HasMany<AttendanceAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(AttendanceAudit::class);
    }
}
