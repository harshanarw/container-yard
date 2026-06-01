<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MrTariffCustomerRate extends Model
{
    protected $fillable = [
        'customer_id',
        'rate_code',
        'rate_per_hour',
    ];

    protected $casts = [
        'rate_per_hour' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
