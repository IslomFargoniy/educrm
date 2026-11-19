<?php

namespace App\Services\Reports;

use App\Enums\EnrollmentStatus;
use App\Enums\LessonStatus;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * How well a teacher does in a month: new students kept, students who graduated or left, lessons held, attendance.
 */
class TeacherKpi
{
    /**
     * @param  list<int>  $branchIds
     * @return array<string, array<string, int>>
     */
    public function build(User $teacher, string $month, array $branchIds): array
    {
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $teacherGroups = fn ($q) => $q->where('teacher_id', $teacher->id)->whereIn('branch_id', $branchIds);

        // New students of the month (joined the teacher's groups, transfers not counted) and where they are now.
        $joined = Enrollment::query()
            ->whereHas('group', $teacherGroups)
            ->whereNull('transferred_from_id')
            ->whereBetween('joined_at', [$start->toDateString(), $end->toDateString()])
            ->withExists(['payments as has_paid' => fn ($q) => $q->whereNull('reversed_at')])
            ->get(['id', 'status', 'leave_reason']);
        $stayed = $joined->filter(fn (Enrollment $e) => $e->status->isStudying())->count();

        // Students who left the teacher's groups during the month, by reason; and who studies there now.
        $left = Enrollment::query()
            ->whereHas('group', $teacherGroups)
            ->whereIn('status', [EnrollmentStatus::Left, EnrollmentStatus::Transferred])
            ->whereBetween('left_at', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'status', 'leave_reason']);

        $lessons = Lesson::query()
            ->where('teacher_id', $teacher->id)
            ->whereBetween('date', [$start->toDateString(), min($end, CarbonImmutable::today())->toDateString()])
            ->whereHas('group', fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->get(['id', 'status']);

        $marks = Attendance::query()
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return [
            'retention' => [
                'joined' => $joined->count(),
                'stayed' => $stayed,
                'paid' => $joined->where('has_paid', true)->count(),
                'left' => $joined->count() - $stayed,
            ],
            'outcomes' => [
                'studying' => Enrollment::query()->whereHas('group', $teacherGroups)->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Trial])->count(),
                'graduated' => $left->where('leave_reason', 'graduated')->count(),
                'dropped' => $left->filter(fn (Enrollment $e) => $e->status === EnrollmentStatus::Left && in_array($e->leave_reason, [null, 'dropped'], true))->count(),
                'expelled' => $left->where('leave_reason', 'expelled')->count(),
                'transferred' => $left->where('status', EnrollmentStatus::Transferred)->count(),
            ],
            'lessons' => [
                'held' => $lessons->where('status', LessonStatus::Held)->count(),
                'not_held' => $lessons->where('status', LessonStatus::Planned)->count(),
                'cancelled' => $lessons->where('status', LessonStatus::Cancelled)->count(),
            ],
            'attendance' => [
                'present' => (int) ($marks['present'] ?? 0),
                'absent' => (int) ($marks['absent'] ?? 0),
                'excused' => (int) ($marks['excused'] ?? 0),
            ],
        ];
    }
}
