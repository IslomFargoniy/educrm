<?php

namespace App\Services;

use App\Enums\LessonStatus;
use App\Models\Group;
use App\Models\Holiday;
use App\Models\Lesson;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

/**
 * Creates planned lessons from the group schedule and keeps them in sync.
 */
class LessonScheduler
{
    /**
     * Create missing planned lessons between the dates (holidays skipped).
     */
    public function generate(Group $group, CarbonInterface $from, CarbonInterface $to): int
    {
        if (($group->status ?? 'active') !== 'active') {
            return 0;
        }

        $from = $from->copy()->max($group->start_date);

        if ($group->end_date !== null) {
            $to = $to->copy()->min($group->end_date);
        }

        if ($from->gt($to)) {
            return 0;
        }

        $existing = $group->lessons()->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')->map(fn ($date) => Carbon::parse($date)->toDateString())->all();
        $holidays = Holiday::query()->forBranch($group->branch_id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')->map(fn ($date) => Carbon::parse($date)->toDateString())->all();
        $days = array_map('intval', $group->days);
        $created = 0;

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $day = $date->toDateString();

            if (! in_array($date->dayOfWeekIso, $days, true) || in_array($day, $existing, true) || in_array($day, $holidays, true)) {
                continue;
            }

            $group->lessons()->create([
                'date' => $day,
                'start_time' => $group->start_time,
                'end_time' => $group->end_time,
                'room_id' => $group->room_id,
                'teacher_id' => $group->teacher_id,
                'status' => LessonStatus::Planned,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Generate lessons for the current and next month.
     */
    public function generateUpcoming(Group $group): int
    {
        return $this->generate($group, today(), today()->addMonthNoOverflow()->endOfMonth());
    }

    /**
     * Rebuild future planned lessons after the schedule (days, time, room, teacher) changed.
     */
    public function rebuildFuture(Group $group): void
    {
        $group->lessons()
            ->where('status', LessonStatus::Planned)
            ->whereDate('date', '>=', today())
            ->where('is_makeup', false)
            ->whereDoesntHave('attendances')
            ->delete();

        $this->generateUpcoming($group);
    }

    /**
     * Cancel planned lessons that fall on a new holiday.
     */
    public function applyHoliday(Holiday $holiday): int
    {
        return Lesson::query()
            ->whereDate('date', $holiday->date)
            ->where('status', LessonStatus::Planned)
            ->when($holiday->branch_id, fn ($q) => $q->whereHas('group', fn ($g) => $g->where('branch_id', $holiday->branch_id)))
            ->update(['status' => LessonStatus::Cancelled, 'cancel_reason' => $holiday->name]);
    }
}
