<?php

namespace App\Models;

use App\Enums\LedgerAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transaction_id', 'account_type', 'account_id', 'amount', 'balance_after', 'occurred_at'])]
class LedgerEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => LedgerAccount::class,
            'occurred_at' => 'datetime',
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
