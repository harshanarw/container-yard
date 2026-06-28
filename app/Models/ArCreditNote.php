<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArCreditNote extends Model
{
    protected $fillable = [
        'credit_note_no', 'customer_id', 'credit_date', 'currency', 'exchange_rate',
        'reference_invoice_type', 'reference_invoice_id',
        'subtotal', 'tax_amount', 'total_amount', 'base_amount',
        'reason', 'status', 'journal_id', 'posting_error', 'notes',
        'approved_by', 'approved_at', 'created_by',
    ];

    protected $casts = [
        'credit_date'   => 'date',
        'approved_at'   => 'datetime',
        'exchange_rate' => 'decimal:6',
        'subtotal'      => 'decimal:2',
        'tax_amount'    => 'decimal:2',
        'total_amount'  => 'decimal:2',
        'base_amount'   => 'decimal:4',
    ];

    public function customer()    { return $this->belongsTo(Customer::class); }
    public function lines()       { return $this->hasMany(ArCreditNoteLine::class); }
    public function applications(){ return $this->hasMany(ArCreditNoteApplication::class); }
    public function journal()     { return $this->belongsTo(GlJournal::class, 'journal_id'); }
    public function createdBy()   { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy()  { return $this->belongsTo(User::class, 'approved_by'); }

    public function isDraft(): bool     { return $this->status === 'draft'; }
    public function isApproved(): bool  { return $this->status === 'approved'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    /** Credit-note amount already applied to invoices. */
    public function getAppliedTotalAttribute(): float
    {
        return (float) $this->applications->sum('applied_amount');
    }

    /** Unapplied (remaining customer credit) balance. */
    public function getUnappliedAttribute(): float
    {
        return round((float) $this->total_amount - $this->applied_total, 2);
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            'approved'  => 'success',
            'cancelled' => 'danger',
            default     => 'secondary',
        };
    }
}
