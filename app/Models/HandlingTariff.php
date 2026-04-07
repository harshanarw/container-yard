<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandlingTariff extends Model
{
    protected $fillable = [
        'shipping_line_id', 'valid_from', 'valid_to', 'is_active', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
        'is_active'  => 'boolean',
    ];

    // Relationships
    public function shippingLine()
    {
        return $this->belongsTo(Customer::class, 'shipping_line_id');
    }

    public function rates()
    {
        return $this->hasMany(HandlingTariffRate::class)->orderBy('container_size');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helpers
    public function getValidityLabelAttribute(): string
    {
        $from = $this->valid_from->format('d M Y');
        $to   = $this->valid_to ? $this->valid_to->format('d M Y') : 'open-ended';
        return "{$from} – {$to}";
    }

    /**
     * Find the active tariff for a shipping line and return the rate for a given container size.
     * Returns ['lift_off_rate', 'lift_on_rate', 'currency'] or null.
     */
    public static function getRatesFor(int $shippingLineId, string $containerSize): ?HandlingTariffRate
    {
        $tariff = static::where('shipping_line_id', $shippingLineId)
            ->where('is_active', true)
            ->where('valid_from', '<=', today())
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', today());
            })
            ->latest('valid_from')
            ->first();

        return $tariff?->rates()->where('container_size', $containerSize)->first();
    }
}
