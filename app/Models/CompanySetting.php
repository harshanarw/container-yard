<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name',
        'tagline',
        'address',
        'city',
        'country',
        'telephone',
        'email',
        'website',
        'vat_number',
        'tin_number',
        'logo_path',
        'icon_path',
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
}
