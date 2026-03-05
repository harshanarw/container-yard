<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'eqt_code', 'iso_code', 'size', 'type_code',
        'height', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Active items in display order. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('eqt_code');
    }

    /** Full label shown in dropdowns: "20GP — 20' General Purpose Container" */
    public function getDropdownLabelAttribute(): string
    {
        return $this->description
            ? "{$this->eqt_code} — {$this->description}"
            : $this->eqt_code;
    }
}
