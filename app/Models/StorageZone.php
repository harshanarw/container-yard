<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageZone extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'color', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function yardLocations()
    {
        return $this->hasMany(YardLocation::class, 'zone', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function getEmptyCountAttribute(): int
    {
        return $this->yardLocations()->where('status', 'empty')->count();
    }

    public function getOccupiedCountAttribute(): int
    {
        return $this->yardLocations()->where('status', 'occupied')->count();
    }
}
