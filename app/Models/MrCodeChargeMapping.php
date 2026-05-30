<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MrCodeChargeMapping extends Model
{
    protected $fillable = [
        'component_code_id', 'repair_code_id', 'charge_code_id',
        'priority', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority'  => 'integer',
    ];

    public function componentCode()
    {
        return $this->belongsTo(MrCode::class, 'component_code_id');
    }

    public function repairCode()
    {
        return $this->belongsTo(MrCode::class, 'repair_code_id');
    }

    public function chargeCode()
    {
        return $this->belongsTo(ChargeCode::class);
    }

    /**
     * Find the best-matching charge code given a component + repair code pair.
     *
     * Specificity score (higher wins):
     *   component + repair = 3
     *   component only     = 2
     *   repair only        = 1
     * Within the same score, lowest priority number wins.
     */
    public static function resolve(?int $componentCodeId, ?int $repairCodeId): ?self
    {
        return self::with('chargeCode.taxCode')
            ->where('is_active', true)
            ->where(function ($q) use ($componentCodeId) {
                $q->whereNull('component_code_id');
                if ($componentCodeId !== null) {
                    $q->orWhere('component_code_id', $componentCodeId);
                }
            })
            ->where(function ($q) use ($repairCodeId) {
                $q->whereNull('repair_code_id');
                if ($repairCodeId !== null) {
                    $q->orWhere('repair_code_id', $repairCodeId);
                }
            })
            ->orderByRaw('
                (CASE WHEN component_code_id IS NOT NULL THEN 2 ELSE 0 END +
                 CASE WHEN repair_code_id    IS NOT NULL THEN 1 ELSE 0 END) DESC
            ')
            ->orderBy('priority')
            ->first();
    }
}
