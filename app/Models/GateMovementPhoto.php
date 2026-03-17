<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GateMovementPhoto extends Model
{
    protected $fillable = [
        'gate_movement_id',
        'photo_path',
        'movement_type',
        'uploaded_by',
    ];

    public function gateMovement()
    {
        return $this->belongsTo(GateMovement::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
