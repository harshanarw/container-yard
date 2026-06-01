<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MrTariffSlab extends Model
{
    protected $fillable = [
        'mr_tariff_item_id',
        'slab_label',
        'qty_from',
        'is_additional',
        'labor_hours',
        'material_cost',
        'sort_order',
    ];

    protected $casts = [
        'is_additional' => 'boolean',
        'qty_from'      => 'decimal:3',
        'labor_hours'   => 'decimal:3',
        'material_cost' => 'decimal:2',
        'sort_order'    => 'integer',
    ];

    public function tariffItem()
    {
        return $this->belongsTo(MrTariffItem::class, 'mr_tariff_item_id');
    }
}
