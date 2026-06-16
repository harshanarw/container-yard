<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = [
        'receipt_no',
        'receipt_date',
        'customer_id',
        'bank_account_id',
        'amount',
        'currency',
        'exchange_rate',
        'payment_method',
        'cheque_no',
        'reference_no',
        'narration',
        'journal_id',
        'status',
        'voided_at',
        'voided_by',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'voided_at'    => 'datetime',
        'amount'       => 'decimal:4',
        'exchange_rate' => 'decimal:6',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
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

    public function allocations()
    {
        return $this->hasMany(ReceiptAllocation::class);
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
