<?php

namespace App\Http\Controllers;

use App\Enums\LedgerAccount;
use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Group;
use App\Models\User;
use App\Services\FinanceService;
use App\Support\Presenters\History;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function index(Request $request): Response
    {
        $branchIds = $this->branchIds();

        $teachers = User::role(Role::Teacher->value)
            ->whereHas('branches', fn ($q) => $q->whereIn('branches.id', $branchIds))
            ->orderBy('name')
            ->get()
            ->map(fn (User $teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'phone' => $teacher->phone,
                'balance' => $teacher->balance,
                'pending' => History::pendingForTeacher($teacher)['total'],
                'groups' => Group::query()->where('teacher_id', $teacher->id)->where('status', 'active')->pluck('name'),
                'is_active' => $teacher->is_active,
            ]);

        return Inertia::render('teachers/index', [
            'teachers' => $teachers,
            'cashboxes' => $request->user()->can('payouts.create')
                ? Cashbox::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->get(['id', 'name', 'balance'])
                : [],
        ]);
    }

    public function show(Request $request, User $teacher): Response
    {
        $user = $request->user();
        abort_unless($teacher->id === $user->id || ($user->can('teachers.view') && $teacher->branches()->whereIn('branches.id', $user->accessibleBranchIds())->exists()), 403);

        $from = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to = Carbon::parse($request->input('to', today()));

        return Inertia::render('teachers/show', [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'phone' => $teacher->phone,
                'balance' => $teacher->balance,
                'groups' => Group::query()->where('teacher_id', $teacher->id)->where('status', 'active')->get(['id', 'name', 'teacher_percent']),
            ],
            'history' => History::forAccount(LedgerAccount::Teacher, $teacher->id, $from, $to),
            'pending' => History::pendingForTeacher($teacher),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'cashboxes' => $user->can('payouts.create')
                ? Cashbox::query()->whereIn('branch_id', $this->branchIds())->where('is_active', true)->get(['id', 'name', 'balance', 'branch_id'])
                : [],
        ]);
    }

    public function payout(Request $request, User $teacher, FinanceService $finance): RedirectResponse
    {
        $validated = $request->validate([
            'cashbox_id' => ['required', 'exists:cashboxes,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $cashbox = Cashbox::query()->findOrFail($request->integer('cashbox_id'));
        $this->authorizeBranch($cashbox->branch_id);

        if (! $teacher->branches()->whereKey($cashbox->branch_id)->exists()) {
            throw ValidationException::withMessages(['cashbox_id' => __('The teacher does not work in the branch of this cashbox.')]);
        }

        $finance->payTeacher($teacher, $cashbox, (int) $validated['amount'], $validated['note'] ?? null);
        $this->toast(__('Salary paid.'));

        return back();
    }
}
