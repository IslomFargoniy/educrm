<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lesson_charge_id', 'amount', 'teacher_amount', 'teacher_id', 'transaction_id', 'covered_at', 'reversed_at'])]
class ChargeCoverage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'covered_at' => 'datetime',
            'reversed_at' => 'datetime',
            'amount' => 'integer',
            'teacher_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LessonCharge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(LessonCharge::class, 'lesson_charge_id');
    }
}
