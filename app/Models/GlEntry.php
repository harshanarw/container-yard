<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlEntry extends Model
{
    protected $fillable = [
        'journal_id',
        'account_id',
        'yard_job_id',
        'container_id',
        'debit',
        'credit',
        'narration',
        // Multi-currency (base debit/credit stay authoritative; these are additive)
        'currency',
        'exchange_rate',
        'txn_debit',
        'txn_credit',
        'group_currency',
        'group_debit',
        'group_credit',
        // Bank reconciliation
        'bank_reconciliation_id',
        'cleared_at',
    ];

    protected $casts = [
        'debit'         => 'decimal:4',
        'credit'        => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'txn_debit'     => 'decimal:4',
        'txn_credit'    => 'decimal:4',
        'group_debit'   => 'decimal:4',
        'group_credit'  => 'decimal:4',
        'cleared_at'    => 'datetime',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(GlJournal::class, 'journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    // ── Job costing dimension (propagated from the source document at posting) ─
    public function yardJob(): BelongsTo
    {
        return $this->belongsTo(YardJob::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }
}
