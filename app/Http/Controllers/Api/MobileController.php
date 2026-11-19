<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LedgerAccount;
use App\Http\Controllers\Controller;
use App\Models\Cashbox;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lead;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\CoverageService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use App\Services\Isup\Arrivals;
use App\Support\Phone;
use App\Support\Presenters\History;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * JSON endpoints for the Android app (admin / manager / teacher).
 * Business rules are the same services the web app uses.
 */
class MobileController extends Controller
{
    /**
     * Lessons of a day: own lessons for teachers, branch lessons for staff.
     */
    public function lessons(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        $onlyOwn = ! $user->can('lessons.manage') && ! $user->can('attendance.edit_past');

        $lessons = Lesson::query()
            ->whereDate('date', $date)
            ->whereHas('group', fn ($q) => $q->whereIn('branch_id', $this->scope($request)))
            ->when($onlyOwn, fn ($q) => $q->where('teacher_id', $user->id))
            ->with('group:id,name,days,start_time,end_time', 'teacher:id,name', 'room:id,name')
            ->withCount('attendances')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Lesson $l) => [
                'id' => $l->id,
                'group' => $l->group->name,
                'date' => $l->date->toDateString(),
                'start_time' => substr($l->start_time, 0, 5),
                'end_time' => substr($l->end_time, 0, 5),
                'teacher' => $l->teacher?->name,
                'room' => $l->room?->name,
                'status' => $l->status->value,
                'is_substitute' => $l->is_substitute,
                'marked' => $l->attendances_count,
                'group_days' => array_values(array_map('intval', $l->group->days ?? [])),
                'group_start_time' => $l->group->start_time ? substr($l->group->start_time, 0, 5) : null,
                'group_end_time' => $l->group->end_time ? substr($l->group->end_time, 0, 5) : null,
            ]);

        return response()->json(['date' => $date->toDateString(), 'lessons' => $lessons]);
    }

    public function lesson(Request $request, Lesson $lesson, AttendanceService $attendance, Arrivals $arrivals): JsonResponse
    {
        $user = $request->user();
        $this->authorizeBranch($lesson->group->branch_id);
        abort_unless($lesson->teacher_id === $user->id || $user->can('groups.view'), 403);
        $statuses = $lesson->attendances()->pluck('status', 'enrollment_id');
        $roster = $attendance->rosterFor($lesson);
        $arrived = $arrivals->forLesson($lesson, $roster->pluck('student_id')->map(fn ($id) => (int) $id)->all());

        return response()->json([
            'lesson' => ['id' => $lesson->id, 'group' => $lesson->group->name, 'date' => $lesson->date->toDateString(), 'status' => $lesson->status->value],
            'can_mark' => $attendance->canMark($user, $lesson),
            'students' => $roster->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id,
                'full_name' => $e->student->full_name,
                'status' => $statuses[$e->id] ?? null,
                'arrived_at' => $arrived[$e->student_id] ?? null,
                'is_trial' => $e->status->value === 'trial',
                'is_debtor' => $user->can('students.view_debt') ? $e->balance < 0 : null,
            ])->values(),
        ]);
    }

    public function attendance(Request $request, Lesson $lesson, AttendanceService $attendance): JsonResponse
    {
        $this->authorizeBranch($lesson->group->branch_id);
        $validated = $request->validate([
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', 'in:present,absent,excused'],
        ]);
        $attendance->save($lesson, $validated['statuses'], $request->user());

        return response()->json(['ok' => true]);
    }

    public function students(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('students.view'), 403);
        $term = (string) $request->query('q', '');

        $students = Student::query()
            ->inBranches($this->scope($request))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('full_name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->with(['enrollments' => fn ($q) => $q->whereIn('status', ['active', 'trial'])->with('group:id,name,branch_id')])
            ->orderBy('full_name')
            ->limit(50)
            ->get()
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'full_name' => $s->full_name,
                'phone' => $request->user()->can('students.view_phone') ? $s->phone : null,
                'groups' => $s->enrollments->map(fn (Enrollment $e) => ['enrollment_id' => $e->id, 'group' => $e->group->name, 'branch_id' => $e->group->branch_id, 'balance' => $e->balance]),
            ]);

        return response()->json(['students' => $students]);
    }

    public function student(Request $request, Student $student, CoverageService $coverage): JsonResponse
    {
        abort_unless($request->user()->can('students.view'), 403);
        abort_unless(Student::query()->whereKey($student->id)->inBranches($request->user()->accessibleBranchIds())->exists(), 403);

        return response()->json([
            'student' => ['id' => $student->id, 'full_name' => $student->full_name],
            'enrollments' => $student->enrollments()->with('group:id,name,branch_id')->get()->map(fn (Enrollment $e) => [
                'id' => $e->id,
                'group' => $e->group->name,
                'branch_id' => $e->group->branch_id,
                'status' => $e->status->value,
                'balance' => $e->balance,
                'available' => max(0, $coverage->availableFunds($e)),
                'history' => History::forEnrollment($e, now()->subMonthsNoOverflow(2)->startOfMonth(), today()),
            ]),
        ]);
    }

    public function payment(Request $request, Enrollment $enrollment, FinanceService $finance): JsonResponse
    {
        abort_unless($request->user()->can('payments.create'), 403);
        $this->authorizeBranch($enrollment->group->branch_id);
        $validated = $request->validate([
            'cashbox_id' => ['required', 'exists:cashboxes,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);
        $payment = $finance->receivePayment($enrollment, Cashbox::query()->findOrFail($request->integer('cashbox_id')), (int) $validated['amount'], $validated['note'] ?? null, null, $validated['idempotency_key'] ?? null);

        return response()->json([
            'payment' => ['id' => $payment->id, 'receipt_no' => $payment->receipt_no, 'amount' => $payment->amount],
            'balance' => $enrollment->fresh()->balance,
        ], 201);
    }

    public function cashboxes(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('cashboxes.view') || $user->can('payments.create'), 403);

        return response()->json([
            'cashboxes' => Cashbox::query()->whereIn('branch_id', $this->scope($request))->where('is_active', true)
                ->get(['id', 'name', 'method', 'branch_id', 'balance'])
                ->map(fn (Cashbox $c) => [...$c->only(['id', 'name', 'branch_id']), 'method' => $c->method->value, 'balance' => $user->can('cashboxes.view') ? $c->balance : null]),
        ]);
    }

    /**
     * Students of the signed-in teacher's groups with their balance (debtors first).
     */
    public function myStudents(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('students.view_debt'), 403);

        $rows = Enrollment::query()
            ->whereIn('status', ['trial', 'active'])
            ->whereHas('group', fn ($q) => $q->where('teacher_id', $user->id)->where('status', 'active'))
            ->with('student:id,full_name,phone', 'group:id,name')
            ->get()
            ->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id,
                'student' => $e->student->full_name,
                'phone' => $user->can('students.view_phone') ? $e->student->phone : null,
                'group' => $e->group->name,
                'status' => $e->status->value,
                'balance' => $e->balance,
            ])
            ->sortBy([['balance', 'asc'], ['student', 'asc']])
            ->values();

        return response()->json([
            'debt' => (int) -$rows->where('balance', '<', 0)->sum('balance'),
            'debtors' => $rows->where('balance', '<', 0)->count(),
            'students' => $rows,
        ]);
    }

    /**
     * The signed-in teacher's balance, history and lessons waiting for payment.
     */
    public function myBalance(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'balance' => $user->balance,
            'history' => History::forAccount(LedgerAccount::Teacher, $user->id, now()->startOfMonth(), today()),
            'pending' => History::pendingForTeacher($user),
        ]);
    }

    /**
     * @return list<int>
     */
    /**
     * The app language, also used for the user's notifications.
     */
    public function updateLocale(Request $request): JsonResponse
    {
        $validated = $request->validate(['locale' => ['required', Rule::in(['uz', 'ru', 'en'])]]);
        $request->user()->update(['locale' => $validated['locale']]);

        return response()->json(['ok' => true]);
    }

    /**
     * Choices for the add student / add lead forms.
     */
    public function formOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('students.create') || $request->user()->can('leads.manage'), 403);

        return response()->json([
            'groups' => Group::query()->whereIn('branch_id', $this->scope($request))->where('status', 'active')
                ->orderBy('name')->get(['id', 'name', 'branch_id']),
            'courses' => Course::options(),
            'sources' => array_column(LeadSource::cases(), 'value'),
        ]);
    }

    /**
     * Add a student (first name and last name separately), optionally straight into a group.
     */
    public function storeStudent(Request $request, EnrollmentService $enrollments): JsonResponse
    {
        abort_unless($request->user()->can('students.create'), 403);
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'regex:/^\+998\d{9}$/'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'branch_id' => ['nullable', 'integer'],
        ]);
        $group = isset($validated['group_id']) ? Group::query()->findOrFail((int) $validated['group_id']) : null;
        $branchId = $group->branch_id ?? $this->scope($request)[0] ?? null;
        abort_if($branchId === null, 422);
        $this->authorizeBranch($branchId);

        $student = DB::transaction(function () use ($validated, $group, $branchId, $enrollments) {
            $student = Student::query()->create([
                ...Arr::only($validated, ['first_name', 'last_name', 'phone']),
                'branch_id' => $branchId,
            ]);

            if ($group !== null) {
                $enrollments->enroll($student, $group, today());
            }

            return $student;
        });

        return response()->json(['student' => ['id' => $student->id, 'full_name' => $student->full_name]], 201);
    }

    public function storeLead(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('leads.manage'), 403);
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^\+998\d{9}$/'],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'course_id' => ['nullable', 'exists:courses,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $branchId = $this->scope($request)[0] ?? null;
        abort_if($branchId === null, 422);

        $lead = Lead::query()->create([
            ...$validated,
            'branch_id' => $branchId,
            'status' => LeadStatus::New,
            'assigned_to' => $request->user()->id,
            'next_contact_at' => today(),
        ]);

        return response()->json(['lead' => ['id' => $lead->id, 'full_name' => $lead->full_name]], 201);
    }

    /**
     * @return list<int>
     */
    private function scope(Request $request): array
    {
        $ids = $request->user()->accessibleBranchIds();
        $branchId = $request->integer('branch_id');

        return $branchId && in_array($branchId, $ids, true) ? [$branchId] : $ids;
    }
}
