<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'swift_code',
        'bank_code',
        'country_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function countryInfo()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->short_name ? "{$this->name} ({$this->short_name})" : $this->name;
    }
}
