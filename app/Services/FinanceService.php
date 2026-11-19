<?php

namespace App\Services;

use App\Enums\ChargeStatus;
use App\Enums\LedgerAccount;
use App\Enums\TransactionType;
use App\Events\PaymentReceived;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\ExpenseCategory;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cash operations: student payments, refunds, transfers, teacher payouts,
 * expenses and owner withdrawals.
 */
class FinanceService
{
    public function __construct(private Ledger $ledger, private CoverageService $coverage) {}

    public function receivePayment(Enrollment $enrollment, Cashbox $cashbox, int $amount, ?string $note = null, ?CarbonInterface $paidAt = null, ?string $idempotencyKey = null): Payment
    {
        $this->assertPositive($amount);
        $enrollment->loadMissing('group', 'student');
        $this->assertSameBranch($cashbox, $enrollment);

        if ($idempotencyKey !== null && ($existing = $this->paymentForKey($idempotencyKey, $enrollment))) {
            return $existing;
        }

        try {
            $payment = $this->storePayment($enrollment, $cashbox, $amount, $note, $paidAt, $idempotencyKey);
        } catch (UniqueConstraintViolationException $e) {
            // The same request arrived twice at once: the other one stored it.
            if ($idempotencyKey !== null && ($existing = $this->paymentForKey($idempotencyKey, $enrollment))) {
                return $existing;
            }

            throw $e;
        }

        PaymentReceived::dispatch($payment);

        return $payment;
    }

    /**
     * The payment already stored under an idempotency key (only for the same enrollment).
     */
    private function paymentForKey(string $key, Enrollment $enrollment): ?Payment
    {
        $transaction = Transaction::query()->where('idempotency_key', $key)->first();

        if ($transaction === null) {
            return null;
        }

        $payment = Payment::query()->where('transaction_id', $transaction->id)->firstOrFail();

        if ($payment->enrollment_id !== $enrollment->id) {
            throw ValidationException::withMessages(['idempotency_key' => __('This payment key was already used for another student.')]);
        }

        return $payment;
    }

    private function storePayment(Enrollment $enrollment, Cashbox $cashbox, int $amount, ?string $note, ?CarbonInterface $paidAt, ?string $idempotencyKey): Payment
    {
        return DB::transaction(function () use ($enrollment, $cashbox, $amount, $note, $paidAt, $idempotencyKey) {
            $payment = Payment::query()->create([
                'enrollment_id' => $enrollment->id,
                'cashbox_id' => $cashbox->id,
                'amount' => $amount,
                'paid_at' => $paidAt ?? now(),
                'received_by' => auth()->id(),
                'receipt_no' => 'TMP-'.uniqid(),
                'note' => $note,
            ]);
            $payment->update(['receipt_no' => sprintf('%s-%06d', now()->format('ym'), $payment->id)]);

            $transaction = $this->ledger->record(TransactionType::StudentPayment, $amount, [
                [$cashbox, $amount],
                [$enrollment, $amount],
            ], [
                'branch_id' => $cashbox->branch_id,
                'note' => $note,
                'idempotency_key' => $idempotencyKey,
                'meta' => [
                    ...$this->enrollmentMeta($enrollment),
                    'cashbox' => $cashbox->name,
                    'cashbox_id' => $cashbox->id,
                    'method' => $cashbox->method->value,
                    'payment_id' => $payment->id,
                    'receipt_no' => $payment->receipt_no,
                ],
            ]);

            $payment->update(['transaction_id' => $transaction->id]);
            $this->coverage->cover($enrollment);

            return $payment;
        });
    }

    public function reversePayment(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            // Lock the payment so a double click cannot reverse it twice.
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($payment->reversed_at !== null) {
                throw ValidationException::withMessages(['payment' => __('This payment is already cancelled.')]);
            }

            $enrollment = $payment->enrollment->loadMissing('group', 'student');
            $this->lockEnrollment($enrollment);

            // Money that already left the group (refund, move to another group) cannot be taken back again.
            $movedOutLater = LedgerEntry::query()
                ->where('account_type', LedgerAccount::Enrollment)
                ->where('account_id', $enrollment->id)
                ->where('amount', '<', 0)
                ->where('transaction_id', '>', $payment->transaction_id)
                ->whereHas('transaction', fn ($q) => $q->whereIn('type', [TransactionType::Refund, TransactionType::BalanceTransfer]))
                ->exists();

            if ($movedOutLater) {
                throw ValidationException::withMessages(['payment' => __('Money from this payment was already refunded or moved to another group. Cancel that first.')]);
            }

            $this->ledger->record(TransactionType::PaymentReversal, $payment->amount, [
                [$payment->cashbox, -$payment->amount],
                [$enrollment, -$payment->amount],
            ], [
                'branch_id' => $payment->cashbox->branch_id,
                'reverses_id' => $payment->transaction_id,
                'note' => $reason,
                'meta' => [
                    ...$this->enrollmentMeta($enrollment),
                    'cashbox' => $payment->cashbox->name,
                    'cashbox_id' => $payment->cashbox_id,
                    'payment_id' => $payment->id,
                    'receipt_no' => $payment->receipt_no,
                ],
            ]);

            Transaction::query()->whereKey($payment->transaction_id)->update(['reversed_at' => now()]);
            $payment->update(['reversed_at' => now(), 'reversed_by' => auth()->id(), 'reverse_reason' => $reason]);
            $this->coverage->uncoverIfNeeded($enrollment);
        });
    }

    /**
     * Move unused money from one of the student's groups to another.
     */
    public function transferBalance(Enrollment $from, Enrollment $to, int $amount): void
    {
        $this->assertPositive($amount);

        if ($from->student_id !== $to->student_id || $from->is($to)) {
            throw ValidationException::withMessages(['to_enrollment_id' => __('Choose another group of the same student.')]);
        }

        DB::transaction(function () use ($from, $to, $amount) {
            $this->lockEnrollment($from);
            $this->assertFundsAvailable($from, $amount);
            $from->loadMissing('group', 'student');
            $to->loadMissing('group', 'student');

            $this->ledger->record(TransactionType::BalanceTransfer, $amount, [[$from, -$amount], [$to, $amount]], [
                'branch_id' => $to->group->branch_id,
                'meta' => [
                    'student' => $from->student->full_name,
                    'student_id' => $from->student_id,
                    'from_group' => $from->group->name,
                    'from_enrollment_id' => $from->id,
                    'to_group' => $to->group->name,
                    'to_enrollment_id' => $to->id,
                ],
            ]);

            $this->coverage->cover($to);
        });
    }

    public function refund(Enrollment $enrollment, Cashbox $cashbox, int $amount, ?string $note = null): void
    {
        $this->assertPositive($amount);
        $enrollment->loadMissing('group', 'student');
        $this->assertSameBranch($cashbox, $enrollment);

        DB::transaction(function () use ($enrollment, $cashbox, $amount, $note) {
            $this->lockEnrollment($enrollment);
            $this->assertFundsAvailable($enrollment, $amount);

            $this->ledger->record(TransactionType::Refund, $amount, [[$enrollment, -$amount], [$cashbox, -$amount]], [
                'branch_id' => $cashbox->branch_id,
                'note' => $note,
                'meta' => [...$this->enrollmentMeta($enrollment), 'cashbox' => $cashbox->name, 'cashbox_id' => $cashbox->id],
            ]);
        });
    }

    /**
     * Write off the student's debt; the teacher gets nothing for written-off lessons.
     */
    public function writeOffDebt(Enrollment $enrollment, string $reason): int
    {
        return DB::transaction(function () use ($enrollment, $reason) {
            $enrollment->loadMissing('group', 'student');
            $charges = $enrollment->charges()->where('status', ChargeStatus::Active)
                ->whereRaw('amount > covered_amount + written_off_amount')->lockForUpdate()->get();

            $total = 0;

            foreach ($charges as $charge) {
                $uncovered = $charge->uncoveredAmount();
                $charge->update(['written_off_amount' => $charge->written_off_amount + $uncovered]);
                $total += $uncovered;
            }

            if ($total === 0) {
                throw ValidationException::withMessages(['enrollment' => __('The student has no debt in this group.')]);
            }

            $this->ledger->record(TransactionType::DebtWriteoff, $total, [[$enrollment, $total]], [
                'branch_id' => $enrollment->group->branch_id,
                'note' => $reason,
                'meta' => $this->enrollmentMeta($enrollment),
            ]);

            return $total;
        });
    }

    /**
     * Starting balance from the old system: positive = prepaid money, negative = debt.
     */
    public function openingBalance(Enrollment $enrollment, int $amount): void
    {
        if ($amount === 0) {
            return;
        }

        DB::transaction(function () use ($enrollment, $amount) {
            $enrollment->loadMissing('group', 'student');

            if ($amount < 0) {
                $enrollment->charges()->create([
                    'lesson_date' => today(),
                    'month' => today()->format('Y-m'),
                    'amount' => -$amount,
                    'teacher_percent' => 0,
                    'status' => ChargeStatus::Active,
                ]);
            }

            $this->ledger->record(TransactionType::OpeningBalance, abs($amount), [[$enrollment, $amount]], [
                'branch_id' => $enrollment->group->branch_id,
                'meta' => $this->enrollmentMeta($enrollment),
            ]);

            $this->coverage->cover($enrollment);
        });
    }

    public function payTeacher(User $teacher, Cashbox $cashbox, int $amount, ?string $note = null): Transaction
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($teacher, $cashbox, $amount, $note) {
            // Locked read: two payouts at the same time cannot both pass the check.
            $locked = User::query()->whereKey($teacher->getKey())->lockForUpdate()->firstOrFail();

            if ($amount > $locked->balance) {
                throw ValidationException::withMessages(['amount' => __('The amount is more than the teacher balance.')]);
            }

            return $this->ledger->record(TransactionType::TeacherPayout, $amount, [[$teacher, -$amount], [$cashbox, -$amount]], [
                'branch_id' => $cashbox->branch_id,
                'note' => $note,
                'meta' => ['teacher' => $teacher->name, 'teacher_id' => $teacher->id, 'cashbox' => $cashbox->name, 'cashbox_id' => $cashbox->id],
            ]);
        });
    }

    public function expense(Cashbox $cashbox, ExpenseCategory $category, int $amount, ?string $note = null): Transaction
    {
        $this->assertPositive($amount);

        return DB::transaction(fn () => $this->ledger->record(TransactionType::Expense, $amount, [[$cashbox, -$amount]], [
            'branch_id' => $cashbox->branch_id,
            'expense_category_id' => $category->id,
            'note' => $note,
            'meta' => ['category' => $category->name, 'cashbox' => $cashbox->name, 'cashbox_id' => $cashbox->id],
        ]));
    }

    public function withdrawProfit(Cashbox $cashbox, int $amount, bool $confirmedOverFreeProfit, ?string $note = null): Transaction
    {
        $this->assertPositive($amount);
        $freeProfit = $this->freeProfit([$cashbox->branch_id]);
        $isOverFreeProfit = $amount > $freeProfit;

        if ($isOverFreeProfit && ! $confirmedOverFreeProfit) {
            throw ValidationException::withMessages([
                'confirm' => __('The amount is more than the free profit (:amount). This money belongs to teachers and students. Confirm to continue.', ['amount' => number_format($freeProfit, 0, '.', ' ')]),
            ]);
        }

        return DB::transaction(fn () => $this->ledger->record(TransactionType::OwnerWithdrawal, $amount, [[$cashbox, -$amount]], [
            'branch_id' => $cashbox->branch_id,
            'is_flagged' => $isOverFreeProfit,
            'note' => $note,
            'meta' => ['cashbox' => $cashbox->name, 'cashbox_id' => $cashbox->id, 'free_profit' => $freeProfit],
        ]));
    }

    public function transferBetweenCashboxes(Cashbox $from, Cashbox $to, int $amount, ?string $note = null): Transaction
    {
        $this->assertPositive($amount);

        if ($from->is($to)) {
            throw ValidationException::withMessages(['to_cashbox_id' => __('Choose another cashbox.')]);
        }

        return DB::transaction(fn () => $this->ledger->record(TransactionType::CashboxTransfer, $amount, [[$from, -$amount], [$to, $amount]], [
            'branch_id' => $from->branch_id,
            'note' => $note,
            'meta' => ['from_cashbox' => $from->name, 'from_cashbox_id' => $from->id, 'to_cashbox' => $to->name, 'to_cashbox_id' => $to->id],
        ]));
    }

    /**
     * Cash minus money owed to teachers and money students paid in advance.
     *
     * @param  list<int>  $branchIds
     */
    public function freeProfit(array $branchIds): int
    {
        return $this->cashTotal($branchIds) - $this->teacherLiability($branchIds) - $this->prepaidTotal($branchIds);
    }

    /**
     * @param  list<int>  $branchIds
     */
    public function cashTotal(array $branchIds): int
    {
        return (int) Cashbox::query()->whereIn('branch_id', $branchIds)->sum('balance');
    }

    /**
     * Teacher money earned in the branches and not yet paid out.
     *
     * @param  list<int>  $branchIds
     */
    public function teacherLiability(array $branchIds): int
    {
        return (int) LedgerEntry::query()
            ->where('account_type', 'teacher')
            ->whereHas('transaction', fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->sum('amount');
    }

    /**
     * Money students paid in advance (positive group balances).
     *
     * @param  list<int>  $branchIds
     */
    public function prepaidTotal(array $branchIds): int
    {
        return (int) Enrollment::query()
            ->where('balance', '>', 0)
            ->whereHas('group', fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->sum('balance');
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollmentMeta(Enrollment $enrollment): array
    {
        return [
            'student' => $enrollment->student->full_name,
            'student_id' => $enrollment->student_id,
            'group' => $enrollment->group->name,
            'group_id' => $enrollment->group_id,
            'enrollment_id' => $enrollment->id,
        ];
    }

    private function assertPositive(int $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The amount must be greater than zero.')]);
        }
    }

    private function assertSameBranch(Cashbox $cashbox, Enrollment $enrollment): void
    {
        if ($cashbox->branch_id !== $enrollment->group->branch_id) {
            throw ValidationException::withMessages(['cashbox_id' => __('Choose a cashbox of the group branch.')]);
        }
    }

    /**
     * Serialise money operations on one enrollment (row lock until the transaction ends).
     */
    private function lockEnrollment(Enrollment $enrollment): void
    {
        Enrollment::query()->whereKey($enrollment->getKey())->lockForUpdate()->first();
    }

    private function assertFundsAvailable(Enrollment $enrollment, int $amount): void
    {
        if ($amount > $this->coverage->availableFunds($enrollment)) {
            throw ValidationException::withMessages(['amount' => __('The amount is more than the unused balance of the group.')]);
        }
    }
}
