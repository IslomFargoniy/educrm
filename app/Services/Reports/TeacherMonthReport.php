<?php

namespace App\Services\Reports;

use App\Enums\AttendanceStatus;
use App\Enums\ChargeStatus;
use App\Enums\LessonStatus;
use App\Models\Attendance;
use App\Models\ChargeCoverage;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonCharge;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A teacher's month by group and student: lessons attended out of the monthly plan, lessons paid and unpaid,
 * and the teacher's share received or still waiting for the student's payment.
 */
class TeacherMonthReport
{
    /**
     * @param  list<int>  $branchIds
     * @return list<array{id: int, name: string, lessons_per_month: int, lessons_held: int, percent: int, students: array<int, array<string, mixed>>, totals: array<string, int>}>
     */
    public function build(User $teacher, string $month, array $branchIds): array
    {
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();

        $lessons = Lesson::query()
            ->where('teacher_id', $teacher->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', LessonStatus::Cancelled)
            ->whereHas('group', fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->get(['id', 'group_id', 'date', 'status']);

        $groupIds = $lessons->pluck('group_id')
            ->merge(Group::query()->where('teacher_id', $teacher->id)->where('status', 'active')->whereIn('branch_id', $branchIds)->pluck('id'))
            ->unique()->values();

        $attendances = Attendance::query()
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->get(['enrollment_id', 'lesson_id', 'status'])
            ->groupBy('enrollment_id');

        $charges = LessonCharge::query()
            ->where('teacher_id', $teacher->id)
            ->where('month', $month)
            ->whereIn('status', [ChargeStatus::Active, ChargeStatus::PendingTrial])
            ->get(['id', 'enrollment_id', 'amount', 'covered_amount', 'written_off_amount', 'teacher_percent', 'status'])
            ->groupBy('enrollment_id');

        $received = ChargeCoverage::query()
            ->whereIn('lesson_charge_id', $charges->flatten()->pluck('id'))
            ->where('teacher_id', $teacher->id)
            ->whereNull('reversed_at')
            ->selectRaw('lesson_charge_id, SUM(teacher_amount) as total')
            ->groupBy('lesson_charge_id')
            ->pluck('total', 'lesson_charge_id');

        $result = [];

        foreach (Group::query()->whereKey($groupIds)->orderBy('name')->get() as $group) {
            $price = $group->priceFor($start);
            $groupLessons = $lessons->where('group_id', $group->id);

            $enrollments = Enrollment::query()
                ->where('group_id', $group->id)
                ->with('student:id,full_name,phone')
                ->get()
                ->filter(fn (Enrollment $e) => $attendances->has($e->id) || $charges->has($e->id) || $e->status->isStudying());

            $students = $enrollments->map(function (Enrollment $e) use ($attendances, $charges, $received) {
                $marks = $attendances->get($e->id, collect());
                $own = $charges->get($e->id, collect())->where('status', ChargeStatus::Active);
                $paid = $own->filter(fn (LessonCharge $c) => $c->covered_amount + $c->written_off_amount >= $c->amount);
                $debt = $own->sum(fn (LessonCharge $c) => max(0, $c->amount - $c->covered_amount - $c->written_off_amount));

                return [
                    'enrollment_id' => $e->id,
                    'student_id' => $e->student_id,
                    'student' => $e->student->full_name,
                    'phone' => $e->student->phone,
                    'status' => $e->status->value,
                    'present' => $marks->where('status', AttendanceStatus::Present)->count(),
                    'absent' => $marks->where('status', AttendanceStatus::Absent)->count(),
                    'excused' => $marks->where('status', AttendanceStatus::Excused)->count(),
                    'billed' => $own->count(),
                    'paid' => $paid->count(),
                    'unpaid' => $own->count() - $paid->count(),
                    'charged' => (int) $own->sum('amount'),
                    'covered' => (int) $own->sum(fn (LessonCharge $c) => $c->covered_amount + $c->written_off_amount),
                    'debt' => (int) $debt,
                    'share_received' => (int) $own->sum(fn (LessonCharge $c) => (int) ($received[$c->id] ?? 0)),
                    'share_waiting' => (int) $own->sum(fn (LessonCharge $c) => intdiv(max(0, $c->amount - $c->covered_amount - $c->written_off_amount) * $c->teacher_percent, 100)),
                    'balance' => $e->balance,
                ];
            })->sortBy('student')->values()->all();

            $sum = fn (string $key) => (int) array_sum(array_column($students, $key));

            $result[] = [
                'id' => $group->id,
                'name' => $group->name,
                'lessons_per_month' => (int) ($price->lessons_per_month ?? 0),
                'lessons_held' => $groupLessons->where('status', LessonStatus::Held)->count(),
                'percent' => (int) $group->teacher_percent,
                'students' => $students,
                'totals' => collect(['present', 'absent', 'excused', 'billed', 'paid', 'unpaid', 'charged', 'covered', 'debt', 'share_received', 'share_waiting'])
                    ->mapWithKeys(fn (string $key) => [$key => $sum($key)])->all(),
            ];
        }

        return $result;
    }
}
