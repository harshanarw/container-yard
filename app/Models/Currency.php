<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public function countryInfo()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    protected $fillable = [
        'code',
        'name',
        'country',
        'country_id',
        'symbol',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public static function setDefault(self $currency): void
    {
        self::query()->update(['is_default' => false]);
        $currency->update(['is_default' => true]);

        CompanySetting::current()->update(['default_currency_code' => $currency->code]);
        CompanySetting::flushCache();
    }
}
