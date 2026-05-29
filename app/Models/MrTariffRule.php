<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MrTariffRule extends Model
{
    protected $fillable = [
        'mr_tariff_header_id',
        'component_code_id', 'damage_code_id', 'repair_code_id', 'material_code_id',
        'std_labor_hours', 'labor_rate',
        'material_qty', 'material_rate',
        'ancillary', 'min_charge', 'max_charge',
        'notes',
    ];

    protected $casts = [
        'std_labor_hours' => 'decimal:2',
        'labor_rate'      => 'decimal:2',
        'material_qty'    => 'decimal:3',
        'material_rate'   => 'decimal:2',
        'ancillary'       => 'decimal:2',
        'min_charge'      => 'decimal:2',
        'max_charge'      => 'decimal:2',
    ];

    public function tariffHeader()
    {
        return $this->belongsTo(MrTariffHeader::class, 'mr_tariff_header_id');
    }

    public function componentCode()
    {
        return $this->belongsTo(MrCode::class, 'component_code_id');
    }

    public function damageCode()
    {
        return $this->belongsTo(MrCode::class, 'damage_code_id');
    }

    public function repairCode()
    {
        return $this->belongsTo(MrCode::class, 'repair_code_id');
    }

    public function materialCode()
    {
        return $this->belongsTo(MrCode::class, 'material_code_id');
    }

    /** Compute the total line amount from standard rates. */
    public function computeAmount(): float
    {
        $labor    = (float)$this->std_labor_hours * (float)$this->labor_rate;
        $material = (float)$this->material_qty * (float)$this->material_rate;
        $total    = $labor + $material + (float)$this->ancillary;

        if ($this->min_charge && $total < (float)$this->min_charge) {
            $total = (float)$this->min_charge;
        }
        if ($this->max_charge && $total > (float)$this->max_charge) {
            $total = (float)$this->max_charge;
        }
        return round($total, 2);
    }
}
