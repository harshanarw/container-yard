<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    use HasFactory;

    protected $fillable = [
        'estimate_no', 'inquiry_id', 'container_id', 'container_no', 'customer_id',
        'size', 'type_code', 'estimate_date', 'valid_until', 'currency', 'priority',
        'status', 'scope_of_work', 'terms', 'subtotal', 'tax_percentage', 'tax_amount',
        'grand_total', 'send_to_email', 'send_cc_email', 'email_message', 'attach_pdf',
        'attach_photos', 'created_by', 'approved_by', 'approved_date', 'rejected_reason',
        'sent_at',
    ];

    protected $casts = [
        'estimate_date' => 'date',
        'valid_until'   => 'date',
        'subtotal'      => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount'    => 'decimal:2',
        'grand_total'   => 'decimal:2',
        'attach_pdf'    => 'boolean',
        'attach_photos' => 'boolean',
        'approved_date' => 'datetime',
        'sent_at'       => 'datetime',
    ];

    // Relationships
    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems()
    {
        return $this->hasMany(EstimateLineItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
