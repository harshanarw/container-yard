<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApCreditNoteLine extends Model
{
    protected $fillable = [
        'ap_credit_note_id', 'description', 'expense_account_id', 'charge_code_id', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function creditNote()    { return $this->belongsTo(ApCreditNote::class, 'ap_credit_note_id'); }
    public function expenseAccount(){ return $this->belongsTo(Account::class, 'expense_account_id'); }
    public function chargeCode()    { return $this->belongsTo(ChargeCode::class, 'charge_code_id'); }
}
