<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Damage extends Model
{
    use HasFactory;

    protected $fillable = [
        'inquiry_id',
        'location_code_id', 'component_code_id', 'damage_code_id',
        'repair_code_id', 'material_code_id', 'responsibility_code_id',
        'location', 'damage_type', 'severity',
        'dimensions', 'dim_length', 'dim_width', 'dim_depth', 'dim_area',
        'quantity', 'cedex_code', 'description',
        'repair_cost', 'repaired',
    ];

    protected $casts = [
        'repaired'    => 'boolean',
        'repair_cost' => 'decimal:2',
        'dim_length'  => 'decimal:2',
        'dim_width'   => 'decimal:2',
        'dim_depth'   => 'decimal:2',
        'dim_area'    => 'decimal:4',
        'quantity'    => 'decimal:2',
    ];

    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function locationCode()
    {
        return $this->belongsTo(MrCode::class, 'location_code_id');
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

    public function responsibilityCode()
    {
        return $this->belongsTo(MrCode::class, 'responsibility_code_id');
    }

    /** Generate CEDEX-compatible code string from the selected codes. */
    public function buildCedexCode(): string
    {
        return implode('/', array_filter([
            optional($this->locationCode)->code,
            optional($this->componentCode)->code,
            optional($this->damageCode)->code,
            optional($this->repairCode)->code,
        ]));
    }
}
