<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YardLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'container_id', 'row', 'bay', 'tier', 'status', 'last_updated_at',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
    ];

    // Relationships
    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    // Helpers
    public function getSlotCodeAttribute(): string
    {
        return "{$this->row}{$this->bay}-T{$this->tier}";
    }

    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }
}
