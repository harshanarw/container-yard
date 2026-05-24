<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxCode extends Model
{
    protected $fillable = [
        'code',
        'description',
        'tax1_rate',
        'tax2_rate',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'tax1_rate'  => 'float',
        'tax2_rate'  => 'float',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getTotalRateAttribute(): float
    {
        return $this->tax1_rate + $this->tax2_rate;
    }

    public function isTaxExempt(): bool
    {
        return $this->tax1_rate == 0 && $this->tax2_rate == 0;
    }
}
