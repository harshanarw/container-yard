<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GateMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'container_id', 'container_no', 'customer_id', 'movement_type', 'size',
        'container_type', 'location_row', 'location_bay', 'location_tier',
        'condition', 'cargo_status', 'seal_no', 'vehicle_plate', 'driver_name',
        'driver_ic', 'release_order', 'gate_in_time', 'gate_out_time',
        'movement_status', 'remarks', 'created_by',
    ];

    protected $casts = [
        'gate_in_time'  => 'datetime',
        'gate_out_time' => 'datetime',
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
