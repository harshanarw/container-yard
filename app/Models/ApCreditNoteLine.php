<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApCreditNoteLine extends Model
{
    protected $fillable = [
        'ap_credit_note_id', 'description', 'expense_account_id', 'charge_code_id', 'tax_code_id', 'amount',
        'tax1_rate', 'tax2_rate', 'tax1_amount', 'tax2_amount', 'gross_amount',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'tax1_rate'    => 'decimal:4',
        'tax2_rate'    => 'decimal:4',
        'tax1_amount'  => 'decimal:2',
        'tax2_amount'  => 'decimal:2',
        'gross_amount' => 'decimal:2',
    ];

    public function creditNote()    { return $this->belongsTo(ApCreditNote::class, 'ap_credit_note_id'); }
    public function expenseAccount(){ return $this->belongsTo(Account::class, 'expense_account_id'); }
    public function chargeCode()    { return $this->belongsTo(ChargeCode::class, 'charge_code_id'); }
    public function taxCode()       { return $this->belongsTo(TaxCode::class, 'tax_code_id'); }
}
