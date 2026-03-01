<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimateLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'estimate_id', 'component', 'repair_type', 'qty', 'unit_price',
        'tax_percentage', 'line_amount',
    ];

    protected $casts = [
        'qty'            => 'decimal:2',
        'unit_price'     => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'line_amount'    => 'decimal:2',
    ];

    // Relationships
    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }
}
