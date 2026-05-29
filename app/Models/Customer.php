<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CustomerType;

class Customer extends Model
{
    use HasFactory;

    public function countryInfo()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function stateInfo()
    {
        return $this->belongsTo(CountryState::class, 'state_id');
    }

    public function districtInfo()
    {
        return $this->belongsTo(CountryState::class, 'district_id');
    }

    protected $fillable = [
        'code', 'name', 'registration_no', 'address', 'city', 'state',
        'country', 'country_id', 'state_id', 'district_id', 'contact_person', 'designation', 'phone_office', 'phone_mobile',
        'fax', 'email', 'website', 'currency', 'credit_limit', 'payment_terms',
        'status',
        'contract_start', 'contract_end', 'email_notifications', 'auto_invoice',
        'tax_exempt',
        'local_agent_id', 'billing_party_id',
        'logo', 'notes',
    ];

    protected $casts = [
        'credit_limit'        => 'decimal:2',
        'contract_start'      => 'date',
        'contract_end'        => 'date',
        'email_notifications' => 'boolean',
        'auto_invoice'        => 'boolean',
        'tax_exempt'          => 'boolean',
    ];

    public function types()
    {
        return $this->belongsToMany(CustomerType::class)->orderBy('sort_order');
    }

    public function localAgent()
    {
        return $this->belongsTo(Customer::class, 'local_agent_id');
    }

    public function billingParty()
    {
        return $this->belongsTo(Customer::class, 'billing_party_id');
    }

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
