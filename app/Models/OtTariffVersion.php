<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An effective-dated set of overtime tariff rates (e.g. ACDO Revised Depot OT
 * effective 01 Apr 2026). New rate circulars become new versions — historical
 * versions are never edited.
 */
class OtTariffVersion extends Model
{
    protected $fillable = [
        'version_code', 'name', 'effective_from', 'effective_to', 'currency',
        'source_reference', 'approval_status', 'active', 'created_by', 'approved_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'active'         => 'boolean',
    ];

    public function rules()
    {
        return $this->hasMany(OtTariffRule::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)->where('approval_status', 'active');
    }
}
