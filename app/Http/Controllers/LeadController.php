<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Group;
use App\Models\Lead;
use App\Models\User;
use App\Support\CurrentBranch;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    /**
     * Leads in two lists: orders (asked for a course at the reception, no first lesson yet)
     * and the ones signed up for a first lesson (course, date and time known).
     */
    public function index(Request $request): Response
    {
        $branchIds = $this->branchIds();
        $filters = $request->only(['list', 'status', 'source', 'assigned_to', 'q', 'course_id', 'teacher_id', 'from', 'to', 'day', 'parity']);
        $list = in_array($filters['list'] ?? null, ['orders', 'trial'], true) ? $filters['list'] : null;
        $base = fn () => Lead::query()
            ->whereIn('branch_id', $branchIds)
            ->when($list === 'orders', fn ($q) => $q->whereNull('trial_at')->where('status', '!=', LeadStatus::Converted))
            ->when($list === 'trial', fn ($q) => $q->whereNotNull('trial_at'));
        $dateColumn = $list === 'trial' ? 'trial_at' : 'created_at';
        $day = isset($filters['day']) ? (int) $filters['day'] : null;

        $leads = $base()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($filters['assigned_to'] ?? null, fn ($q, $user) => $q->where('assigned_to', $user))
            ->when($filters['course_id'] ?? null, fn ($q, $id) => $q->where('course_id', $id))
            ->when($filters['teacher_id'] ?? null, fn ($q, $id) => $q->whereHas('trialGroup', fn ($g) => $g->where('teacher_id', $id)))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate($dateColumn, '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate($dateColumn, '<=', $to))
            ->when($day, fn ($q, $d) => $q->whereHas('trialGroup', fn ($g) => $g->whereJsonContains('days', $d)))
            ->when(in_array($filters['parity'] ?? null, ['odd', 'even'], true), fn ($q) => $q->whereHas('trialGroup', fn ($g) => $g->whereJsonContains('days', $filters['parity'] === 'odd' ? 1 : 2)))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('full_name', 'like', "%{$term}%")->orWhere('phone', 'like', '%'.preg_replace('/\D+/', '', (string) $term).'%')))
            ->with('assignee:id,name', 'branch:id,name', 'trialGroup:id,name,teacher_id', 'trialGroup.teacher:id,name')
            ->when($list === 'trial', fn ($q) => $q->orderByDesc('trial_at'), fn ($q) => $q
                ->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'contacted' THEN 1 WHEN 'trial' THEN 2 ELSE 3 END")
                ->orderBy('next_contact_at')
                ->latest())
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                ...$lead->only(['id', 'full_name', 'first_name', 'last_name', 'phone', 'interested_course', 'course_id', 'note', 'student_id']),
                'source' => $lead->source->value,
                'status' => $lead->status->value,
                'assigned_to' => $lead->assigned_to,
                'assignee' => $lead->assignee?->name,
                'next_contact_at' => $lead->next_contact_at?->toDateString(),
                'created_at' => $lead->created_at->toIso8601String(),
                'trial_at' => $lead->trial_at?->toIso8601String(),
                'trial_group' => $lead->trialGroup?->name,
                'teacher' => $lead->trialGroup?->teacher?->name,
                'branch' => $lead->branch->name,
            ]);

        return Inertia::render('leads/index', [
            'leads' => $leads,
            'filters' => $filters,
            'list' => $list,
            'statusCounts' => $base()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'courses' => Course::options(),
            'staff' => User::role([Role::Admin->value, Role::Manager->value])
                ->whereHas('branches', fn ($q) => $q->whereIn('branches.id', $branchIds))->orderBy('name')->get(['id', 'name']),
            'teachers' => User::role(Role::Teacher->value)
                ->whereHas('branches', fn ($q) => $q->whereIn('branches.id', $branchIds))->orderBy('name')->get(['id', 'name']),
            'trialGroups' => Group::query()->whereIn('branch_id', $branchIds)->where('status', 'active')->with('teacher:id,name')->orderBy('name')
                ->get(['id', 'name', 'course_id', 'teacher_id', 'days', 'start_time', 'end_time', 'branch_id'])
                ->map(fn (Group $g) => [
                    'id' => $g->id, 'name' => $g->name, 'course_id' => $g->course_id, 'teacher' => $g->teacher?->name,
                    'days' => array_values(array_map('intval', $g->days ?? [])), 'start_time' => substr((string) $g->start_time, 0, 5),
                ]),
            'sources' => array_column(LeadSource::cases(), 'value'),
            'statuses' => array_column(LeadStatus::cases(), 'value'),
        ]);
    }

    /**
     * Sign the lead up for a first (trial) lesson in a group at a date and time; null clears it.
     */
    public function trial(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeBranch($lead->branch_id);
        $validated = $request->validate([
            'trial_group_id' => ['nullable', 'required_with:trial_at', Rule::exists('groups', 'id')->where('branch_id', $lead->branch_id)],
            'trial_at' => ['nullable', 'required_with:trial_group_id', 'date'],
        ]);

        if (empty($validated['trial_group_id'])) {
            $lead->update(['trial_at' => null, 'trial_group_id' => null, 'status' => $lead->status === LeadStatus::Trial ? LeadStatus::Contacted : $lead->status]);
            $this->toast(__('First lesson cancelled.'));

            return back();
        }

        $group = Group::query()->findOrFail((int) $validated['trial_group_id']);
        $lead->update([
            'trial_at' => $validated['trial_at'],
            'trial_group_id' => $group->id,
            'course_id' => $lead->course_id ?? $group->course_id,
            'status' => $lead->status === LeadStatus::Converted ? LeadStatus::Converted : LeadStatus::Trial,
        ]);
        $this->toast(__('Signed up for the first lesson.'));

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $branchId = app(CurrentBranch::class)->forCreate();
        $this->authorizeBranch($branchId);
        Lead::query()->create([...$validated, 'branch_id' => $branchId, 'assigned_to' => $validated['assigned_to'] ?? $request->user()->id]);
        $this->toast(__('Lead added.'));

        return back();
    }

    public function show(Lead $lead): Response
    {
        $this->authorizeBranch($lead->branch_id);
        $lead->load('notes.user:id,name', 'assignee:id,name', 'student:id,full_name');

        return Inertia::render('leads/show', [
            'lead' => [
                ...$lead->only(['id', 'full_name', 'first_name', 'last_name', 'phone', 'interested_course', 'course_id', 'note', 'assigned_to', 'student_id']),
                'source' => $lead->source->value,
                'status' => $lead->status->value,
                'next_contact_at' => $lead->next_contact_at?->toDateString(),
                'created_at' => $lead->created_at->toIso8601String(),
                'assignee' => $lead->assignee?->name,
                'student' => $lead->student?->full_name,
                'notes' => $lead->notes->map(fn ($n) => ['id' => $n->id, 'text' => $n->text, 'by' => $n->user?->name, 'at' => $n->created_at->toIso8601String()]),
            ],
            'courses' => Course::options($lead->course_id),
            'staff' => User::role([Role::Admin->value, Role::Manager->value])
                ->whereHas('branches', fn ($q) => $q->where('branches.id', $lead->branch_id))->orderBy('name')->get(['id', 'name']),
            'groups' => Group::query()->where('branch_id', $lead->branch_id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'sources' => array_column(LeadSource::cases(), 'value'),
            'statuses' => array_column(LeadStatus::cases(), 'value'),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeBranch($lead->branch_id);
        $lead->update($this->validated($request));
        $this->toast(__('Saved.'));

        return back();
    }

    public function note(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeBranch($lead->branch_id);
        $validated = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        $lead->notes()->create(['text' => $validated['text'], 'user_id' => $request->user()->id]);

        if ($lead->status === LeadStatus::New) {
            $lead->update(['status' => LeadStatus::Contacted]);
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^\+998\d{9}$/'],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'course_id' => ['nullable', 'exists:courses,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'next_contact_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]) + ['status' => LeadStatus::New->value];
    }
}
