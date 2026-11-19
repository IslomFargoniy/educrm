<?php

namespace App\Services\Reports;

use App\Enums\TransactionType;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Staff pay for a month: fixed salary + KPI for every student they took in whose first payment fell in the month.
 * The KPI comes out of the center's share; salary payments are expenses of the "Staff salary" category.
 */
class StaffSalary
{
    public const EXPENSE_CATEGORY = 'Xodimlar oyligi';

    /**
     * @param  list<int>  $branchIds
     * @return array<int, array<string, mixed>>
     */
    public function forMonth(string $month, array $branchIds, ?int $onlyUserId = null): array
    {
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();

        // First (not cancelled) payment of every student, whatever group it went to.
        $firstPayments = DB::table('payments')
            ->join('enrollments', 'enrollments.id', '=', 'payments.enrollment_id')
            ->whereNull('payments.reversed_at')
            ->groupBy('enrollments.student_id')
            ->selectRaw('enrollments.student_id, MIN(payments.paid_at) as first_paid_at');

        $staff = User::query()
            ->where('is_active', true)
            ->when($onlyUserId, fn ($q, $id) => $q->whereKey($id))
            ->where(fn ($q) => $q->whereNotNull('salary_fixed')->orWhereNotNull('kpi_first_payment'))
            ->where(fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'superadmin'))
                ->orWhereHas('branches', fn ($b) => $b->whereIn('branches.id', $branchIds)))
            ->orderBy('name')
            ->get();

        return $staff->map(function (User $user) use ($start, $end, $branchIds, $firstPayments) {
            $registered = Student::query()->where('registered_by', $user->id)->inBranches($branchIds);
            $paidFirst = (clone $registered)
                ->joinSub($firstPayments, 'first', 'first.student_id', '=', 'students.id')
                ->whereBetween('first.first_paid_at', [$start, $end->endOfDay()])
                ->orderBy('first.first_paid_at')
                ->get(['students.id', 'students.full_name', 'first.first_paid_at']);
            $rate = (int) ($user->kpi_first_payment ?? 0);
            $fixed = (int) ($user->salary_fixed ?? 0);
            $kpi = $paidFirst->count() * $rate;
            $paid = (int) Transaction::query()
                ->where('type', TransactionType::Expense)
                ->whereNull('reversed_at')
                ->whereIn('branch_id', $branchIds)
                ->where('meta->staff_id', $user->id)
                ->where('meta->month', $start->format('Y-m'))
                ->sum('amount');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->getRoleNames()->first(),
                'fixed' => $fixed,
                'kpi_rate' => $rate,
                'registered' => (clone $registered)->whereBetween('students.created_at', [$start, $end->endOfDay()])->count(),
                'first_payments' => $paidFirst->count(),
                'kpi' => $kpi,
                'total' => $fixed + $kpi,
                'paid' => $paid,
                'left' => $fixed + $kpi - $paid,
                'students' => $paidFirst->map(fn ($s) => ['id' => $s->id, 'full_name' => $s->full_name, 'first_paid_at' => (string) $s->getAttribute('first_paid_at')])->values()->all(),
            ];
        })->values()->all();
    }
}
