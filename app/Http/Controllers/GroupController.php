<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\User;
use App\Services\LessonScheduler;
use App\Services\ScheduleConflicts;
use App\Support\CurrentBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function __construct(private LessonScheduler $scheduler, private ScheduleConflicts $conflicts) {}

    public function index(Request $request): Response
    {
        $branchIds = $this->branchIds();
        $status = $request->input('status', 'active');

        $groups = Group::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', $status)
            ->when($request->user()->hasRole(Role::Teacher->value) && ! $request->user()->can('groups.view'), fn ($q) => $q->where('teacher_id', $request->user()->id))
            ->with('teacher:id,name', 'room:id,name', 'branch:id,name')
            ->withCount(['enrollments as students_count' => fn ($q) => $q->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Trial])])
            ->orderBy('name')
            ->get()
            ->map(fn (Group $group) => [
                ...$group->only(['id', 'name', 'subject', 'days', 'teacher_percent', 'status']),
                'start_time' => substr($group->start_time, 0, 5),
                'end_time' => substr($group->end_time, 0, 5),
                'teacher' => $group->teacher?->name,
                'room' => $group->room?->name,
                'branch' => $group->branch->name,
                'students_count' => $group->students_count,
                'price' => $group->priceFor(today())?->only(['monthly_price', 'lessons_per_month']),
            ]);

        return Inertia::render('groups/index', [
            'groups' => $groups,
            'status' => $status,
            ...$this->formOptions($branchIds),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateGroup($request, true);
        $this->authorizeBranch((int) $validated['branch_id']);
        $this->assertDivisible((int) $validated['monthly_price'], (int) $validated['lessons_per_month']);
        $warnings = $this->conflicts->forGroup($this->scheduleOf($validated));

        $group = DB::transaction(function () use ($validated, $request) {
            $group = Group::query()->create(collect($validated)->except(['monthly_price', 'lessons_per_month'])->all());
            $group->prices()->create([
                'monthly_price' => $validated['monthly_price'],
                'lessons_per_month' => $validated['lessons_per_month'],
                'effective_from' => Carbon::parse($validated['start_date'])->startOfMonth()->min(today()->startOfMonth()),
                'created_by' => $request->user()->id,
            ]);
            $this->scheduler->generateUpcoming($group);

            return $group;
        });

        $this->toast($warnings === [] ? __('Group created.') : __('Group created. Warning: :warnings', ['warnings' => implode(' ', $warnings)]), $warnings === [] ? 'success' : 'warning');

        return to_route('groups.show', $group);
    }

    public function show(Request $request, Group $group): Response
    {
        $this->authorizeGroup($request, $group);
        $month = Carbon::parse($request->input('month', today()->format('Y-m')).'-01');
        $user = $request->user();

        $lessons = $group->lessons()
            ->whereBetween('date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->with('teacher:id,name', 'attendances:id,lesson_id,enrollment_id,status')
            ->orderBy('date')
            ->get();

        $enrollments = $group->enrollments()
            ->with('student:id,full_name,phone', 'freezes')
            ->where(fn ($q) => $q->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Trial])
                ->orWhere('left_at', '>=', $month->toDateString()))
            ->get()
            ->sortBy('student.full_name')
            ->values();

        return Inertia::render('groups/show', [
            'group' => [
                ...$group->only(['id', 'name', 'subject', 'days', 'teacher_percent', 'status', 'teacher_id', 'room_id', 'branch_id']),
                'start_time' => substr($group->start_time, 0, 5),
                'end_time' => substr($group->end_time, 0, 5),
                'start_date' => $group->start_date->toDateString(),
                'end_date' => $group->end_date?->toDateString(),
                'teacher' => $group->teacher?->name,
                'room' => $group->room?->name,
                'branch' => $group->branch->name,
                'prices' => $group->prices->map(fn ($p) => [
                    'id' => $p->id, 'monthly_price' => $p->monthly_price, 'lessons_per_month' => $p->lessons_per_month,
                    'effective_from' => $p->effective_from->format('Y-m'),
                ]),
                'current_price' => $group->priceFor(today())?->only(['monthly_price', 'lessons_per_month']),
            ],
            'students' => $enrollments->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id,
                'student_id' => $e->student_id,
                'full_name' => $e->student->full_name,
                'phone' => $user->can('students.view_phone') ? $e->student->phone : null,
                'status' => $e->status->value,
                'balance' => $user->can('students.view_payments') || $user->can('students.view_debt') ? $e->balance : null,
                'is_debtor' => $user->can('students.view_debt') ? $e->balance < 0 : null,
                'teacher_percent' => $user->can('groups.prices') ? $e->teacher_percent : null,
                'frozen' => $e->isFrozenOn(today()),
                'joined_at' => $e->joined_at->toDateString(),
                'left_at' => $e->left_at?->toDateString(),
            ]),
            'lessons' => $lessons->map(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'date' => $lesson->date->toDateString(),
                'start_time' => substr($lesson->start_time, 0, 5),
                'status' => $lesson->status->value,
                'teacher' => $lesson->teacher?->name,
                'is_substitute' => $lesson->is_substitute,
                'is_makeup' => $lesson->is_makeup,
                'cancel_reason' => $lesson->cancel_reason,
                'attendance' => $lesson->attendances->mapWithKeys(fn ($a) => [$a->enrollment_id => $a->status->value]),
            ]),
            'month' => $month->format('Y-m'),
            ...$this->formOptions([$group->branch_id]),
        ]);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $this->authorizeGroup($request, $group);
        $validated = $this->validateGroup($request, false);
        $this->authorizeBranch((int) $validated['branch_id']);

        if (! $request->user()->can('groups.prices')) {
            unset($validated['teacher_percent']);
        }

        $warnings = $this->conflicts->forGroup($this->scheduleOf([...$validated, 'branch_id' => $group->branch_id]), $group->id);
        $scheduleChanged = $group->fill($validated)->isDirty(['days', 'start_time', 'end_time', 'room_id', 'teacher_id', 'start_date', 'end_date', 'status']);
        $group->save();

        if ($scheduleChanged) {
            $this->scheduler->rebuildFuture($group);
        }

        $this->toast($warnings === [] ? __('Saved.') : __('Saved. Warning: :warnings', ['warnings' => implode(' ', $warnings)]), $warnings === [] ? 'success' : 'warning');

        return back();
    }

    /**
     * New price / lessons per month, effective from the first day of the chosen month (next month by default).
     */
    public function storePrice(Request $request, Group $group): RedirectResponse
    {
        $this->authorizeGroup($request, $group);
        $validated = $request->validate([
            'monthly_price' => ['required', 'integer', 'min:0'],
            'lessons_per_month' => ['required', 'integer', 'min:1', 'max:60'],
            'effective_from' => ['required', 'date_format:Y-m'],
        ]);
        $this->assertDivisible((int) $validated['monthly_price'], (int) $validated['lessons_per_month']);

        $group->prices()->updateOrCreate(
            ['effective_from' => Carbon::parse($validated['effective_from'].'-01')->toDateString()],
            ['monthly_price' => $validated['monthly_price'], 'lessons_per_month' => $validated['lessons_per_month'], 'created_by' => $request->user()->id],
        );
        $this->toast(__('New price saved from :month.', ['month' => $validated['effective_from']]));

        return back();
    }

    /**
     * Add a make-up (extra) lesson.
     */
    public function storeLesson(Request $request, Group $group, ScheduleConflicts $conflicts): RedirectResponse
    {
        $this->authorizeGroup($request, $group);
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],
        ]);

        $lesson = $group->lessons()->create([
            ...$validated,
            'teacher_id' => $validated['teacher_id'] ?? $group->teacher_id,
            'room_id' => $validated['room_id'] ?? $group->room_id,
            'is_makeup' => true,
            'is_substitute' => isset($validated['teacher_id']) && (int) $validated['teacher_id'] !== $group->teacher_id,
        ]);
        $warnings = $conflicts->forLesson($lesson);
        $this->toast($warnings === [] ? __('Extra lesson added.') : __('Extra lesson added. Warning: :warnings', ['warnings' => implode(' ', $warnings)]), $warnings === [] ? 'success' : 'warning');

        return back();
    }

    /**
     * @param  list<int>  $branchIds
     * @return array<string, mixed>
     */
    private function formOptions(array $branchIds): array
    {
        return [
            'teachers' => User::role(Role::Teacher->value)->where('is_active', true)
                ->whereHas('branches', fn ($q) => $q->whereIn('branches.id', $branchIds))
                ->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::query()->whereIn('branch_id', $branchIds)->orderBy('name')->get(['id', 'name', 'branch_id']),
            'defaultBranchId' => app(CurrentBranch::class)->forCreate(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGroup(Request $request, bool $creating): array
    {
        return $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'teacher_id' => ['nullable', 'exists:users,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'teacher_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'status' => [$creating ? 'nullable' : 'required', 'in:active,archived'],
            ...($creating ? [
                'monthly_price' => ['required', 'integer', 'min:0'],
                'lessons_per_month' => ['required', 'integer', 'min:1', 'max:60'],
            ] : []),
        ]) + ['status' => 'active'];
    }

    /**
     * Schedule fields used for the room and teacher conflict check.
     *
     * @param  array<string, mixed>  $data
     * @return array{branch_id: int, days: list<int>, start_time: string, end_time: string, room_id: int|null, teacher_id: int|null}
     */
    private function scheduleOf(array $data): array
    {
        return [
            'branch_id' => (int) $data['branch_id'],
            'days' => array_values(array_map('intval', (array) $data['days'])),
            'start_time' => (string) $data['start_time'],
            'end_time' => (string) $data['end_time'],
            'room_id' => isset($data['room_id']) ? (int) $data['room_id'] : null,
            'teacher_id' => isset($data['teacher_id']) ? (int) $data['teacher_id'] : null,
        ];
    }

    private function assertDivisible(int $price, int $lessons): void
    {
        if ($price % $lessons !== 0) {
            throw ValidationException::withMessages([
                'monthly_price' => __('The price must divide by :lessons lessons without remainder.', ['lessons' => $lessons]),
            ]);
        }
    }

    private function authorizeGroup(Request $request, Group $group): void
    {
        $this->authorizeBranch($group->branch_id);
        $user = $request->user();
        abort_unless($user->can('groups.view') || $group->teacher_id === $user->id, 403);
    }
}
