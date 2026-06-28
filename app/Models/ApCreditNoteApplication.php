<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApCreditNoteApplication extends Model
{
    protected $fillable = [
        'ap_credit_note_id', 'supplier_invoice_id', 'applied_amount', 'base_amount',
    ];

    protected $casts = [
        'applied_amount' => 'decimal:2',
        'base_amount'    => 'decimal:4',
    ];

    public function creditNote() { return $this->belongsTo(ApCreditNote::class, 'ap_credit_note_id'); }
    public function invoice()    { return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id'); }
}
