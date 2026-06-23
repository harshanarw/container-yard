<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReeferElectricityInvoice extends Model
{
    protected $fillable = [
        'invoice_no', 'customer_id', 'invoice_date', 'due_date',
        'billing_period_from', 'billing_period_to',
        'invoice_currency', 'exchange_rate',
        'subtotal', 'sscl_percentage', 'sscl_amount',
        'vat_percentage', 'vat_amount',
        'total_amount', 'total_value',
        'status', 'notes', 'sent_at', 'created_by',
        'ird_invoice_no',
    ];

    protected $casts = [
        'invoice_date'        => 'date',
        'due_date'            => 'date',
        'billing_period_from' => 'date',
        'billing_period_to'   => 'date',
        'exchange_rate'       => 'decimal:4',
        'subtotal'            => 'decimal:2',
        'sscl_percentage'     => 'decimal:2',
        'sscl_amount'         => 'decimal:2',
        'vat_percentage'      => 'decimal:2',
        'vat_amount'          => 'decimal:2',
        'total_amount'        => 'decimal:2',
        'total_value'         => 'decimal:2',
        'sent_at'             => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines()
    {
        return $this->hasMany(ReeferElectricityInvoiceLine::class)
                    ->orderBy('container_no');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'draft'     => 'bg-secondary-subtle text-secondary',
            'issued'    => 'bg-info-subtle text-info',
            'paid'      => 'bg-success-subtle text-success',
            'cancelled' => 'bg-danger-subtle text-danger',
            default     => 'bg-light text-muted',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Generate the next invoice number in sequence (REF-YYYY-NNNNN). */
    public static function nextInvoiceNo(): string
    {
        $year   = now()->year;
        $prefix = "REF-{$year}-";

        $last = static::where('invoice_no', 'like', "{$prefix}%")
                       ->orderByDesc('invoice_no')
                       ->value('invoice_no');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
