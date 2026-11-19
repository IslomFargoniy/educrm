<?php

namespace App\Http\Controllers;

use App\Models\Cashbox;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\Reports\StaffSalary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff salaries: fixed amount plus KPI for students taken in who made their first payment.
 */
class StaffSalaryController extends Controller
{
    public function index(Request $request, StaffSalary $salaries): Response
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) ? (string) $request->input('month') : now()->format('Y-m');
        $user = $request->user();
        // Without the reports permission a staff member sees only their own pay.
        $own = ! $user->can('reports.view');
        abort_if($own && $user->salary_fixed === null && $user->kpi_first_payment === null, 403);
        $branchIds = $own ? $user->accessibleBranchIds() : $this->branchIds();

        return Inertia::render('staff-salaries', [
            'month' => $month,
            'own' => $own,
            'staff' => $salaries->forMonth($month, $branchIds, $own ? $user->id : null),
            'cashboxes' => ! $own && $user->can('payouts.create')
                ? Cashbox::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'balance', 'branch_id'])
                : [],
        ]);
    }

    public function pay(Request $request, User $user, FinanceService $finance): RedirectResponse
    {
        $validated = $request->validate([
            'cashbox_id' => ['required', 'exists:cashboxes,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'month' => ['required', 'date_format:Y-m'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $cashbox = Cashbox::query()->findOrFail((int) $validated['cashbox_id']);
        $this->authorizeBranch($cashbox->branch_id);
        $finance->payStaff($user, $cashbox, (int) $validated['amount'], $validated['month'], $validated['note'] ?? null);
        $this->toast(__('Salary paid.'));

        return back();
    }
}
