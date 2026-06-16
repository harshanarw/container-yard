<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountMapping extends Model
{
    protected $fillable = [
        'mapping_type', 'source_type', 'source_id',
        'account_id', 'notes', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'charge_revenue'   => 'Charge Code → Revenue Account',
            'charge_expense'   => 'Charge Code → Expense Account',
            'customer_ar'      => 'Customer → AR Control Account',
            'supplier_ap'      => 'Supplier → AP Control Account',
            'tax_output'       => 'Tax Code → Output Tax Payable',
            'tax_input'        => 'Tax Code → Input Tax Receivable',
            'advance_customer' => 'Customer Advance Receipts Account',
            'advance_supplier' => 'Supplier Advance Payments Account',
            'bank_charge'      => 'Bank Charges Account',
            'discount'         => 'Discount Account',
            'write_off'        => 'Write-Off / Bad Debt Account',
            default            => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
