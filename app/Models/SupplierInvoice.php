<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierInvoice extends Model
{
    protected $fillable = [
        'invoice_no', 'supplier_invoice_no', 'customer_id', 'invoice_date', 'due_date',
        'currency', 'exchange_rate', 'subtotal', 'tax_amount', 'sscl_amount', 'vat_amount', 'total_amount',
        'status', 'journal_id', 'posting_error', 'notes',
        'approved_by', 'approved_at', 'created_by',
    ];

    protected $casts = [
        'invoice_date'  => 'date',
        'due_date'      => 'date',
        'approved_at'   => 'datetime',
        'exchange_rate' => 'decimal:6',
        'subtotal'      => 'decimal:2',
        'tax_amount'    => 'decimal:2',
        'sscl_amount'   => 'decimal:2',
        'vat_amount'    => 'decimal:2',
        'total_amount'  => 'decimal:2',
    ];

    /**
     * The Contact/Party we owe (unified customers master). Named supplier() for
     * readability on the AP side, but it resolves to a Customer — the same
     * external party that may also be an AR debtor.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** Semantic alias — the underlying record is a unified Contact. */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(GlJournal::class, 'journal_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'partially_paid', 'paid']);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** True once a posted GL journal is linked. */
    public function isPosted(): bool
    {
        return $this->journal_id !== null && $this->journal && $this->journal->isPosted();
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'draft'          => 'bg-secondary-subtle text-secondary',
            'approved'       => 'bg-primary-subtle text-primary',
            'partially_paid' => 'bg-info-subtle text-info',
            'paid'           => 'bg-success-subtle text-success',
            'cancelled'      => 'bg-danger-subtle text-danger',
            default          => 'bg-light text-muted',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }
}
