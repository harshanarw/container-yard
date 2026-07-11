<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralInvoice extends Model
{
    protected $fillable = [
        'invoice_no', 'ird_invoice_no', 'invoice_type', 'category',
        'customer_id', 'billing_party_id',
        'invoice_date', 'due_date', 'payment_terms',
        'currency', 'exchange_rate', 'tax_applicable',
        'subtotal', 'sscl_total', 'vat_total', 'tax_percentage', 'tax_amount', 'grand_total',
        'amount_paid', 'balance_due',
        'reference', 'remarks', 'status',
        'created_by', 'issued_by', 'issued_at',
    ];

    protected $casts = [
        'invoice_date'   => 'date',
        'due_date'       => 'date',
        'exchange_rate'  => 'decimal:6',
        'tax_applicable' => 'boolean',
        'subtotal'       => 'decimal:2',
        'sscl_total'     => 'decimal:2',
        'vat_total'      => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'grand_total'    => 'decimal:2',
        'amount_paid'    => 'decimal:2',
        'balance_due'    => 'decimal:2',
        'issued_at'      => 'datetime',
    ];

    const TYPES = [
        'tax_invoice' => 'Tax Invoice',
        'invoice'     => 'Invoice',
        'debit_note'  => 'Debit Note',
    ];

    /** Document title per type (used on the PDF). */
    const TYPE_TITLES = [
        'tax_invoice' => 'TAX INVOICE',
        'invoice'     => 'INVOICE',
        'debit_note'  => 'DEBIT NOTE',
    ];

    /** Classification of the charge (reporting/filtering only — GL flows via charge codes). */
    const CATEGORIES = [
        'overtime'      => 'Overtime / After-Hours',
        'transport'     => 'Transport / Haulage',
        'special'       => 'Special Handling (HAZ / OOG / Heavy Lift)',
        'documentation' => 'Documentation & Admin',
        'survey'        => 'Survey & Inspection',
        'equipment'     => 'Equipment / Genset Hire',
        'penalty'       => 'Penalty / Demurrage / Detention',
        'cleaning'      => 'Cleaning',
        'materials'     => 'Seal / Sundry Materials',
        'recovery'      => 'Damage Recovery / Compensation',
        'other'         => 'Miscellaneous / Other',
    ];

    // ── Relationships ────────────────────────────────────────────────────────
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** Party actually invoiced (AR); falls back to the customer when unset. */
    public function billingParty()
    {
        return $this->belongsTo(Customer::class, 'billing_party_id');
    }

    public function lines()
    {
        return $this->hasMany(GeneralInvoiceLine::class)->orderBy('sort_order');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Alias so the AR framework (ArAllocationService / StatementService), which
     * reads `total_amount` for non-repair types, works without special-casing.
     */
    public function getTotalAmountAttribute()
    {
        return $this->grand_total;
    }

    /** The party carrying the receivable (billing party, else the customer). */
    public function billedPartyId(): ?int
    {
        return $this->billing_party_id ?: $this->customer_id;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->invoice_type] ?? ucwords(str_replace('_', ' ', (string) $this->invoice_type));
    }

    public function getTypeTitleAttribute(): string
    {
        return self::TYPE_TITLES[$this->invoice_type] ?? strtoupper((string) $this->invoice_type);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ($this->category ?: '—');
    }

    public function isTaxDocument(): bool
    {
        return $this->tax_applicable && in_array($this->invoice_type, ['tax_invoice', 'debit_note'], true);
    }
}
