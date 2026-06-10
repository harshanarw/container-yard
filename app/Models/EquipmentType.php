<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentType extends Model
{
    use HasFactory;

    const VENTILATION_TYPES = [
        'none'           => 'Sealed / None',
        'passive'        => 'Passive',
        'cross'          => 'Cross-Ventilated',
        'mechanical'     => 'Mechanical',
        'reefer'         => 'Reefer',
        'controlled_atm' => 'Controlled Atmosphere',
    ];

    protected $fillable = [
        'eqt_code', 'iso_code', 'size', 'type_code',
        'height', 'description', 'is_active', 'sort_order',
        'ventilation_type', 'vent_count',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'vent_count' => 'integer',
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

    /** True for Reefer (RF) and Reefer High Cube (RH) container types. */
    public function isReefer(): bool
    {
        return in_array($this->type_code, ['RF', 'RH']);
    }

    /** Human-readable ventilation summary for display. */
    public function ventilationLabel(): string
    {
        $label = self::VENTILATION_TYPES[$this->ventilation_type] ?? null;
        if (!$label) {
            return '—';
        }
        return ($this->vent_count > 0)
            ? "{$label} · {$this->vent_count} vents"
            : $label;
    }
}
