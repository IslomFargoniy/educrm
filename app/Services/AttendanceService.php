<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LessonStatus;
use App\Events\StudentAbsent;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves attendance and bills lessons accordingly.
 */
class AttendanceService
{
    public function __construct(private BillingService $billing) {}

    /**
     * Students that belong to the lesson list (joined, not left, not frozen).
     *
     * @return Collection<int, Enrollment>
     */
    public function rosterFor(Lesson $lesson): Collection
    {
        $marked = $lesson->attendances()->pluck('enrollment_id')->all();

        return $lesson->group->enrollments()
            ->with('student', 'freezes')
            ->get()
            ->filter(fn (Enrollment $enrollment) => in_array($enrollment->id, $marked, true)
                || ($enrollment->status->isStudying() && $enrollment->attendsOn($lesson->date)))
            ->sortBy('student.full_name')
            ->values();
    }

    /**
     * Whether the user may mark or change attendance of the lesson.
     */
    public function canMark(User $user, Lesson $lesson): bool
    {
        if ($lesson->status === LessonStatus::Cancelled || $lesson->date->isFuture()) {
            return false;
        }

        if ($lesson->date->isToday() && $user->can('attendance.mark') && ($lesson->teacher_id === $user->id || $user->can('lessons.manage'))) {
            return true;
        }

        return $user->can('attendance.edit_past') && $user->canAccessBranch($lesson->group->branch_id);
    }

    /**
     * @param  array<int|string, string>  $statuses  enrollment id => attendance status
     */
    public function save(Lesson $lesson, array $statuses, User $user): void
    {
        if (! $this->canMark($user, $lesson)) {
            throw ValidationException::withMessages(['attendance' => __('You cannot change attendance of this lesson.')]);
        }

        $roster = $this->rosterFor($lesson)->keyBy('id');
        $absent = [];

        DB::transaction(function () use ($lesson, $statuses, $user, $roster, &$absent) {
            foreach ($statuses as $enrollmentId => $value) {
                $enrollment = $roster->get((int) $enrollmentId);
                $status = AttendanceStatus::tryFrom($value);

                if ($enrollment === null || $status === null) {
                    continue;
                }

                $attendance = Attendance::query()->firstOrNew(['lesson_id' => $lesson->id, 'enrollment_id' => $enrollment->id]);
                $oldStatus = $attendance->exists ? $attendance->status : null;

                if ($oldStatus === $status) {
                    continue;
                }

                $attendance->fill(['status' => $status, 'marked_by' => $user->id])->save();
                $attendance->audits()->create([
                    'old_status' => $oldStatus?->value,
                    'new_status' => $status->value,
                    'changed_by' => $user->id,
                    'changed_at' => now(),
                ]);

                $attendance->setRelation('lesson', $lesson);
                $attendance->setRelation('enrollment', $enrollment);
                $this->billing->syncAttendance($attendance);

                if ($status !== AttendanceStatus::Present && $oldStatus === null) {
                    $absent[] = $attendance;
                }
            }

            $lesson->update(['status' => LessonStatus::Held]);
        });

        foreach ($absent as $attendance) {
            StudentAbsent::dispatch($attendance);
        }
    }
}
