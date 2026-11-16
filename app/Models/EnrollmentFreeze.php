<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['enrollment_id', 'from_date', 'to_date', 'reason', 'created_by'])]
class EnrollmentFreeze extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
