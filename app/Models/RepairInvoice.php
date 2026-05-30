<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairInvoice extends Model
{
    protected $fillable = [
        'invoice_no', 'estimate_id', 'work_order_id', 'container_id', 'container_no',
        'customer_id', 'invoice_date', 'due_date', 'currency', 'status',
        'subtotal', 'sscl_total', 'vat_total', 'tax_percentage', 'tax_amount', 'grand_total',
        'amount_paid', 'balance_due', 'notes', 'created_by', 'issued_by', 'issued_at',
    ];

    protected $casts = [
        'invoice_date'  => 'date',
        'due_date'      => 'date',
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
