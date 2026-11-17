<?php

namespace App\Services;

use App\Enums\LedgerAccount;
use App\Enums\TransactionType;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Records money movements as a transaction with one ledger entry per affected account.
 *
 * Every balance change in the system goes through this class, so balance histories
 * of cashboxes, teachers and students are always complete. Callers must run inside
 * a database transaction.
 */
class Ledger
{
    /**
     * @param  list<array{0: Cashbox|User|Enrollment, 1: int}>  $movements  account and signed amount
     * @param  array<string, mixed>  $attributes  extra transaction attributes (branch_id, meta, note …)
     */
    public function record(TransactionType $type, int $amount, array $movements, array $attributes = []): Transaction
    {
        $occurredAt = isset($attributes['occurred_at']) ? Carbon::parse($attributes['occurred_at']) : now();

        $transaction = Transaction::query()->create([
            ...$attributes,
            'type' => $type,
            'amount' => $amount,
            'occurred_at' => $occurredAt,
            'created_by' => $attributes['created_by'] ?? auth()->id(),
        ]);

        foreach ($movements as [$account, $delta]) {
            if ($delta === 0) {
                continue;
            }

            /** @var Cashbox|User|Enrollment $locked */
            $locked = $account->newQuery()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $newBalance = $locked->balance + $delta;

            if ($locked instanceof Cashbox && $newBalance < 0) {
                throw ValidationException::withMessages([
                    'amount' => __('Not enough money in the cashbox :name.', ['name' => $locked->name]),
                ]);
            }

            $locked->newQuery()->whereKey($locked->getKey())->update(['balance' => $newBalance]);
            $account->setAttribute('balance', $newBalance);
            $account->syncOriginalAttribute('balance');

            LedgerEntry::query()->create([
                'transaction_id' => $transaction->id,
                'account_type' => $this->accountType($account),
                'account_id' => $account->getKey(),
                'amount' => $delta,
                'balance_after' => $newBalance,
                'occurred_at' => $occurredAt,
            ]);
        }

        return $transaction;
    }

    private function accountType(Model $account): LedgerAccount
    {
        return match (true) {
            $account instanceof Cashbox => LedgerAccount::Cashbox,
            $account instanceof User => LedgerAccount::Teacher,
            $account instanceof Enrollment => LedgerAccount::Enrollment,
            default => throw new \InvalidArgumentException('Unsupported ledger account '.$account::class),
        };
    }
}
