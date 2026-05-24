<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargeCode extends Model
{
    protected $fillable = ['code', 'description', 'tax_code_id', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function taxCode()
    {
        return $this->belongsTo(TaxCode::class);
    }
}
