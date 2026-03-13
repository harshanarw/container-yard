<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorageInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no', 'customer_id', 'invoice_date',
        'billing_period_from', 'billing_period_to',
        'subtotal', 'tax_percentage', 'tax_amount', 'total_amount',
        'status', 'notes', 'sent_at', 'created_by',
    ];

    protected $casts = [
        'invoice_date'        => 'date',
        'billing_period_from' => 'date',
        'billing_period_to'   => 'date',
        'subtotal'            => 'decimal:2',
        'tax_percentage'      => 'decimal:2',
        'tax_amount'          => 'decimal:2',
        'total_amount'        => 'decimal:2',
        'sent_at'             => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function details()
    {
        return $this->hasMany(StorageInvoiceDetail::class)->orderBy('container_no');
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
