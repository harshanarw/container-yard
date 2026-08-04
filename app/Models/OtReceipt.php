<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An overtime receipt: billing authorization for containers under one BL to be
 * gated in during a selected OT service window (A short / B extended).
 */
class OtReceipt extends Model
{
    protected $fillable = [
        'receipt_no', 'bl_number', 'customer_id', 'ot_tariff_version_id', 'ot_tariff_rule_id',
        'operational_date', 'valid_from', 'valid_to',
        'receipt_amount', 'tax_amount', 'total_amount', 'currency',
        'expected_container_count', 'used_container_count',
        'status', 'extension_of_receipt_id', 'billing_mode',
        'bank_account_id', 'payment_method', 'journal_id',
        'remarks', 'created_by', 'approved_by', 'paid_at',
    ];

    protected $casts = [
        'operational_date'         => 'date',
        'valid_from'               => 'datetime',
        'valid_to'                 => 'datetime',
        'paid_at'                  => 'datetime',
        'receipt_amount'           => 'decimal:2',
        'tax_amount'               => 'decimal:2',
        'total_amount'             => 'decimal:2',
        'expected_container_count' => 'integer',
        'used_container_count'     => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function version()
    {
        return $this->belongsTo(OtTariffVersion::class, 'ot_tariff_version_id');
    }

    public function rule()
    {
        return $this->belongsTo(OtTariffRule::class, 'ot_tariff_rule_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function extensionOf()
    {
        return $this->belongsTo(OtReceipt::class, 'extension_of_receipt_id');
    }

    public function journal()
    {
        return $this->belongsTo(\App\Models\GlJournal::class, 'journal_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function remainingCount(): int
    {
        return max(0, (int) $this->expected_container_count - (int) $this->used_container_count);
    }

    /** True when the receipt may be used for a gate-in at $at (default now). */
    public function isUsable(?Carbon $at = null): bool
    {
        $at ??= now();

        return in_array($this->status, ['paid', 'partially_used'], true)
            && $this->remainingCount() > 0
            && $at->gte($this->valid_from) && $at->lte($this->valid_to);
    }

    public function scopeForBl($query, string $blNumber)
    {
        return $query->where('bl_number', $blNumber);
    }
}
