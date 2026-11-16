<?php

namespace App\Http\Controllers;

use App\Enums\ChargeStatus;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lead;
use App\Models\Student;
use App\Models\User;
use App\Services\BillingService;
use App\Services\CoverageService;
use App\Services\EnrollmentService;
use App\Support\CurrentBranch;
use App\Support\Phone;
use App\Support\Presenters\History;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $filters = $request->only(['q', 'group_id', 'status']);

        $students = Student::query()
            ->inBranches($branchIds)
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', '%'.preg_replace('/\D+/', '', $term).'%')))
            ->when($filters['group_id'] ?? null, fn (Builder $q, $groupId) => $q->whereHas('enrollments', fn (Builder $e) => $e->where('group_id', $groupId)->whereIn('status', ['active', 'trial'])))
            ->when(($filters['status'] ?? null) === 'debtor', fn (Builder $q) => $q->whereHas('enrollments', fn (Builder $e) => $e->where('balance', '<', 0)))
            ->when(($filters['status'] ?? null) === 'studying', fn (Builder $q) => $q->whereHas('enrollments', fn (Builder $e) => $e->whereIn('status', ['active', 'trial'])))
            ->when(($filters['status'] ?? null) === 'left', fn (Builder $q) => $q->whereDoesntHave('enrollments', fn (Builder $e) => $e->whereIn('status', ['active', 'trial'])))
            ->with(['enrollments' => fn ($q) => $q->whereHas('group', fn ($g) => $g->whereIn('branch_id', $branchIds))->with('group:id,name')])
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
            ]);

        return Inertia::render('students/index', [
            'students' => $students,
            'filters' => $filters,
            'groups' => Group::query()->whereIn('branch_id', $branchIds)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, EnrollmentService $enrollments): RedirectResponse
    {
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
            'guardian_phone' => Phone::normalize($request->input('guardian_phone')),
        ]);
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^\+998\d{9}$/'],
            'birth_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'regex:/^\+998\d{9}$/'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'joined_at' => ['nullable', 'date'],
            'trial_lessons' => ['nullable', 'integer', 'min:0', 'max:30'],
            'lead_id' => ['nullable', 'exists:leads,id'],
        ]);

        $guardianPassword = null;
        $student = DB::transaction(function () use ($validated, $enrollments, &$guardianPassword) {
            $student = Student::query()->create([
                ...collect($validated)->only(['full_name', 'phone', 'birth_date', 'note', 'lead_id'])->all(),
                'branch_id' => app(CurrentBranch::class)->forCreate(),
            ]);

            if (! empty($validated['guardian_phone'])) {
                $guardianPassword = $this->attachGuardian($student, $validated['guardian_name'] ?? $validated['full_name'], $validated['guardian_phone'], 'parent');
            }

            if (! empty($validated['group_id'])) {
                $group = Group::query()->findOrFail($validated['group_id']);
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
            ->with('group.teacher:id,name', 'group.branch:id,name', 'freezes', 'transferredFrom.group:id,name')
            ->orderByRaw("CASE WHEN status IN ('active','trial') THEN 0 ELSE 1 END")
            ->latest('joined_at')
            ->get()
            ->map(function (Enrollment $enrollment) use ($coverage, $billing, $month, $from, $to) {
                $price = $enrollment->group->priceFor(today());
                $lessonsPerMonth = $price?->lessons_per_month ?? 0;

                return [
                    'id' => $enrollment->id,
                    'group' => ['id' => $enrollment->group->id, 'name' => $enrollment->group->name, 'branch' => $enrollment->group->branch->name, 'branch_id' => $enrollment->group->branch_id, 'teacher' => $enrollment->group->teacher?->name],
                    'status' => $enrollment->status->value,
                    'joined_at' => $enrollment->joined_at->toDateString(),
                    'left_at' => $enrollment->left_at?->toDateString(),
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
                ...$student->only(['id', 'full_name', 'note', 'status']),
                'phone' => $request->user()->can('students.view_phone') ? $student->phone : null,
                'birth_date' => $student->birth_date?->toDateString(),
                'guardians' => $student->guardians->map(fn (User $g) => [
                    'id' => $g->id, 'name' => $g->name, 'relation' => $g->pivot->relation,
                    'phone' => $request->user()->can('students.view_phone') ? $g->phone : null,
                    'telegram' => $g->telegram_chat_id !== null,
                ]),
            ],
            'enrollments' => $enrollments,
            'payments' => $payments,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'groups' => Group::query()->whereIn('branch_id', $branchIds)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'branch_id']),
            'cashboxes' => Cashbox::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'branch_id', 'method']),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        abort_unless(Student::query()->whereKey($student->id)->inBranches($request->user()->accessibleBranchIds())->exists(), 403);
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^\+998\d{9}$/'],
            'birth_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
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
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^\+998\d{9}$/'],
            'relation' => ['required', Rule::in(['father', 'mother', 'parent', 'other'])],
        ]);

        $password = $this->attachGuardian($student, $validated['name'], $validated['phone'], $validated['relation']);
        $this->toast($password === null
            ? __('Parent added.')
            : __('Parent added. Login: phone number, password: :password', ['password' => $password]));

        return back();
    }

    public function destroyGuardian(Student $student, User $guardian): RedirectResponse
    {
        $student->guardians()->detach($guardian->id);

        return back();
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
