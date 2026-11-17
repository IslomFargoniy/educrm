<?php

namespace App\Http\Controllers;

use App\Enums\ChargeStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonStatus;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BillingService;
use App\Services\ScheduleConflicts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    /**
     * Today's (or the chosen day's) lessons: own lessons for teachers, branch lessons for staff.
     */
    public function index(Request $request): Response
    {
        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $user = $request->user();
        $onlyOwn = ! $user->can('lessons.manage') && ! $user->can('attendance.edit_past');

        $lessons = Lesson::query()
            ->whereDate('date', $date)
            ->whereHas('group', fn ($q) => $q->whereIn('branch_id', $onlyOwn ? $user->accessibleBranchIds() : $this->branchIds()))
            ->when($onlyOwn, fn ($q) => $q->where('teacher_id', $user->id))
            ->with('group:id,name,branch_id', 'teacher:id,name', 'room:id,name')
            ->withCount('attendances')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'group' => $lesson->group->name,
                'start_time' => substr($lesson->start_time, 0, 5),
                'end_time' => substr($lesson->end_time, 0, 5),
                'teacher' => $lesson->teacher?->name,
                'room' => $lesson->room?->name,
                'status' => $lesson->status->value,
                'is_substitute' => $lesson->is_substitute,
                'is_makeup' => $lesson->is_makeup,
                'marked' => $lesson->attendances_count,
            ]);

        return Inertia::render('lessons/index', ['lessons' => $lessons, 'date' => $date->toDateString()]);
    }

    public function show(Request $request, Lesson $lesson, AttendanceService $attendance): Response
    {
        $this->authorizeLesson($request, $lesson);
        $lesson->load('group.branch', 'teacher:id,name', 'room:id,name', 'attendances.audits.user:id,name');
        $user = $request->user();
        $statuses = $lesson->attendances->keyBy('enrollment_id');

        $roster = $attendance->rosterFor($lesson)->map(function (Enrollment $enrollment) use ($statuses, $user) {
            $record = $statuses->get($enrollment->id);
            $trialMarked = $enrollment->status === EnrollmentStatus::Trial
                ? $enrollment->charges()->where('status', ChargeStatus::PendingTrial)->count() : 0;

            return [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'full_name' => $enrollment->student->full_name,
                'phone' => $user->can('students.view_phone') ? $enrollment->student->phone : null,
                'status' => $record?->status->value,
                'enrollment_status' => $enrollment->status->value,
                'trial_left' => $enrollment->status === EnrollmentStatus::Trial ? max(0, $enrollment->trial_lessons - $trialMarked) : null,
                'is_debtor' => $user->can('students.view_debt') ? $enrollment->balance < 0 : null,
                'balance' => $user->can('students.view_payments') ? $enrollment->balance : null,
                'history' => $record?->audits->sortByDesc('changed_at')->map(fn ($a) => [
                    'from' => $a->old_status, 'to' => $a->new_status, 'by' => $a->user?->name, 'at' => $a->changed_at->toIso8601String(),
                ])->values()->all(),
            ];
        });

        return Inertia::render('lessons/show', [
            'lesson' => [
                'id' => $lesson->id,
                'date' => $lesson->date->toDateString(),
                'start_time' => substr($lesson->start_time, 0, 5),
                'end_time' => substr($lesson->end_time, 0, 5),
                'status' => $lesson->status->value,
                'cancel_reason' => $lesson->cancel_reason,
                'is_substitute' => $lesson->is_substitute,
                'is_makeup' => $lesson->is_makeup,
                'teacher_id' => $lesson->teacher_id,
                'teacher' => $lesson->teacher?->name,
                'room' => $lesson->room?->name,
                'group' => ['id' => $lesson->group->id, 'name' => $lesson->group->name, 'teacher_id' => $lesson->group->teacher_id],
            ],
            'roster' => $roster,
            'canMark' => $attendance->canMark($user, $lesson),
            'canManage' => $user->can('lessons.manage'),
            'teachers' => $user->can('lessons.manage')
                ? User::role(Role::Teacher->value)->where('is_active', true)
                    ->whereHas('branches', fn ($q) => $q->where('branches.id', $lesson->group->branch_id))
                    ->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    public function attendance(Request $request, Lesson $lesson, AttendanceService $attendance): RedirectResponse
    {
        $this->authorizeLesson($request, $lesson);
        $validated = $request->validate([
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', 'in:present,absent,excused'],
        ]);
        $attendance->save($lesson, $validated['statuses'], $request->user());
        $this->toast(__('Attendance saved.'));

        return back();
    }

    /**
     * Change the teacher (substitute), cancel or restore a lesson.
     */
    public function update(Request $request, Lesson $lesson, BillingService $billing, ScheduleConflicts $conflicts): RedirectResponse
    {
        $this->authorizeBranch($lesson->group->branch_id);
        $validated = $request->validate([
            'action' => ['required', 'in:teacher,cancel,restore'],
            'teacher_id' => ['required_if:action,teacher', 'nullable', 'exists:users,id'],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $lesson, $billing) {
            match ($validated['action']) {
                'teacher' => $this->changeTeacher($lesson, (int) $validated['teacher_id'], $billing),
                'cancel' => $this->cancel($lesson, $validated['cancel_reason'] ?? null, $billing),
                'restore' => $lesson->update(['status' => LessonStatus::Planned, 'cancel_reason' => null]),
                default => abort(422),
            };
        });

        $warnings = $validated['action'] === 'teacher' ? $conflicts->forLesson($lesson->fresh()) : [];
        $this->toast($warnings === [] ? __('Saved.') : __('Saved. Warning: :warnings', ['warnings' => implode(' ', $warnings)]), $warnings === [] ? 'success' : 'warning');

        return back();
    }

    private function changeTeacher(Lesson $lesson, int $teacherId, BillingService $billing): void
    {
        $lesson->update(['teacher_id' => $teacherId, 'is_substitute' => $teacherId !== $lesson->group->teacher_id]);

        if ($lesson->status === LessonStatus::Held) {
            $billing->rebillLesson($lesson->fresh());
        }
    }

    private function cancel(Lesson $lesson, ?string $reason, BillingService $billing): void
    {
        if ($lesson->status === LessonStatus::Held) {
            $billing->reverseLesson($lesson);
            $lesson->attendances()->delete();
        }

        $lesson->update(['status' => LessonStatus::Cancelled, 'cancel_reason' => $reason]);
    }

    private function authorizeLesson(Request $request, Lesson $lesson): void
    {
        $user = $request->user();
        $this->authorizeBranch($lesson->group->branch_id);
        abort_unless($lesson->teacher_id === $user->id || $user->can('groups.view'), 403);
    }
}
