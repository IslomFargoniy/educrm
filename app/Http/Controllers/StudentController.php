<?php

namespace App\Http\Controllers;

use App\Enums\ChargeStatus;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Course;
use App\Models\Device;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lead;
use App\Models\Student;
use App\Models\StudentVisit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\CoverageService;
use App\Services\EnrollmentService;
use App\Support\CurrentBranch;
use App\Support\PersonName;
use App\Support\Phone;
use App\Support\Presenters\History;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $branchIds = $this->branchIds();
        $filters = $request->only(['q', 'group_id', 'status', 'category', 'teacher_id', 'course_id', 'call_status']);
        $category = in_array($filters['category'] ?? null, ['new', 'active', 'archive', 'debtors'], true) ? $filters['category'] : null;
        $categories = $this->categoryScopes($branchIds);

        $students = Student::query()
            ->inBranches($branchIds)
            ->when($category, fn (Builder $q, string $key) => $categories[$key]($q))
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', '%'.preg_replace('/\D+/', '', $term).'%')))
            ->when($filters['group_id'] ?? null, fn (Builder $q, $groupId) => $q->whereHas('enrollments', fn (Builder $e) => $e->where('group_id', $groupId)->whereIn('status', ['active', 'trial'])))
            ->when(($filters['status'] ?? null) === 'debtor', fn (Builder $q) => $q->whereHas('enrollments', fn (Builder $e) => $e->where('balance', '<', 0)))
            ->when(($filters['status'] ?? null) === 'studying', fn (Builder $q) => $q->whereHas('enrollments', fn (Builder $e) => $e->whereIn('status', ['active', 'trial'])))
            ->when(($filters['status'] ?? null) === 'left', fn (Builder $q) => $q->whereDoesntHave('enrollments', fn (Builder $e) => $e->whereIn('status', ['active', 'trial'])))
            ->when($filters['teacher_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('enrollments', fn (Builder $e) => $e->whereIn('status', ['active', 'trial'])->whereHas('group', fn (Builder $g) => $g->where('teacher_id', $id))))
            ->when($filters['course_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('enrollments', fn (Builder $e) => $e->whereIn('status', ['active', 'trial'])->whereHas('group', fn (Builder $g) => $g->where('course_id', $id))))
            ->when($filters['call_status'] ?? null, fn (Builder $q, string $status) => $status === 'none' ? $q->whereNull('call_status') : $q->where('call_status', $status))
            ->with(['enrollments' => fn ($q) => $q->whereHas('group', fn ($g) => $g->whereIn('branch_id', $branchIds))->with('group:id,name')])
            ->when($category === 'debtors', fn (Builder $q) => $q->orderBy(Enrollment::query()->selectRaw('COALESCE(SUM(balance), 0)')->whereColumn('enrollments.student_id', 'students.id')))
            ->orderBy('full_name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Student $student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'phone' => $request->user()->can('students.view_phone') ? $student->phone : null,
                'groups' => $student->enrollments->filter(fn (Enrollment $e) => $e->status->isStudying())
                    ->map(fn (Enrollment $e) => ['name' => $e->group->name, 'status' => $e->status->value])->values(),
                'balance' => (int) $student->enrollments->sum('balance'),
                'call_status' => $student->call_status,
                'call_status_at' => $student->call_status_at?->toIso8601String(),
                'left' => $category === 'archive'
                    ? $student->enrollments->sortByDesc('left_at')->first()?->only(['left_at', 'leave_reason'])
                    : null,
            ]);

        return Inertia::render('students/index', [
            'students' => $students,
            'filters' => $filters,
            'counts' => collect($categories)->map(fn ($scope) => $scope(Student::query()->inBranches($branchIds))->count()),
            'debtTotals' => $category === 'debtors' ? [
                'debt' => (int) -Enrollment::query()->where('balance', '<', 0)->whereHas('group', fn (Builder $g) => $g->whereIn('branch_id', $branchIds))->sum('balance'),
                'credit' => (int) Enrollment::query()->where('balance', '>', 0)->whereIn('status', ['active', 'trial'])->whereHas('group', fn (Builder $g) => $g->whereIn('branch_id', $branchIds))->sum('balance'),
            ] : null,
            'teachers' => User::role(Role::Teacher->value)->whereHas('branches', fn (Builder $b) => $b->whereIn('branches.id', $branchIds))->orderBy('name')->get(['id', 'name']),
            'courses' => Course::query()->orderBy('name')->get(['id', 'name']),
            'callStatuses' => Student::CALL_STATUSES,
            'groups' => Group::query()->whereIn('branch_id', $branchIds)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Colour tag with the result of the last call about the debt.
     */
    public function callStatus(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($request, $student);
        $validated = $request->validate(['call_status' => ['nullable', Rule::in(Student::CALL_STATUSES)]]);
        $student->update([
            'call_status' => $validated['call_status'] ?? null,
            'call_status_at' => now(),
            'call_status_by' => $request->user()->id,
        ]);

        return back();
    }

    /**
     * Parents (guardians) of the branch's students with their children and whether Telegram is linked.
     */
    public function parents(Request $request): Response
    {
        $branchIds = $this->branchIds();
        $term = trim((string) $request->input('q'));

        $parents = User::query()
            ->whereHas('children', fn (Builder $q) => $q->inBranches($branchIds))
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', '%'.preg_replace('/\D+/', '', $term).'%')
                ->orWhereHas('children', fn (Builder $c) => $c->where('full_name', 'like', "%{$term}%"))))
            ->with(['children' => fn ($q) => $q->inBranches($branchIds)->select('students.id', 'students.full_name')])
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (User $parent) => [
                'id' => $parent->id,
                'name' => $parent->name,
                'phone' => $request->user()->can('students.view_phone') ? $parent->phone : null,
                'telegram' => $parent->telegram_chat_id !== null,
                'children' => $parent->children->map(fn (Student $c) => ['id' => $c->id, 'full_name' => $c->full_name, 'relation' => data_get($c, 'pivot.relation')])->values(),
            ]);

        return Inertia::render('students/parents', ['parents' => $parents, 'filters' => ['q' => $term]]);
    }

    /**
     * Student categories of the menu:
     *  new     — studying, came to at least two lessons, nothing paid yet;
     *  active  — studying and has paid;
     *  archive — no group any more (graduated, left or removed);
     *  debtors — owe money in some group.
     *
     * @param  list<int>  $branchIds
     * @return array<string, \Closure(Builder<Student>): Builder<Student>>
     */
    private function categoryScopes(array $branchIds): array
    {
        $studying = fn (Builder $e) => $e->whereIn('status', ['active', 'trial'])->whereHas('group', fn (Builder $g) => $g->whereIn('branch_id', $branchIds));

        return [
            'new' => fn (Builder $q) => $q->whereHas('enrollments', fn (Builder $e) => $studying($e)
                ->whereHas('attendances', fn (Builder $a) => $a->where('status', 'present'), '>=', 2)
                ->whereDoesntHave('payments', fn (Builder $p) => $p->whereNull('reversed_at'))),
            'active' => fn (Builder $q) => $q->whereHas('enrollments', fn (Builder $e) => $studying($e)
                ->whereHas('payments', fn (Builder $p) => $p->whereNull('reversed_at'))),
            'archive' => fn (Builder $q) => $q->whereHas('enrollments')->whereDoesntHave('enrollments', fn (Builder $e) => $e->whereIn('status', ['active', 'trial'])),
            'debtors' => fn (Builder $q) => $q->whereHas('enrollments', fn (Builder $e) => $e->where('balance', '<', 0)->whereHas('group', fn (Builder $g) => $g->whereIn('branch_id', $branchIds))),
        ];
    }

    public function store(Request $request, EnrollmentService $enrollments): RedirectResponse
    {
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
            'guardian_phone' => Phone::normalize($request->input('guardian_phone')),
        ]);
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'regex:/^\+998\d{9}$/'],
            'birth_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'guardian_first_name' => ['nullable', 'string', 'max:100'],
            'guardian_last_name' => ['nullable', 'string', 'max:100'],
            'guardian_phone' => ['nullable', 'regex:/^\+998\d{9}$/'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'joined_at' => ['nullable', 'date'],
            'trial_lessons' => ['nullable', 'integer', 'min:0', 'max:30'],
            'lead_id' => ['nullable', 'exists:leads,id'],
        ]);

        $guardianPassword = null;
        $student = DB::transaction(function () use ($validated, $enrollments, &$guardianPassword) {
            $student = Student::query()->create([
                ...Arr::only($validated, ['first_name', 'last_name', 'phone', 'birth_date', 'note', 'lead_id']),
                'branch_id' => app(CurrentBranch::class)->forCreate(),
            ]);

            if (! empty($validated['guardian_phone'])) {
                $guardianPassword = $this->attachGuardian(
                    $student,
                    filled($validated['guardian_first_name'] ?? null)
                        ? PersonName::join($validated['guardian_first_name'], $validated['guardian_last_name'] ?? $validated['last_name'])
                        : $student->full_name,
                    $validated['guardian_phone'],
                    'parent',
                );
            }

            if (! empty($validated['group_id'])) {
                $group = Group::query()->findOrFail((int) $validated['group_id']);
                $this->authorizeBranch($group->branch_id);
                $enrollments->enroll($student, $group, Carbon::parse($validated['joined_at'] ?? today()), [
                    'trial_lessons' => (int) ($validated['trial_lessons'] ?? 0),
                ]);
            }

            if (! empty($validated['lead_id'])) {
                $isTrial = ! empty($validated['group_id']) && (int) ($validated['trial_lessons'] ?? 0) > 0;
                Lead::query()->whereKey($validated['lead_id'])->update([
                    'status' => $isTrial ? LeadStatus::Trial : LeadStatus::Converted,
                    'student_id' => $student->id,
                ]);
            }

            return $student;
        });

        $this->toast($guardianPassword === null
            ? __('Student added.')
            : __('Student added. Parent login: phone number, password: :password', ['password' => $guardianPassword]));

        return to_route('students.show', $student);
    }

    public function show(Request $request, Student $student, CoverageService $coverage, BillingService $billing): Response
    {
        $branchIds = $this->branchIds();
        abort_unless(Student::query()->whereKey($student->id)->inBranches($request->user()->accessibleBranchIds())->exists(), 403);

        $from = Carbon::parse($request->input('from', now()->subMonthsNoOverflow(2)->startOfMonth()));
        $to = Carbon::parse($request->input('to', today()));
        $month = today()->format('Y-m');

        $enrollments = $student->enrollments()
            ->whereHas('group', fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->with('group.teacher:id,name', 'group.branch:id,name', 'group.course:id,name', 'freezes', 'transferredFrom.group:id,name')
            ->orderByRaw("CASE WHEN status IN ('active','trial') THEN 0 ELSE 1 END")
            ->latest('joined_at')
            ->get()
            ->map(function (Enrollment $enrollment) use ($coverage, $billing, $month, $from, $to) {
                $price = $enrollment->group->priceFor(today());
                $lessonsPerMonth = $price->lessons_per_month ?? 0;

                return [
                    'id' => $enrollment->id,
                    'group' => ['id' => $enrollment->group->id, 'name' => $enrollment->group->name, 'branch' => $enrollment->group->branch->name, 'branch_id' => $enrollment->group->branch_id, 'teacher' => $enrollment->group->teacher?->name, 'course' => $enrollment->group->course?->name],
                    'status' => $enrollment->status->value,
                    'joined_at' => $enrollment->joined_at->toDateString(),
                    'left_at' => $enrollment->left_at?->toDateString(),
                    'leave_reason' => $enrollment->leave_reason,
                    'leave_note' => $enrollment->leave_note,
                    'balance' => $enrollment->balance,
                    'available' => max(0, $coverage->availableFunds($enrollment)),
                    'monthly_price' => $price?->monthly_price,
                    'discounted_price' => $price ? $enrollment->discountedMonthlyPrice($price->monthly_price) : null,
                    'lessons_per_month' => $lessonsPerMonth,
                    'billed_this_month' => $billing->billedLessonsInMonth($enrollment, $month),
                    'month_limit' => $lessonsPerMonth ? $billing->monthlyLimit($enrollment, $month, $lessonsPerMonth) : 0,
                    'discount_type' => $enrollment->discount_type?->value,
                    'discount_value' => $enrollment->discount_value,
                    'teacher_percent' => $enrollment->teacher_percent,
                    'group_percent' => $enrollment->group->teacher_percent,
                    'trial_lessons' => $enrollment->trial_lessons,
                    'trial_marked' => $enrollment->charges()->where('status', ChargeStatus::PendingTrial)->count(),
                    'freezes' => $enrollment->freezes->sortByDesc('from_date')->map(fn ($f) => [
                        'id' => $f->id, 'from' => $f->from_date->toDateString(), 'to' => $f->to_date->toDateString(), 'reason' => $f->reason,
                        'active' => today()->betweenIncluded($f->from_date, $f->to_date),
                    ])->values(),
                    'transferred_from' => $enrollment->transferredFrom?->group->name,
                    'history' => History::forEnrollment($enrollment, $from, $to),
                ];
            });

        $payments = $student->enrollments()->with(['payments' => fn ($q) => $q->latest('paid_at')->with('cashbox:id,name', 'receiver:id,name')], 'group:id,name')->get()
            ->flatMap(fn (Enrollment $e) => $e->payments->map(fn ($p) => [
                'id' => $p->id,
                'group' => $e->group->name,
                'amount' => $p->amount,
                'paid_at' => $p->paid_at->toIso8601String(),
                'cashbox' => $p->cashbox->name,
                'receipt_no' => $p->receipt_no,
                'received_by' => $p->receiver?->name,
                'reversed' => $p->reversed_at !== null,
                'reverse_reason' => $p->reverse_reason,
                'can_reverse' => $p->reversed_at === null && ($request->user()->can('payments.reverse')
                    || ($request->user()->can('payments.reverse_today') && $p->paid_at->isToday() && $p->received_by === $request->user()->id)),
            ]))->sortByDesc('paid_at')->values();

        return Inertia::render('students/show', [
            'student' => [
                ...$student->only(['id', 'full_name', 'first_name', 'last_name', 'note', 'status']),
                'registered_by' => $student->registered_by,
                'registered_by_name' => $student->registeredBy?->name,
                'phone' => $request->user()->can('students.view_phone') ? $student->phone : null,
                'birth_date' => $student->birth_date?->toDateString(),
                'guardians' => $student->guardians->map(fn (User $g) => [
                    'id' => $g->id, 'name' => $g->name, 'relation' => data_get($g, 'pivot.relation'),
                    'phone' => $request->user()->can('students.view_phone') ? $g->phone : null,
                    'telegram' => $g->telegram_chat_id !== null,
                ]),
            ],
            'enrollments' => $enrollments,
            'payments' => $payments,
            'face' => [
                'photo' => $student->face_photo !== null ? route('students.face.show', ['student' => $student->id, 'v' => md5($student->face_photo)]) : null,
                'devices' => $student->devices()->with('branch:id,name')->get()->map(fn (Device $device) => [
                    'id' => $device->id,
                    'name' => $device->name.' · '.$device->branch->name,
                    'status' => $device->sync->status,
                    'error' => $device->sync->error,
                    'synced_at' => $device->sync->synced_at?->toIso8601String(),
                ])->values(),
                'has_devices' => Device::query()->whereIn('branch_id', $branchIds)->exists(),
            ],
            'visits' => $student->visits()->with('device:id,name')->latest('scanned_at')->limit(50)->get()->map(fn (StudentVisit $visit) => [
                'id' => $visit->id,
                'scanned_at' => $visit->scanned_at->toIso8601String(),
                'direction' => $visit->direction,
                'device' => $visit->device?->name,
            ])->values(),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'groups' => Group::query()->whereIn('branch_id', $branchIds)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'branch_id']),
            'cashboxes' => Cashbox::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'branch_id', 'method']),
            'staff' => User::role(array_map(fn (Role $r) => $r->value, Role::staff()))->where('is_active', true)
                ->whereHas('branches', fn (Builder $b) => $b->whereIn('branches.id', $branchIds))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        abort_unless(Student::query()->whereKey($student->id)->inBranches($request->user()->accessibleBranchIds())->exists(), 403);
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'regex:/^\+998\d{9}$/'],
            'birth_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'registered_by' => ['nullable', 'exists:users,id'],
        ]);

        if (! $request->user()->can('students.view_phone')) {
            unset($validated['phone']);
        }

        $student->update($validated);
        $this->toast(__('Saved.'));

        return back();
    }

    public function storeGuardian(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudent($request, $student);
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^\+998\d{9}$/'],
            'relation' => ['required', Rule::in(['father', 'mother', 'parent', 'other'])],
        ]);

        $password = $this->attachGuardian($student, PersonName::join($validated['first_name'], $validated['last_name']), $validated['phone'], $validated['relation']);
        $this->toast($password === null
            ? __('Parent added.')
            : __('Parent added. Login: phone number, password: :password', ['password' => $password]));

        return back();
    }

    public function destroyGuardian(Request $request, Student $student, User $guardian): RedirectResponse
    {
        $this->authorizeStudent($request, $student);
        $student->guardians()->detach($guardian->id);

        return back();
    }

    /**
     * Give a parent of this student a new password and show it once. Parents sign in by phone
     * and have no other way to recover a lost (or never seen, e.g. Excel-imported) password.
     */
    public function resetGuardianPassword(Request $request, Student $student, User $guardian): RedirectResponse
    {
        $this->authorizeStudent($request, $student);
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);
        // Only a portal-only account: a staff member who is also a parent is managed under Admin → Users.
        abort_if($guardian->roles->pluck('name')->contains(fn (string $role) => $role !== Role::Parent->value && $role !== Role::Student->value), 403);

        $password = Str::password(8, symbols: false);
        $guardian->update(['password' => $password]);
        $guardian->tokens()->delete();
        $this->toast(__('New password for :name: :password', ['name' => $guardian->name, 'password' => $password]));

        return back();
    }

    private function authorizeStudent(Request $request, Student $student): void
    {
        abort_unless(Student::query()->whereKey($student->id)->inBranches($request->user()->accessibleBranchIds())->exists(), 403);
    }

    /**
     * Link a parent account (created when missing). Returns the new password, if one was created.
     */
    private function attachGuardian(Student $student, string $name, string $phone, string $relation): ?string
    {
        $guardian = User::query()->where('phone', $phone)->first();
        $password = null;

        if ($guardian === null) {
            $password = Str::password(8, symbols: false);
            $guardian = User::query()->create(['name' => $name, 'phone' => $phone, 'password' => $password]);
            $guardian->assignRole(Role::Parent->value);
        } elseif (! $guardian->hasRole(Role::Parent->value)) {
            $guardian->assignRole(Role::Parent->value);
        }

        $student->guardians()->syncWithoutDetaching([$guardian->id => ['relation' => $relation]]);

        return $password;
    }
}
