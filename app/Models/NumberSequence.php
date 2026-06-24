<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    protected $fillable = [
        'module_code',
        'label',
        'prefix',
        'use_company_prefix',
        'separator',
        'date_format',
        'seq_padding',
        'reset_period',
        'current_period',
        'last_number',
        'is_system',
    ];

    protected $casts = [
        'use_company_prefix' => 'boolean',
        'is_system'          => 'boolean',
        'seq_padding'        => 'integer',
        'last_number'        => 'integer',
    ];
}
