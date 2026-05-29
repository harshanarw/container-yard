<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MrCode extends Model
{
    protected $table = 'mr_codes';

    protected $fillable = ['type', 'code', 'name', 'description', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    // Type constants
    const TYPE_LOCATION       = 'location';
    const TYPE_COMPONENT      = 'component';
    const TYPE_DAMAGE         = 'damage';
    const TYPE_REPAIR         = 'repair';
    const TYPE_MATERIAL       = 'material';
    const TYPE_RESPONSIBILITY = 'responsibility';

    const TYPES = [
        self::TYPE_LOCATION       => 'Location',
        self::TYPE_COMPONENT      => 'Component',
        self::TYPE_DAMAGE         => 'Damage',
        self::TYPE_REPAIR         => 'Repair',
        self::TYPE_MATERIAL       => 'Material',
        self::TYPE_RESPONSIBILITY => 'Responsibility',
    ];

    public static function validTypes(): array
    {
        return array_keys(self::TYPES);
    }

    // Scopes
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
