<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArCreditNoteApplication extends Model
{
    protected $fillable = [
        'ar_credit_note_id', 'invoice_type', 'invoice_id', 'applied_amount', 'base_amount',
    ];

    protected $casts = [
        'applied_amount' => 'decimal:2',
        'base_amount'    => 'decimal:4',
    ];

    public function creditNote() { return $this->belongsTo(ArCreditNote::class, 'ar_credit_note_id'); }
}
