<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairCategory extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'color', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function mappings()
    {
        return $this->hasMany(RepairCategoryMapping::class);
    }

    public function estimateLineItems()
    {
        return $this->hasMany(EstimateLineItem::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function badgeClass(): string
    {
        return 'bg-' . ($this->color ?: 'secondary');
    }
}
