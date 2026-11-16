<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['date', 'branch_id', 'name'])]
class Holiday extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Holidays that apply to the given branch (branch-specific or global).
     *
     * @param  Builder<Holiday>  $query
     */
    public function scopeForBranch(Builder $query, int $branchId): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
    }

    /**
     * Whether the given date is a holiday for the branch.
     */
    public static function isHoliday(CarbonInterface $date, int $branchId): bool
    {
        return static::query()->forBranch($branchId)->whereDate('date', $date)->exists();
    }
}
