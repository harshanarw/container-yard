<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'name', 'iso2', 'iso3', 'phone_code', 'capital',
        'currency_code', 'currency_name', 'currency_symbol',
        'region', 'subregion', 'flag_emoji', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function defaultForForms(): ?self
    {
        $settings = CompanySetting::current();
        if ($settings?->country_id) {
            return static::find($settings->country_id);
        }
        return null;
    }

    public static function forSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)->orderBy('name')->get();
    }
}
