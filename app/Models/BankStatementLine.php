<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'bank_account_id',
        'bank_reconciliation_id',
        'txn_date',
        'description',
        'reference',
        'deposit',
        'withdrawal',
        'balance',
        'matched_gl_entry_id',
        'status',
        'source',
        'row_hash',
        'created_by',
    ];

    protected $casts = [
        'txn_date'   => 'date',
        'deposit'    => 'decimal:4',
        'withdrawal' => 'decimal:4',
        'balance'    => 'decimal:4',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function matchedEntry(): BelongsTo
    {
        return $this->belongsTo(GlEntry::class, 'matched_gl_entry_id');
    }

    /** Signed amount from the bank's perspective: + into the account, − out of it. */
    public function getSignedAmountAttribute(): float
    {
        return (float) $this->deposit - (float) $this->withdrawal;
    }

    public function isMatched(): bool
    {
        return $this->status === 'matched';
    }
}
