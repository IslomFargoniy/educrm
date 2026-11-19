<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A course the center teaches (English, IELTS, Math...). Groups run a course; leads ask for one.
 */
#[Fillable(['name', 'is_active'])]
class Course extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Groups and leads keep the course name for display: follow a rename.
        static::updated(function (Course $course): void {
            if ($course->wasChanged('name')) {
                Group::query()->where('course_id', $course->id)->update(['subject' => $course->name]);
                Lead::query()->where('course_id', $course->id)->update(['interested_course' => $course->name]);
            }
        });
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * Active courses, plus the given one even when it was disabled (to keep a saved choice).
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function options(?int $includeId = null): array
    {
        return self::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($includeId, fn ($w) => $w->orWhere('id', $includeId)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Course $course) => ['id' => $course->id, 'name' => $course->name])
            ->values()
            ->toArray();
    }
}
