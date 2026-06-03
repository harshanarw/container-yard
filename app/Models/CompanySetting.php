<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CompanySetting extends Model
{
    public function countryInfo()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    protected $fillable = [
        'company_name',
        'company_prefix',
        'tagline',
        'address',
        'city',
        'country',
        'country_id',
        'telephone',
        'email',
        'website',
        'vat_number',
        'tin_number',
        'default_currency_code',
        'logo_path',
        'icon_path',
        'product_icon_path',
        'tax1_label',
        'tax2_label',
        'software_provider',
        // System Settings
        'yard_capacity',
        'free_storage_days',
        'timezone',
        'prefix_invoice',
        'prefix_sh_invoice',
        'prefix_survey',
        'prefix_estimate',
        'prefix_gate_in',
        'prefix_gate_out',
        'default_tax_rate',
        'surcharge_overtime',
        'surcharge_night',
        'enable_digital_approvals',
        'mr_dimension_uom',
    ];

    protected $casts = [
        'enable_digital_approvals' => 'boolean',
    ];

    public static function current(): static
    {
        return Cache::remember('company_settings', 3600, fn () => static::firstOrCreate([], ['company_name' => 'Container Yard Management']));
    }

    public static function flushCache(): void
    {
        Cache::forget('company_settings');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon_path ? Storage::url($this->icon_path) : null;
    }

    public function getProductIconUrlAttribute(): ?string
    {
        return $this->product_icon_path ? Storage::url($this->product_icon_path) : null;
    }
}
