<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    protected $fillable = [
        'bank_account_id',
        'statement_date',
        'opening_balance',
        'closing_balance',
        'status',
        'reconciled_at',
        'reconciled_by',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'statement_date'  => 'date',
        'opening_balance' => 'decimal:4',
        'closing_balance' => 'decimal:4',
        'reconciled_at'   => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    /** GL cash/bank entries cleared against this reconciliation. */
    public function clearedEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'bank_reconciliation_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
