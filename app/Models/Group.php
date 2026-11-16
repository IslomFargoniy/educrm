<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'name', 'subject', 'teacher_id', 'room_id', 'teacher_percent', 'days', 'start_time', 'end_time', 'start_date', 'end_date', 'status'])]
class Group extends Model
{
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
