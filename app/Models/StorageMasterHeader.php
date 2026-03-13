<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorageMasterHeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'default_free_days',
        'valid_from',
        'valid_to',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'valid_from'         => 'date',
        'valid_to'           => 'date',
        'is_active'          => 'boolean',
        'default_free_days'  => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function details()
    {
        return $this->hasMany(StorageMasterDetail::class, 'storage_master_header_id')
                    ->with('equipmentType')
                    ->orderBy('id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Find the active tariff for a given customer and equipment type.
     * Returns the storage rate or null if none found.
     */
    public static function getRateFor(int $customerId, int $equipmentTypeId): ?float
    {
        $header = static::where('customer_id', $customerId)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now());
            })
            ->latest('valid_from')
            ->first();

        if (! $header) {
            return null;
        }

        $detail = $header->details()
            ->where('equipment_type_id', $equipmentTypeId)
            ->first();

        return $detail ? (float) $detail->storage_rate : null;
    }

    public function getValidityLabelAttribute(): string
    {
        $from = $this->valid_from->format('d M Y');
        $to   = $this->valid_to ? $this->valid_to->format('d M Y') : 'Open-ended';

        return "{$from} — {$to}";
    }
}
