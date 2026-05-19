<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationAdjustment extends Model
{
    protected $fillable = [
        'container_id', 'container_no', 'zone',
        'from_row', 'from_bay', 'from_tier',
        'to_row',   'to_bay',   'to_tier',
        'notes', 'adjusted_by',
    ];

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function adjustedBy()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function getFromCodeAttribute(): string
    {
        return "{$this->zone}-{$this->from_row}{$this->from_bay}-T{$this->from_tier}";
    }

    public function getToCodeAttribute(): string
    {
        return "{$this->zone}-{$this->to_row}{$this->to_bay}-T{$this->to_tier}";
    }
}
