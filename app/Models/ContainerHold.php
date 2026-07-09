<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerHold extends Model
{
    protected $fillable = [
        'container_id', 'hold_type', 'reason',
        'placed_by', 'placed_at', 'cleared_by', 'cleared_at', 'clear_notes',
    ];

    protected $casts = [
        'placed_at'  => 'datetime',
        'cleared_at' => 'datetime',
    ];

    public const TYPES = [
        'customs'        => 'Customs Hold',
        'damage'         => 'Damage Dispute',
        'stop_release'   => 'Stop Release',
        'survey_pending' => 'Survey Pending',
        'other'          => 'Other',
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('cleared_at');
    }

    public function isActive(): bool
    {
        return $this->cleared_at === null;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->hold_type] ?? ucfirst(str_replace('_', ' ', $this->hold_type));
    }
}
