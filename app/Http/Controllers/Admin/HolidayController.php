<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Services\LessonScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $branchIds = $request->user()->accessibleBranchIds();

        return Inertia::render('admin/holidays', [
            'holidays' => Holiday::query()
                ->where(fn ($q) => $q->whereNull('branch_id')->orWhereIn('branch_id', $branchIds))
                ->whereYear('date', '>=', now()->year - 1)
                ->with('branch:id,name')->orderByDesc('date')->get()
                ->map(fn (Holiday $h) => ['id' => $h->id, 'date' => $h->date->toDateString(), 'name' => $h->name, 'branch' => $h->branch?->name]),
            'canGlobal' => $request->user()->isSuperadmin(),
        ]);
    }

    public function store(Request $request, LessonScheduler $scheduler): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        if ($validated['branch_id'] ?? null) {
            $this->authorizeBranch((int) $validated['branch_id']);
        } else {
            abort_unless($request->user()->isSuperadmin(), 403);
        }

        $holiday = Holiday::query()->create($validated);
        $cancelled = $scheduler->applyHoliday($holiday);
        $this->toast(__('Holiday saved. Cancelled lessons: :count.', ['count' => $cancelled]));

        return back();
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        abort_unless($holiday->branch_id ? $request->user()->canAccessBranch($holiday->branch_id) : $request->user()->isSuperadmin(), 403);
        $holiday->delete();

        return back();
    }
}
