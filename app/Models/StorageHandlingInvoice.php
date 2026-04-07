<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageHandlingInvoice extends Model
{
    protected $fillable = [
        'invoice_no', 'shipping_line_id', 'invoice_date',
        'billing_period_from', 'billing_period_to',
        'storage_subtotal', 'handling_subtotal', 'subtotal',
        'tax_percentage', 'tax_amount', 'total_amount',
        'status', 'notes', 'sent_at', 'created_by',
    ];

    protected $casts = [
        'invoice_date'        => 'date',
        'billing_period_from' => 'date',
        'billing_period_to'   => 'date',
        'storage_subtotal'    => 'decimal:2',
        'handling_subtotal'   => 'decimal:2',
        'subtotal'            => 'decimal:2',
        'tax_percentage'      => 'decimal:2',
        'tax_amount'          => 'decimal:2',
        'total_amount'        => 'decimal:2',
        'sent_at'             => 'datetime',
    ];

    public function shippingLine()
    {
        return $this->belongsTo(Customer::class, 'shipping_line_id');
    }

    public function lines()
    {
        return $this->hasMany(StorageHandlingInvoiceLine::class, 'invoice_id')->orderBy('container_no');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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
}
