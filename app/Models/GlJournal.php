<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlJournal extends Model
{
    protected $fillable = [
        'journal_no',
        'journal_date',
        'financial_year_id',
        'period_id',
        'journal_type',
        'reference_type',
        'reference_id',
        'narration',
        'total_debit',
        'total_credit',
        'status',
        'posted_at',
        'posted_by',
        'voided_at',
        'voided_by',
        'created_by',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'posted_at'    => 'datetime',
        'voided_at'    => 'datetime',
        'total_debit'  => 'decimal:4',
        'total_credit' => 'decimal:4',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'journal_id');
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            'draft'  => 'secondary',
            'posted' => 'success',
            'voided' => 'danger',
            default  => 'secondary',
        };
    }

    public static function typeBadge(string $type): string
    {
        return match ($type) {
            'invoice'    => 'primary',
            'receipt'    => 'success',
            'payment'    => 'warning',
            'journal'    => 'info',
            'adjustment' => 'info',
            'opening'    => 'info',
            default      => 'secondary',
        };
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
