<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Student;
use App\Services\FinanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Students to take a payment from (the cashbox page's "Receive payment"): name or phone,
     * with the groups they study in or still owe money to, in the user's branches.
     */
    public function students(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));
        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }
        $branchIds = $this->branchIds();
        $digits = preg_replace('/\D+/', '', $term);
        $payable = fn ($q) => $q->whereHas('group', fn ($g) => $g->whereIn('branch_id', $branchIds))
            ->where(fn ($e) => $e->whereIn('status', ['active', 'trial'])->orWhere('balance', '<', 0));

        $students = Student::query()
            ->inBranches($branchIds)
            ->where(fn (Builder $w) => $w->where('full_name', 'like', "%{$term}%")
                ->when(strlen($digits) >= 3, fn (Builder $q) => $q->orWhere('phone', 'like', "%{$digits}%")))
            ->whereHas('enrollments', $payable)
            ->with(['enrollments' => fn ($q) => $payable($q)->with('group:id,name,branch_id,course_id,teacher_id', 'group.course:id,name', 'group.teacher:id,name')])
            ->orderBy('full_name')
            ->limit(15)
            ->get(['id', 'full_name', 'phone']);

        return response()->json(['data' => $students->map(fn (Student $student) => [
            'id' => $student->id,
            'name' => $student->full_name,
            'phone' => $student->phone,
            'enrollments' => $student->enrollments->map(function (Enrollment $enrollment) {
                $price = $enrollment->group->priceFor(today());

                return [
                    'id' => $enrollment->id,
                    'group' => $enrollment->group->name,
                    'course' => $enrollment->group->course?->name,
                    'teacher' => $enrollment->group->teacher?->name,
                    'branch_id' => $enrollment->group->branch_id,
                    'balance' => $enrollment->balance,
                    'discounted_price' => $price ? $enrollment->discountedMonthlyPrice($price->monthly_price) : null,
                ];
            })->values(),
        ])]);
    }

    public function reverse(Request $request, Payment $payment, FinanceService $finance): RedirectResponse
    {
        $this->authorizeBranch($payment->cashbox->branch_id);
        $user = $request->user();
        $allowed = $user->can('payments.reverse')
            || ($user->can('payments.reverse_today') && $payment->paid_at->isToday() && $payment->received_by === $user->id);
        abort_unless($allowed, 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $finance->reversePayment($payment, $validated['reason']);
        $this->toast(__('Payment cancelled.'));

        return back();
    }

    /**
     * Printable receipt: ?format=thermal (58/80 mm) or a4.
     */
    public function receipt(Request $request, Payment $payment): View
    {
        $this->authorizeBranch($payment->cashbox->branch_id);
        $payment->load('enrollment.student', 'enrollment.group.branch', 'cashbox', 'receiver');

        return view('receipt', [
            'payment' => $payment,
            'format' => $request->query('format') === 'a4' ? 'a4' : 'thermal',
            'width' => $request->query('width') === '58' ? 58 : 80,
            'balanceAfter' => $payment->transaction_id
                ? LedgerEntry::query()->where('transaction_id', $payment->transaction_id)->where('account_type', 'enrollment')->value('balance_after')
                : null,
        ]);
    }
}
