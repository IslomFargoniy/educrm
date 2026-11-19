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

        $teacherEarnings = $transactions()->whereIn('type', [TransactionType::TeacherEarning, TransactionType::EarningReversal, TransactionType::TeacherPayout])
            ->with('entries')->get()
            ->flatMap(fn (Transaction $t) => $t->entries->where('account_type.value', 'teacher')->map(fn ($e) => ['teacher_id' => $e->account_id, 'type' => $t->type->value, 'amount' => $e->amount]))
            ->groupBy('teacher_id')
            ->map(fn ($rows, $teacherId) => [
                'teacher' => User::query()->find($teacherId)?->name,
                'teacher_id' => (int) $teacherId,
                'earned' => (int) $rows->whereIn('type', ['teacher_earning', 'earning_reversal'])->sum('amount'),
                'paid' => -(int) $rows->where('type', 'teacher_payout')->sum('amount'),
                'balance' => User::query()->find($teacherId)?->balance,
            ])->values();

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
