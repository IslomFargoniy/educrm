<?php

namespace App\Http\Controllers\Finance;

use App\Enums\LedgerAccount;
use App\Http\Controllers\Controller;
use App\Models\Cashbox;
use App\Models\ExpenseCategory;
use App\Services\FinanceService;
use App\Support\Presenters\History;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CashboxController extends Controller
{
    public function __construct(private FinanceService $finance) {}

    public function index(): Response
    {
        $branchIds = $this->branchIds();

        return Inertia::render('cashboxes/index', [
            'cashboxes' => Cashbox::query()->whereIn('branch_id', $branchIds)->with('branch:id,name')
                ->orderBy('branch_id')->orderBy('name')->get()
                ->map(fn (Cashbox $c) => [...$c->only(['id', 'name', 'balance', 'is_active', 'branch_id']), 'method' => $c->method->value, 'branch' => $c->branch->name]),
            'summary' => [
                'cash' => $this->finance->cashTotal($branchIds),
                'teacherLiability' => $this->finance->teacherLiability($branchIds),
                'prepaid' => $this->finance->prepaidTotal($branchIds),
                'freeProfit' => $this->finance->freeProfit($branchIds),
            ],
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Cashbox $cashbox): Response
    {
        $this->authorizeBranch($cashbox->branch_id);
        $from = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to = Carbon::parse($request->input('to', today()));

        return Inertia::render('cashboxes/show', [
            'cashbox' => [...$cashbox->only(['id', 'name', 'balance', 'branch_id']), 'method' => $cashbox->method->value, 'branch' => $cashbox->branch->name],
            'history' => History::forAccount(LedgerAccount::Cashbox, $cashbox->id, $from, $to),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'freeProfit' => $this->finance->freeProfit([$cashbox->branch_id]),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'cashboxes' => Cashbox::query()->whereIn('branch_id', $this->branchIds())->where('is_active', true)->get(['id', 'name', 'branch_id', 'balance']),
        ]);
    }

    public function expense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cashbox_id' => ['required', 'exists:cashboxes,id'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $cashbox = $this->cashbox($validated['cashbox_id']);
        $this->finance->expense($cashbox, ExpenseCategory::query()->findOrFail($request->integer('expense_category_id')), (int) $validated['amount'], $validated['note'] ?? null);
        $this->toast(__('Expense saved.'));

        return back();
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cashbox_id' => ['required', 'exists:cashboxes,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'confirm' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $cashbox = $this->cashbox($validated['cashbox_id']);
        $this->finance->withdrawProfit($cashbox, (int) $validated['amount'], (bool) ($validated['confirm'] ?? false), $validated['note'] ?? null);
        $this->toast(__('Profit withdrawn.'));

        return back();
    }

    public function transfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cashbox_id' => ['required', 'exists:cashboxes,id'],
            'to_cashbox_id' => ['required', 'exists:cashboxes,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->finance->transferBetweenCashboxes($this->cashbox($validated['cashbox_id']), $this->cashbox($validated['to_cashbox_id']), (int) $validated['amount'], $validated['note'] ?? null);
        $this->toast(__('Money moved.'));

        return back();
    }

    private function cashbox(int|string $id): Cashbox
    {
        $cashbox = Cashbox::query()->findOrFail($id);
        $this->authorizeBranch($cashbox->branch_id);

        return $cashbox;
    }
}
