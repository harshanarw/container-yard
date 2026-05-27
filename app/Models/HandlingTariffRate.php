<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandlingTariffRate extends Model
{
    protected $fillable = [
        'handling_tariff_id', 'container_size', 'cargo_status',
        'lift_off_rate', 'lift_on_rate', 'currency',
        'charge_code_id',
    ];

    protected $casts = [
        'lift_off_rate' => 'decimal:2',
        'lift_on_rate'  => 'decimal:2',
    ];

    public function tariff()
    {
        return $this->belongsTo(HandlingTariff::class, 'handling_tariff_id');
    }

    public function chargeCode()
    {
        return $this->belongsTo(ChargeCode::class);
    }

    public function getSizeLabelAttribute(): string
    {
        return $this->container_size . "' Container";
    }
}
