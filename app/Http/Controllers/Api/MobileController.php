<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LedgerAccount;
use App\Enums\TransactionType;
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
use App\Services\Isup\FacePhoto;
use App\Services\Reports\StaffSalary;
use App\Support\Phone;
use App\Support\Presenters\History;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
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
            ->get();

        // Students of the day's groups on that date; frozen ones are counted apart.
        $enrollments = Enrollment::query()
            ->whereIn('group_id', $lessons->pluck('group_id')->unique())
            ->whereIn('status', ['trial', 'active'])
            ->with('freezes')
            ->get()
            ->filter(fn (Enrollment $e) => $e->joined_at->lte($date) && ($e->left_at === null || $e->left_at->gte($date)))
            ->groupBy('group_id');
        $frozen = fn (int $groupId) => $enrollments->get($groupId, collect())->filter(fn (Enrollment $e) => $e->isFrozenOn($date))->count();

        $lessons = $lessons
            ->map(fn (Lesson $l) => [
                'id' => $l->id,
                'group_id' => $l->group_id,
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
                'students' => $enrollments->get($l->group_id, collect())->count(),
                'frozen' => $frozen($l->group_id),
            ]);
        $unique = $enrollments->flatten(1);

        return response()->json([
            'date' => $date->toDateString(),
            'lessons' => $lessons,
            'summary' => [
                'students' => $unique->pluck('student_id')->unique()->count(),
                'frozen' => $unique->filter(fn (Enrollment $e) => $e->isFrozenOn($date))->pluck('student_id')->unique()->count(),
            ],
        ]);
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
            'is_future' => $lesson->date->isFuture(),
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

    /**
     * Face photo for the terminals, taken in the app (teachers: students of own groups).
     */
    public function storeFace(Request $request, Student $student, FacePhoto $faces): JsonResponse
    {
        abort_unless($faces->canManage($request->user(), $student), 403);
        $request->validate(['photo' => ['required', 'image', 'max:10240']]);
        $faces->save($student, $request->file('photo'));

        return response()->json(['ok' => true]);
    }

    /**
     * Group card for the app: students with phone and balance, and the attendance of recent lessons.
     */
    public function group(Request $request, Group $group, FacePhoto $faces): JsonResponse
    {
        $user = $request->user();
        $this->authorizeBranch($group->branch_id);
        abort_unless($group->teacher_id === $user->id || $user->can('groups.view'), 403);

        $lessons = $group->lessons()
            ->whereDate('date', '<=', today())
            ->orderByDesc('date')->orderByDesc('start_time')
            ->limit(30)
            ->with('attendances:id,lesson_id,enrollment_id,status')
            ->get(['id', 'group_id', 'date', 'start_time', 'status']);

        $enrollments = $group->enrollments()
            ->with('student:id,full_name,phone,branch_id,face_photo', 'freezes')
            ->get()
            ->filter(fn (Enrollment $e) => $e->status->isStudying() || $lessons->contains(fn (Lesson $l) => $l->attendances->contains('enrollment_id', $e->id)))
            ->sortBy('student.full_name')
            ->values();

        return response()->json([
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'teacher' => $group->teacher?->name,
                'days' => array_values(array_map('intval', $group->days ?? [])),
                'start_time' => $group->start_time ? substr($group->start_time, 0, 5) : null,
                'end_time' => $group->end_time ? substr($group->end_time, 0, 5) : null,
            ],
            'students' => $enrollments->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id,
                'student_id' => $e->student_id,
                'full_name' => $e->student->full_name,
                'photo' => $e->student->face_photo !== null
                    ? rtrim((string) config('app.url'), '/').URL::temporarySignedRoute('isup.face', now()->addHours(2), ['student' => $e->student_id, 'v' => md5($e->student->face_photo)], absolute: false)
                    : null,
                'can_photo' => $faces->canManage($user, $e->student),
                'phone' => $user->can('students.view_phone') ? $e->student->phone : null,
                'balance' => $user->can('students.view_debt') || $user->can('students.view_payments') ? $e->balance : null,
                'status' => $e->status->value,
                'frozen' => $e->isFrozenOn(today()),
            ])->values(),
            'lessons' => $lessons->map(fn (Lesson $l) => [
                'id' => $l->id,
                'date' => $l->date->toDateString(),
                'start_time' => substr($l->start_time, 0, 5),
                'status' => $l->status->value,
                'marks' => (object) $l->attendances->mapWithKeys(fn ($a) => [(string) $a->enrollment_id => $a->status->value])->all(),
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

        $page = Student::query()
            ->inBranches($this->scope($request))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('full_name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->with(['enrollments' => fn ($q) => $q->whereIn('status', ['active', 'trial'])->with('group:id,name,branch_id')])
            ->orderBy('full_name')
            ->paginate(30, ['*'], 'page', max(1, $request->integer('page', 1)));
        $students = collect($page->items())
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'full_name' => $s->full_name,
                'phone' => $request->user()->can('students.view_phone') ? $s->phone : null,
                'groups' => $s->enrollments->map(fn (Enrollment $e) => ['enrollment_id' => $e->id, 'group' => $e->group->name, 'branch_id' => $e->group->branch_id, 'balance' => $e->balance]),
            ]);

        return response()->json(['students' => $students->values(), 'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null, 'total' => $page->total()]);
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
     * The signed-in staff member's salary for a month: fixed + KPI for first payments, paid and left.
     */
    public function mySalary(Request $request, StaffSalary $salaries): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? (string) $request->query('month') : now()->format('Y-m');
        $row = $salaries->forMonth($month, $user->accessibleBranchIds(), $user->id)[0] ?? null;
        abort_if($row === null, 404);

        return response()->json(['month' => $month, ...$row]);
    }

    /**
     * Balance cards of the signed-in teacher for this month, worked out on the server:
     * month start + income for this month's lessons + earlier debts paid now − paid out = can take.
     */
    public function myBalanceSummary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $month = $this->teacherMonth($user);
        $paidOut = (int) collect($month['payout'])->sum('expense');
        $incomeThisMonth = (int) collect($month['income'])->sum(fn (array $r) => $r['income'] - $r['expense']);

        return response()->json([
            'balance' => $user->balance,
            'opening' => $month['history']['opening'],
            'income' => $incomeThisMonth,
            'earlier_debts' => $month['history']['income'] - ($month['history']['expense'] - $paidOut) - $incomeThisMonth,
            'paid_out' => $paidOut,
            'closing' => $month['history']['closing'],
            'pending_total' => History::pendingForTeacher($user)['total'],
        ]);
    }

    /**
     * One page of the teacher's month history: all rows, income rows or salary paid out.
     */
    public function myHistory(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $month = $this->teacherMonth($user);
        $rows = match ($request->query('kind')) {
            'income' => $month['income'],
            'payout' => $month['payout'],
            default => $month['history']['rows'],
        };

        return response()->json($this->slice($rows, $request->integer('page', 1)));
    }

    /**
     * This month's income grouped by student (largest first), one page; each with its lessons.
     */
    public function myIncomeByStudent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $items = [];

        foreach ($this->teacherMonth($user)['income'] as $row) {
            $group = (string) ($row['meta']['group'] ?? '');
            $lessonDate = $row['meta']['lesson_date'] ?? null;
            $children = $row['children'] ?? [];
            $parts = $children === []
                ? [['student' => (string) ($row['meta']['student'] ?? ''), 'amount' => $row['income'] - $row['expense']]]
                : array_map(fn (array $c) => ['student' => (string) $c['student'], 'amount' => (int) $c['share']], $children);

            foreach ($parts as $part) {
                $items[] = ['student' => $part['student'], 'group' => $group, 'lesson_date' => $lessonDate, 'date' => $row['date'], 'amount' => $part['amount'], 'type' => $row['type']];
            }
        }

        $rows = collect($items)->groupBy(fn (array $i) => $i['student'].'|'.$i['group'])
            ->map(fn ($list) => [
                'student' => $list->first()['student'],
                'group' => $list->first()['group'],
                'total' => (int) $list->sum('amount'),
                'items' => $list->sortBy(fn (array $i) => $i['lesson_date'] ?? $i['date'])->values()->all(),
            ])
            ->sortByDesc('total')->values()->all();

        return response()->json($this->slice($rows, $request->integer('page', 1)));
    }

    /**
     * Lessons waiting for the students' payment, grouped by student (largest first), one page.
     */
    public function myPending(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $pending = History::pendingForTeacher($user);
        $rows = collect($pending['items'])->groupBy(fn (array $i) => $i['student'].'|'.$i['group'])
            ->map(fn ($list) => [
                'student' => $list->first()['student'],
                'group' => $list->first()['group'],
                'total' => (int) $list->sum('amount'),
                'items' => $list->values()->all(),
            ])
            ->sortByDesc('total')->values()->all();

        return response()->json(['sum' => $pending['total'], ...$this->slice($rows, $request->integer('page', 1))]);
    }

    /**
     * The teacher's history of the current month, split into income rows (this month's lessons) and salary paid out.
     *
     * @return array{history: array{opening: int, income: int, expense: int, closing: int, rows: list<array<string, mixed>>}, income: list<array<string, mixed>>, payout: list<array<string, mixed>>}
     */
    private function teacherMonth(User $user): array
    {
        $history = History::forAccount(LedgerAccount::Teacher, $user->id, now()->startOfMonth(), today());
        $thisMonth = now()->format('Y-m');
        $isPayout = fn (array $r) => $r['type'] === TransactionType::TeacherPayout->value;

        return [
            'history' => $history,
            'income' => array_values(array_filter($history['rows'], fn (array $r) => ! $isPayout($r)
                && ($r['income'] > 0 || $r['expense'] > 0)
                && str_starts_with((string) ($r['meta']['lesson_date'] ?? $r['date']), $thisMonth))),
            'payout' => array_values(array_filter($history['rows'], fn (array $r) => $isPayout($r) && $r['expense'] > 0)),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>, next_page: int|null, total: int}
     */
    private function slice(array $rows, int $page, int $perPage = 30): array
    {
        $page = max(1, $page);

        return [
            'rows' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'next_page' => count($rows) > $page * $perPage ? $page + 1 : null,
            'total' => count($rows),
        ];
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
