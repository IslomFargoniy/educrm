<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Enums\LedgerAccount;
use App\Enums\TransactionType;
use App\Models\Cashbox;
use App\Models\ChargeCoverage;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lead;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Exports\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __invoke(Request $request): Response|StreamedResponse
    {
        $branchIds = $this->branchIds();
        $from = Carbon::parse($request->input('from', now()->startOfMonth()))->startOfDay();
        $to = Carbon::parse($request->input('to', today()))->endOfDay();

        $transactions = fn () => Transaction::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('occurred_at', [$from, $to])
            ->whereNull('reversed_at');

        $totals = $transactions()->selectRaw('type, SUM(amount) as total, COUNT(*) as count')->groupBy('type')->get()
            ->mapWithKeys(fn ($row) => [$row->type->value => ['total' => (int) $row->getAttribute('total'), 'count' => (int) $row->getAttribute('count')]]);

        // Exact change of money in the cashboxes: every cash movement, reversals and withdrawals included.
        $cashChange = (int) LedgerEntry::query()
            ->where('account_type', LedgerAccount::Cashbox)
            ->whereIn('account_id', Cashbox::query()->whereIn('branch_id', $branchIds)->select('id'))
            ->whereHas('transaction', fn ($q) => $q->whereBetween('occurred_at', [$from, $to]))
            ->sum('amount');

        $expenses = $transactions()->where('type', TransactionType::Expense)
            ->with('expenseCategory:id,name')->get()
            ->groupBy(fn (Transaction $t) => $t->expenseCategory->name ?? '—')
            ->map(fn ($items, $name) => ['category' => $name, 'total' => (int) $items->sum('amount')])
            ->sortByDesc('total')->values();

        // Teacher money straight from the ledger: opening + earned − paid (+ other) = closing.
        // Payouts in the period may pay for lessons earned before it, so "paid" can exceed "earned".
        $teacherEntries = LedgerEntry::query()
            ->join('transactions', 'transactions.id', '=', 'ledger_entries.transaction_id')
            ->where('ledger_entries.account_type', LedgerAccount::Teacher)
            ->whereIn('transactions.branch_id', $branchIds)
            ->where('transactions.occurred_at', '<=', $to)
            ->groupBy('ledger_entries.account_id')
            ->selectRaw('ledger_entries.account_id as teacher_id')
            ->selectRaw('SUM(CASE WHEN transactions.occurred_at < ? THEN ledger_entries.amount ELSE 0 END) as opening', [$from])
            ->selectRaw('SUM(CASE WHEN transactions.occurred_at >= ? AND transactions.type IN (?, ?) THEN ledger_entries.amount ELSE 0 END) as earned', [$from, TransactionType::TeacherEarning->value, TransactionType::EarningReversal->value])
            ->selectRaw('SUM(CASE WHEN transactions.occurred_at >= ? AND transactions.type = ? THEN ledger_entries.amount ELSE 0 END) as paid', [$from, TransactionType::TeacherPayout->value])
            ->selectRaw('SUM(ledger_entries.amount) as closing')
            ->get();
        $teacherNames = User::query()->whereIn('id', $teacherEntries->pluck('teacher_id'))->pluck('name', 'id');

        $teacherEarnings = $teacherEntries
            ->map(fn ($row) => [
                'teacher' => $teacherNames[$row->getAttribute('teacher_id')] ?? null,
                'teacher_id' => (int) $row->getAttribute('teacher_id'),
                'opening' => (int) $row->getAttribute('opening'),
                'earned' => (int) $row->getAttribute('earned'),
                'paid' => -(int) $row->getAttribute('paid'),
                'closing' => (int) $row->getAttribute('closing'),
            ])
            ->filter(fn (array $row) => $row['opening'] !== 0 || $row['earned'] !== 0 || $row['paid'] !== 0 || $row['closing'] !== 0)
            ->sortBy('teacher')
            ->values();

        $debtors = Enrollment::query()
            ->where('balance', '<', 0)
            ->whereHas('group', fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->with('student:id,full_name,phone', 'group:id,name')
            ->orderBy('balance')
            ->get()
            ->map(fn (Enrollment $e) => [
                'student_id' => $e->student_id, 'student' => $e->student->full_name, 'phone' => $e->student->phone,
                'group' => $e->group->name, 'debt' => -$e->balance, 'status' => $e->status->value,
            ]);

        $groups = Group::query()->whereIn('branch_id', $branchIds)->with('teacher:id,name')->get()
            ->map(function (Group $group) use ($from, $to) {
                $income = (int) Transaction::query()->where('type', TransactionType::StudentPayment)->whereNull('reversed_at')
                    ->whereBetween('occurred_at', [$from, $to])->where('meta->group_id', $group->id)->sum('amount');
                // Shares come from lesson money recognised in the period (charge coverages), not from
                // payments received: a payment made last month can pay for this month's lessons.
                $earned = ChargeCoverage::query()->whereNull('reversed_at')->whereBetween('covered_at', [$from, $to])
                    ->whereHas('charge.enrollment', fn ($q) => $q->where('group_id', $group->id))
                    ->selectRaw('COALESCE(SUM(amount), 0) as revenue, COALESCE(SUM(teacher_amount), 0) as teacher')
                    ->first();
                $revenue = (int) $earned?->getAttribute('revenue');
                $teacher = (int) $earned?->getAttribute('teacher');

                return [
                    'is_active' => $group->status === 'active',
                    'group' => $group->name,
                    'teacher' => $group->teacher?->name,
                    'students' => $group->enrollments()->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Trial])->count(),
                    'income' => $income,
                    'revenue' => $revenue,
                    'teacher_share' => $teacher,
                    'center_share' => $revenue - $teacher,
                ];
            })
            // Closed groups stay in the report for the periods they had money in.
            ->filter(fn (array $row, int $key) => $row['income'] !== 0 || $row['revenue'] !== 0 || $row['is_active'])
            ->map(fn (array $row) => collect($row)->except('is_active')->all())
            ->sortByDesc('income')->values();

        $leadSources = Lead::query()->whereIn('branch_id', $branchIds)->whereBetween('created_at', [$from, $to])->get()
            ->groupBy(fn (Lead $lead) => $lead->source->value)
            ->map(fn ($items, $source) => [
                'source' => $source,
                'total' => $items->count(),
                'converted' => $items->where('status.value', 'converted')->count(),
            ])->sortByDesc('total')->values();

        if ($request->query('export') === 'debtors') {
            return XlsxExport::download('qarzdorlar.xlsx', ['Student', 'Phone', 'Group', 'Debt'],
                array_values($debtors->map(fn ($d) => [$d['student'], $d['phone'], $d['group'], $d['debt']])->all()));
        }

        return Inertia::render('reports', [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => $totals,
            'cashChange' => $cashChange,
            'expenses' => $expenses,
            'teachers' => $teacherEarnings,
            'debtors' => $debtors,
            'groups' => $groups,
            'leadSources' => $leadSources,
        ]);
    }
}
