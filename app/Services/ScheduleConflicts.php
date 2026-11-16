<?php

namespace App\Services;

use App\Enums\LessonStatus;
use App\Models\Group;
use App\Models\Lesson;

/**
 * Finds groups (or lessons) that use the same room or teacher at the same time.
 */
class ScheduleConflicts
{
    /**
     * @param  array{branch_id: int, days: list<int|string>, start_time: string, end_time: string, room_id?: int|null, teacher_id?: int|null}  $schedule
     * @return list<string>
     */
    public function forGroup(array $schedule, ?int $ignoreGroupId = null): array
    {
        $days = array_map('intval', $schedule['days']);
        $conflicts = [];

        $candidates = Group::query()
            ->where('status', 'active')
            ->when($ignoreGroupId, fn ($q) => $q->whereKeyNot($ignoreGroupId))
            ->where('start_time', '<', $schedule['end_time'])
            ->where('end_time', '>', $schedule['start_time'])
            ->where(function ($q) use ($schedule) {
                $q->when($schedule['room_id'] ?? null, fn ($r) => $r->orWhere('room_id', $schedule['room_id']))
                    ->when($schedule['teacher_id'] ?? null, fn ($r) => $r->orWhere('teacher_id', $schedule['teacher_id']));
            })
            ->with('room', 'teacher')
            ->get();

        foreach ($candidates as $group) {
            if (array_intersect($days, array_map('intval', $group->days)) === []) {
                continue;
            }

            if (($schedule['room_id'] ?? null) && $group->room_id === (int) $schedule['room_id']) {
                $conflicts[] = __('Room :room is busy: group :group (:from–:to).', [
                    'room' => $group->room?->name, 'group' => $group->name,
                    'from' => substr($group->start_time, 0, 5), 'to' => substr($group->end_time, 0, 5),
                ]);
            }

            if (($schedule['teacher_id'] ?? null) && $group->teacher_id === (int) $schedule['teacher_id']) {
                $conflicts[] = __('Teacher :teacher is busy: group :group (:from–:to).', [
                    'teacher' => $group->teacher?->name, 'group' => $group->name,
                    'from' => substr($group->start_time, 0, 5), 'to' => substr($group->end_time, 0, 5),
                ]);
            }
        }

        return $conflicts;
    }

    /**
     * Conflicts of a single lesson (make-up lesson or substitute teacher).
     *
     * @return list<string>
     */
    public function forLesson(Lesson $lesson): array
    {
        $others = Lesson::query()
            ->whereKeyNot($lesson->id)
            ->whereDate('date', $lesson->date)
            ->where('status', '!=', LessonStatus::Cancelled)
            ->where('start_time', '<', $lesson->end_time)
            ->where('end_time', '>', $lesson->start_time)
            ->where(fn ($q) => $q->where('room_id', $lesson->room_id)->orWhere('teacher_id', $lesson->teacher_id))
            ->with('group')
            ->get();

        return $others->map(fn (Lesson $other) => __('Time overlaps with group :group (:from–:to).', [
            'group' => $other->group->name,
            'from' => substr($other->start_time, 0, 5),
            'to' => substr($other->end_time, 0, 5),
        ]))->all();
    }
}
