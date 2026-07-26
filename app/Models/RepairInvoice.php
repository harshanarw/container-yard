<?php

namespace App\Models;

use App\Traits\HasYardJob;
use Illuminate\Database\Eloquent\Model;

class RepairInvoice extends Model
{
    use HasYardJob;

    protected $fillable = [
        'invoice_no', 'billing_mode', 'estimate_id', 'work_order_id', 'yard_job_id', 'container_id', 'container_no',
        'customer_id', 'billing_party_id', 'invoice_date', 'due_date', 'period_basis',
        'billing_period_from', 'billing_period_to', 'bill_categories',
        'currency', 'exchange_rate', 'tax_applicable', 'status',
        'subtotal', 'sscl_total', 'vat_total', 'tax_percentage', 'tax_amount', 'grand_total',
        'amount_paid', 'balance_due', 'notes', 'created_by', 'issued_by', 'issued_at',
        'ird_invoice_no',
    ];

    protected $casts = [
        'invoice_date'  => 'date',
        'due_date'      => 'date',
        'billing_period_from' => 'date',
        'billing_period_to'   => 'date',
        'bill_categories'     => 'array',
        'exchange_rate' => 'decimal:6',
        'tax_applicable' => 'boolean',
        'subtotal'       => 'decimal:2',
        'sscl_total'     => 'decimal:2',
        'vat_total'      => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'grand_total'    => 'decimal:2',
        'amount_paid'   => 'decimal:2',
        'balance_due'   => 'decimal:2',
        'issued_at'     => 'datetime',
    ];

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** Party the receivable is raised against (falls back to the customer). */
    public function billingParty()
    {
        return $this->belongsTo(Customer::class, 'billing_party_id');
    }

    /** The party carrying the receivable — billing party, else the customer. */
    public function billedPartyId(): ?int
    {
        return $this->billing_party_id ?: $this->customer_id;
    }

    /** Periodic (consolidated) invoices spanning many estimates. */
    public function scopePeriodic($query)
    {
        return $query->where('billing_mode', 'periodic');
    }

    public function lines()
    {
        return $this->hasMany(RepairInvoiceLine::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
