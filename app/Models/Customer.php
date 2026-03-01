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
        'rate_20gp', 'rate_40gp', 'rate_40hc', 'free_days', 'status',
        'contract_start', 'contract_end', 'email_notifications', 'auto_invoice',
        'logo', 'notes',
    ];

    protected $casts = [
        'credit_limit'        => 'decimal:2',
        'rate_20gp'           => 'decimal:2',
        'rate_40gp'           => 'decimal:2',
        'rate_40hc'           => 'decimal:2',
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

    // Helpers
    public function getStorageRate(string $size): float
    {
        return match ($size) {
            '20'    => (float) $this->rate_20gp,
            '40'    => (float) $this->rate_40gp,
            '45'    => (float) $this->rate_40hc,
            default => 0.0,
        };
    }
}
