<?php

namespace App\Http\Controllers;

use App\Enums\ChargeStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadStatus;
use App\Enums\LedgerAccount;
use App\Enums\TransactionType;
use App\Models\Cashbox;
use App\Models\ChargeCoverage;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lead;
use App\Models\LedgerEntry;
use App\Models\LessonCharge;
use App\Models\StudentVisit;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Exports\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        $groups = Group::query()->whereIn('branch_id', $branchIds)->with('teacher:id,name', 'course:id,name')->get()
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
                // What students must pay for the lessons held in the period (written-off part excluded).
                $charged = (int) LessonCharge::query()->where('status', ChargeStatus::Active)
                    ->whereBetween('lesson_date', [$from->toDateString(), $to->toDateString()])
                    ->whereHas('enrollment', fn ($q) => $q->where('group_id', $group->id))
                    ->sum(DB::raw('amount - written_off_amount'));
                $studying = $group->enrollments()->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Trial])->get();
                $price = $group->priceFor($to);
                $monthlyPlan = $price === null ? 0 : (int) $studying->where('status', EnrollmentStatus::Active)
                    ->sum(fn (Enrollment $e) => $e->discountedMonthlyPrice($price->monthly_price));
                $percents = $studying->map(fn (Enrollment $e) => $e->teacher_percent ?? $group->teacher_percent)->unique()->values();

                return [
                    'is_active' => $group->status === 'active',
                    'course' => $group->course->name ?? $group->subject,
                    'debt' => (int) -$group->enrollments()->where('balance', '<', 0)->sum('balance'),
                    'group' => $group->name,
                    'teacher' => $group->teacher?->name,
                    'students' => $studying->count(),
                    'teacher_percent' => $group->teacher_percent,
                    // Some students have their own percent: show the range.
                    'percent_range' => $percents->count() > 1 ? [$percents->min(), $percents->max()] : null,
                    'monthly_plan' => $monthlyPlan,
                    'charged' => $charged,
                    'income' => $income,
                    'revenue' => $revenue,
                    'teacher_share' => $teacher,
                    'center_share' => $revenue - $teacher,
                ];
            })
            // Closed groups stay in the report for the periods they had money in.
            ->filter(fn (array $row, int $key) => $row['income'] !== 0 || $row['revenue'] !== 0 || $row['charged'] !== 0 || $row['is_active'])
            ->map(fn (array $row) => collect($row)->except('is_active')->all())
            ->sortByDesc('income')->values();

        // Lead funnel for leads created in the period: contacted → trial → joined a group → paid.
        $periodLeads = Lead::query()->whereIn('branch_id', $branchIds)->whereBetween('created_at', [$from, $to])
            ->with('student.enrollments.payments')
            ->get();
        $funnel = function ($items): array {
            $enrollments = fn (Lead $lead) => $lead->student === null ? collect() : $lead->student->enrollments;

            return [
                'total' => $items->count(),
                'contacted' => $items->filter(fn (Lead $lead) => $lead->status !== LeadStatus::New)->count(),
                'trial' => $items->filter(fn (Lead $lead) => $lead->status === LeadStatus::Trial
                    || $enrollments($lead)->contains(fn (Enrollment $e) => $e->trial_lessons > 0))->count(),
                'joined' => $items->filter(fn (Lead $lead) => $enrollments($lead)->isNotEmpty())->count(),
                'studying' => $items->filter(fn (Lead $lead) => $enrollments($lead)
                    ->contains(fn (Enrollment $e) => $e->status === EnrollmentStatus::Active))->count(),
                'paid' => $items->filter(fn (Lead $lead) => $enrollments($lead)
                    ->contains(fn (Enrollment $e) => $e->payments->whereNull('reversed_at')->isNotEmpty()))->count(),
                'rejected' => $items->filter(fn (Lead $lead) => $lead->status === LeadStatus::Rejected)->count(),
            ];
        };
        $leadSources = $periodLeads->groupBy(fn (Lead $lead) => $lead->source->value)
            ->map(fn ($items, $source) => ['source' => $source, ...$funnel($items)])
            ->sortByDesc('total')->values();
        $leadCourses = $periodLeads->groupBy(fn (Lead $lead) => $lead->interested_course ?? '')
            ->map(fn ($items, $course) => ['course' => $course === '' ? null : $course, ...$funnel($items)])
            ->sortByDesc('total')->values();
        // Courses: the group rows summed per course, with the leads who asked for that course.
        $leadsByCourse = $leadCourses->keyBy(fn (array $row) => mb_strtolower((string) $row['course']));
        $courseRows = $groups->groupBy('course')
            ->map(function ($rows, $course) use ($leadsByCourse) {
                $leads = $leadsByCourse->get(mb_strtolower((string) $course));

                return [
                    'course' => $course === '' ? null : $course,
                    'groups' => $rows->count(),
                    ...collect(['students', 'monthly_plan', 'charged', 'income', 'revenue', 'teacher_share', 'center_share', 'debt'])
                        ->mapWithKeys(fn (string $key) => [$key => (int) $rows->sum($key)])->all(),
                    'leads' => $leads['total'] ?? 0,
                    'leads_joined' => $leads['joined'] ?? 0,
                ];
            })
            ->sortByDesc('revenue')->values();

        $studentFlow = [
            'leads' => $funnel($periodLeads),
            // Every enrollment, not only ones that came from leads.
            'joined' => Enrollment::query()->whereHas('group', fn ($q) => $q->whereIn('branch_id', $branchIds))
                ->whereNull('transferred_from_id')
                ->whereBetween('joined_at', [$from->toDateString(), $to->toDateString()])->count(),
            'transferred' => Enrollment::query()->whereHas('group', fn ($q) => $q->whereIn('branch_id', $branchIds))
                ->whereNotNull('transferred_from_id')
                ->whereBetween('joined_at', [$from->toDateString(), $to->toDateString()])->count(),
            'left' => Enrollment::query()->whereHas('group', fn ($q) => $q->whereIn('branch_id', $branchIds))
                ->where('status', EnrollmentStatus::Left)
                ->whereBetween('left_at', [$from->toDateString(), $to->toDateString()])->count(),
        ];

        // Face terminal passes: one row per student per day (first entrance, last exit).
        $visits = StudentVisit::query()
            ->whereIn('branch_id', $branchIds)
            ->whereNotNull('student_id')
            ->whereBetween('scanned_at', [$from, $to])
            ->with('student:id,full_name')
            ->orderByDesc('scanned_at')
            ->limit(5000)
            ->get()
            ->groupBy(fn (StudentVisit $visit) => $visit->scanned_at->toDateString().'|'.$visit->student_id)
            ->map(function ($passes) {
                $first = $passes->where('direction', 'in')->sortBy('scanned_at')->first();
                $last = $passes->where('direction', 'out')->sortByDesc('scanned_at')->first();
                $any = $passes->first();

                return [
                    'date' => $any->scanned_at->toDateString(),
                    'student_id' => $any->student_id,
                    'student' => $any->student?->full_name,
                    'first_in' => $first?->scanned_at->format('H:i'),
                    'last_out' => $last?->scanned_at->format('H:i'),
                    'passes' => $passes->count(),
                ];
            })
            ->values();

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
            'leadCourses' => $leadCourses,
            'courses' => $courseRows,
            'studentFlow' => $studentFlow,
            'visits' => $visits,
        ]);
    }
}
