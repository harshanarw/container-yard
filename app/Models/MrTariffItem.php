<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MrTariffItem extends Model
{
    const OPERATION_TYPES = [
        'straight', 'insert', 'section', 'replace',
        'weld', 'remove', 'paint', 'resecure', 'free',
    ];

    const UNIT_TYPES = ['nos', 'lift', 'sqft', 'inches'];

    protected $fillable = [
        'mr_tariff_header_id',
        'tariff_code',
        'operation_type',
        'description',
        'component_code_id',
        'repair_code_id',
        'unit_type',
        'notes',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tariffHeader()
    {
        return $this->belongsTo(MrTariffHeader::class, 'mr_tariff_header_id');
    }

    public function componentCode()
    {
        return $this->belongsTo(MrCode::class, 'component_code_id');
    }

    public function repairCode()
    {
        return $this->belongsTo(MrCode::class, 'repair_code_id');
    }

    public function slabs()
    {
        return $this->hasMany(MrTariffSlab::class, 'mr_tariff_item_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
