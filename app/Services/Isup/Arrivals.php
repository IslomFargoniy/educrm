<?php

namespace App\Services\Isup;

use App\Models\Lesson;
use App\Models\StudentVisit;
use Carbon\CarbonImmutable;

/**
 * When students came in for a lesson, from the face terminals (used to pre-mark attendance).
 */
class Arrivals
{
    /**
     * First entrance pass of each student between "an hour before the start" and the end of the lesson.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, string> student id => "H:i"
     */
    public function forLesson(Lesson $lesson, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $date = $lesson->date->toDateString();
        $from = CarbonImmutable::parse($date.' '.$lesson->start_time)->subMinutes((int) config('isup.arrival_window_minutes'));
        $to = CarbonImmutable::parse($date.' '.$lesson->end_time);

        return StudentVisit::query()
            ->whereIn('student_id', $studentIds)
            ->where('direction', 'in')
            ->whereBetween('scanned_at', [$from, $to])
            ->orderBy('scanned_at')
            ->get(['student_id', 'scanned_at'])
            ->unique('student_id')
            ->mapWithKeys(fn (StudentVisit $visit) => [(int) $visit->student_id => $visit->scanned_at->format('H:i')])
            ->all();
    }
}
