<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'code', 'name', 'registration_no', 'tin_number', 'address', 'city',
        'country', 'country_id', 'contact_person', 'phone', 'email', 'website',
        'currency', 'credit_limit', 'payment_terms', 'status', 'tax_exempt',
        'notes', 'created_by',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'tax_exempt'   => 'boolean',
    ];

    public function countryInfo(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function paymentVouchers(): HasMany
    {
        return $this->hasMany(PaymentVoucher::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'active'   => 'bg-success-subtle text-success',
            'pending'  => 'bg-warning-subtle text-warning',
            'inactive' => 'bg-secondary-subtle text-secondary',
            default    => 'bg-light text-muted',
        };
    }
}
