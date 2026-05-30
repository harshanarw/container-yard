<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairCategoryMapping extends Model
{
    protected $fillable = [
        'repair_category_id', 'component_code_id', 'repair_type', 'priority', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority'  => 'integer',
    ];

    public function repairCategory()
    {
        return $this->belongsTo(RepairCategory::class);
    }

    public function componentCode()
    {
        return $this->belongsTo(MrCode::class, 'component_code_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('priority');
    }
}
