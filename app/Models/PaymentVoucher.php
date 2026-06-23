<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentVoucher extends Model
{
    protected $fillable = [
        'voucher_no',
        'voucher_date',
        'supplier_id',
        'payee_name',
        'bank_account_id',
        'amount',
        'currency',
        'exchange_rate',
        'payment_method',
        'cheque_no',
        'reference_no',
        'narration',
        'expense_account_id',
        'journal_id',
        'status',
        'voided_at',
        'voided_by',
        'created_by',
    ];

    protected $casts = [
        'voucher_date'  => 'date',
        'voided_at'     => 'datetime',
        'amount'        => 'decimal:4',
        'exchange_rate' => 'decimal:6',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function expenseAccount()
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function journal()
    {
        return $this->belongsTo(GlJournal::class, 'journal_id');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            'confirmed' => 'success',
            'voided'    => 'danger',
            default     => 'secondary',
        };
    }

    public static function paymentMethodLabel(string $m): string
    {
        return match ($m) {
            'cash'          => 'Cash',
            'cheque'        => 'Cheque',
            'bank_transfer' => 'Bank Transfer',
            'online'        => 'Online',
            default         => ucfirst($m),
        };
    }
}
