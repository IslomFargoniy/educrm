<?php

namespace App\Models;

use App\Concerns\HasPersonName;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['full_name', 'first_name', 'last_name', 'phone', 'birth_date', 'branch_id', 'user_id', 'lead_id', 'status', 'note'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, HasPersonName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return BelongsToMany<User, $this, Pivot, 'pivot'>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student')->withPivot('relation');
    }

    /**
     * Branch where the student was registered.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Students that study (or studied) in one of the given branches.
     *
     * @param  Builder<Student>  $query
     * @param  list<int>  $branchIds
     */
    public function scopeInBranches(Builder $query, array $branchIds): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereIn('branch_id', $branchIds)
            ->orWhereHas('enrollments.group', fn (Builder $g) => $g->whereIn('branch_id', $branchIds)));
    }
}
