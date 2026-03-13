<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'type', 'registration_no', 'address', 'city', 'state',
        'country', 'contact_person', 'designation', 'phone_office', 'phone_mobile',
        'fax', 'email', 'website', 'currency', 'credit_limit', 'payment_terms',
        'status',
        'contract_start', 'contract_end', 'email_notifications', 'auto_invoice',
        'logo', 'notes',
    ];

    protected $casts = [
        'credit_limit'        => 'decimal:2',
        'contract_start'      => 'date',
        'contract_end'        => 'date',
        'email_notifications' => 'boolean',
        'auto_invoice'        => 'boolean',
    ];

    // Relationships
    public function containers()
    {
        return $this->hasMany(Container::class);
    }

    public function inquiries()
    {
        return $this->hasMany(Inquiry::class);
    }

    public function estimates()
    {
        return $this->hasMany(Estimate::class);
    }

    public function gateMovements()
    {
        return $this->hasMany(GateMovement::class);
    }

    public function yardStorage()
    {
        return $this->hasMany(YardStorage::class);
    }

    // Storage tariff for this customer (active, currently valid)
    public function activeTariff()
    {
        return $this->hasOne(StorageMasterHeader::class)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now());
            })
            ->latestOfMany('valid_from');
    }
}
