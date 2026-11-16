<?php

namespace App\Http\Controllers;

use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
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
