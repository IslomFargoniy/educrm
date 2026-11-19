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

#[Fillable(['full_name', 'first_name', 'last_name', 'phone', 'birth_date', 'branch_id', 'user_id', 'lead_id', 'registered_by', 'status', 'note', 'face_photo', 'call_status', 'call_status_at', 'call_status_by'])]
class Student extends Model
{
    /** Call results for debt calls, shown as colour tags on the phone number. */
    public const CALL_STATUSES = ['cancelled', 'paid', 'not_processed', 'initial', 'call_back', 'no_answer'];

    /** @use HasFactory<StudentFactory> */
    use HasFactory, HasPersonName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['birth_date' => 'date', 'call_status_at' => 'datetime'];
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

    protected static function booted(): void
    {
        // Whoever adds the student takes them in (counts for the staff KPI).
        static::creating(function (Student $student): void {
            if ($student->registered_by === null && auth()->check()) {
                $student->setAttribute('registered_by', auth()->id());
            }
        });
    }

    /**
     * Staff member who took the student in.
     *
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Face terminals the student was written to.
     *
     * @return BelongsToMany<Device, $this, DeviceStudent, 'sync'>
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class)->using(DeviceStudent::class)->as('sync')->withPivot('status', 'error', 'synced_at')->withTimestamps();
    }

    /**
     * @return HasMany<StudentVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(StudentVisit::class);
    }

    /**
     * Branches whose terminals should know the student: own branch and branches of groups being studied.
     *
     * @return list<int>
     */
    public function faceBranchIds(): array
    {
        $groupBranches = $this->enrollments()
            ->whereIn('enrollments.status', ['trial', 'active'])
            ->join('groups', 'groups.id', '=', 'enrollments.group_id')
            ->pluck('groups.branch_id')
            ->all();

        return array_values(array_unique(array_map('intval', [$this->branch_id, ...$groupBranches])));
    }

    /**
     * Whether the student currently studies in some group (only they are kept on the terminals).
     */
    public function isStudying(): bool
    {
        return $this->enrollments()->whereIn('status', ['trial', 'active'])->exists();
    }
}
