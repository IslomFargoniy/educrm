<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pass of a student through a face terminal (entrance or exit).
 */
#[Fillable(['student_id', 'device_id', 'branch_id', 'employee_no', 'event_serial', 'direction', 'scanned_at', 'photo'])]
class StudentVisit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
