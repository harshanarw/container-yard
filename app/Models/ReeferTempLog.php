<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReeferTempLog extends Model
{
    protected $fillable = [
        'plug_session_id', 'logged_at',
        'set_temperature', 'return_temperature', 'supply_temperature',
        'humidity_pct', 'notes', 'logged_by',
    ];

    protected $casts = [
        'logged_at'          => 'datetime',
        'set_temperature'    => 'decimal:2',
        'return_temperature' => 'decimal:2',
        'supply_temperature' => 'decimal:2',
        'humidity_pct'       => 'decimal:2',
    ];

    public function plugSession()
    {
        return $this->belongsTo(ReeferPlugSession::class, 'plug_session_id');
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
