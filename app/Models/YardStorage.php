<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YardStorage extends Model
{
    use HasFactory;

    protected $table = 'yard_storage';

    protected $fillable = [
        'container_id', 'customer_id', 'gate_in_date', 'gate_out_date',
        'total_days', 'free_days', 'chargeable_days', 'daily_rate', 'qty',
        'subtotal', 'tax_percentage', 'tax_amount', 'total_charge', 'tariff_tier',
    ];

    protected $casts = [
        'gate_in_date'   => 'date',
        'gate_out_date'  => 'date',
        'daily_rate'     => 'decimal:2',
        'subtotal'       => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'total_charge'   => 'decimal:2',
    ];

    // Relationships
    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
