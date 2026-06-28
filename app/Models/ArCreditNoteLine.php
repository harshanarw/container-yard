<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArCreditNoteLine extends Model
{
    protected $fillable = [
        'ar_credit_note_id', 'description', 'revenue_account_id', 'charge_code_id', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function creditNote()    { return $this->belongsTo(ArCreditNote::class, 'ar_credit_note_id'); }
    public function revenueAccount(){ return $this->belongsTo(Account::class, 'revenue_account_id'); }
    public function chargeCode()    { return $this->belongsTo(ChargeCode::class, 'charge_code_id'); }
}
