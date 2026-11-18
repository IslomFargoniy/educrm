<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\TransactionType;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Lesson;
use App\Models\Transaction;
use App\Services\FinanceService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(FinanceService $finance): Response
    {
        $branchIds = $this->branchIds();
        $sumOf = fn (array $types, $from, $to) => (int) Transaction::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('type', $types)
            ->whereNull('reversed_at')
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('amount');

        $months = collect(range(5, 0))->map(function (int $back) use ($sumOf) {
            $start = now()->subMonthsNoOverflow($back)->startOfMonth();
            $end = $start->endOfMonth();

            return [
                'month' => $start->format('Y-m'),
                'income' => $sumOf([TransactionType::StudentPayment->value], $start, $end),
                'expenses' => $sumOf([TransactionType::Expense->value, TransactionType::TeacherPayout->value], $start, $end),
            ];
        });

        $debtors = Enrollment::query()
            ->where('balance', '<', 0)
            ->whereHas('group', fn ($q) => $q->whereIn('branch_id', $branchIds));

        return Inertia::render('dashboard', [
            'stats' => [
                'todayIncome' => $sumOf([TransactionType::StudentPayment->value], today(), now()->endOfDay()),
                'monthIncome' => $sumOf([TransactionType::StudentPayment->value], now()->startOfMonth(), now()->endOfDay()),
                'cash' => $finance->cashTotal($branchIds),
                'teacherLiability' => $finance->teacherLiability($branchIds),
                'prepaid' => $finance->prepaidTotal($branchIds),
                'freeProfit' => $finance->freeProfit($branchIds),
                'debtorsCount' => (clone $debtors)->distinct()->count('student_id'),
                'debtTotal' => -(int) (clone $debtors)->sum('balance'),
                'newLeads' => Lead::query()->whereIn('branch_id', $branchIds)->where('status', LeadStatus::New)->count(),
                'activeStudents' => Enrollment::query()->whereIn('status', ['active', 'trial'])
                    ->whereHas('group', fn ($q) => $q->whereIn('branch_id', $branchIds))->distinct()->count('student_id'),
            ],
            'cashboxes' => Cashbox::query()->whereIn('branch_id', $branchIds)->where('is_active', true)
                ->with('branch:id,name')->orderBy('branch_id')->get(['id', 'branch_id', 'name', 'method', 'balance']),
            'months' => $months,
            'todayLessons' => Lesson::query()
                ->whereDate('date', today())
                ->whereHas('group', fn ($q) => $q->whereIn('branch_id', $branchIds))
                ->with('group:id,name', 'teacher:id,name', 'room:id,name')
                ->orderBy('start_time')
                ->limit(12)
                ->get()
                ->map(fn (Lesson $lesson) => [
                    'id' => $lesson->id,
                    'time' => substr($lesson->start_time, 0, 5),
                    'group' => $lesson->group->name,
                    'teacher' => $lesson->teacher?->name,
                    'room' => $lesson->room?->name,
                    'status' => $lesson->status->value,
                ]),
        ]);
    }
}
