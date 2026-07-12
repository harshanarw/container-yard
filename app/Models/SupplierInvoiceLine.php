<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceLine extends Model
{
    protected $fillable = [
        'supplier_invoice_id',
        'yard_job_id',
        'container_id',
        'charge_code_id',
        'tax_code_id',
        'description',
        'expense_account_id',
        'amount',        // net amount (excl. all tax)
        'tax1_rate',
        'tax2_rate',
        'tax1_amount',   // SSCL — embedded in expense cost
        'tax2_amount',   // VAT — recoverable input tax
        'gross_amount',  // amount + tax1_amount + tax2_amount
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'tax1_rate'    => 'decimal:4',
        'tax2_rate'    => 'decimal:4',
        'tax1_amount'  => 'decimal:2',
        'tax2_amount'  => 'decimal:2',
        'gross_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function chargeCode(): BelongsTo
    {
        return $this->belongsTo(ChargeCode::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    // ── Job costing dimension ────────────────────────────────────────────────
    public function yardJob(): BelongsTo
    {
        return $this->belongsTo(YardJob::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }
}
