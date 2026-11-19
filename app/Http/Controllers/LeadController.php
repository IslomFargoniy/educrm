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
    public function index(Request $request): Response
    {
        $branchIds = $this->branchIds();
        $filters = $request->only(['status', 'source', 'assigned_to', 'q']);

        $leads = Lead::query()
            ->whereIn('branch_id', $branchIds)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($filters['assigned_to'] ?? null, fn ($q, $user) => $q->where('assigned_to', $user))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('full_name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->with('assignee:id,name', 'branch:id,name')
            ->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'contacted' THEN 1 WHEN 'trial' THEN 2 ELSE 3 END")
            ->orderBy('next_contact_at')
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                ...$lead->only(['id', 'full_name', 'first_name', 'last_name', 'phone', 'interested_course', 'note', 'student_id']),
                'source' => $lead->source->value,
                'status' => $lead->status->value,
                'assigned_to' => $lead->assigned_to,
                'assignee' => $lead->assignee?->name,
                'next_contact_at' => $lead->next_contact_at?->toDateString(),
                'created_at' => $lead->created_at->toIso8601String(),
                'branch' => $lead->branch->name,
            ]);

        return Inertia::render('leads/index', [
            'leads' => $leads,
            'filters' => $filters,
            'courses' => Course::options(),
            'staff' => User::role([Role::Admin->value, Role::Manager->value])
                ->whereHas('branches', fn ($q) => $q->whereIn('branches.id', $branchIds))->orderBy('name')->get(['id', 'name']),
            'sources' => array_column(LeadSource::cases(), 'value'),
            'statuses' => array_column(LeadStatus::cases(), 'value'),
        ]);
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
