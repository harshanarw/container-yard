<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WashingTariff extends Model
{
    protected $fillable = [
        'customer_id', 'wash_scope', 'wash_type', 'container_size',
        'rate', 'min_charge', 'currency',
        'charge_code_id', 'tax_code_id',
        'valid_from', 'valid_to', 'is_active', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'rate'       => 'decimal:2',
        'min_charge' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to'   => 'date',
        'is_active'  => 'boolean',
    ];

    const SCOPES = [
        'internal' => 'Internal Wash',
        'external' => 'External Wash',
    ];

    const TYPES = [
        'standard'   => 'Standard',
        'chemical'   => 'Chemical Wash',
        'steam'      => 'Steam / High-Pressure',
        'food_grade' => 'Food-Grade',
        'degas'      => 'Degas / Decontamination',
    ];

    const SIZES = ['20', '40', '45'];

    // ── Relationships ────────────────────────────────────────────────────────
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function chargeCode()
    {
        return $this->belongsTo(ChargeCode::class);
    }

    public function taxCode()
    {
        return $this->belongsTo(TaxCode::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Rows in their validity window on $date (open-ended dates always match). */
    public function scopeCurrent($query, ?string $date = null)
    {
        $date = $date ?: now()->toDateString();

        return $query
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    public function getScopeLabelAttribute(): string
    {
        return self::SCOPES[$this->wash_scope] ?? ucfirst((string) $this->wash_scope);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->wash_type] ?? ucfirst((string) $this->wash_type);
    }

    public function getSizeLabelAttribute(): string
    {
        return $this->container_size ? $this->container_size . "'" : 'All sizes';
    }

    /**
     * Best-match rate for a wash scope. Prefers a customer-specific row over the
     * default, and an exact container-size row over the all-sizes fallback.
     * Returns the WashingTariff or null when nothing is configured.
     */
    public static function resolve(
        ?int $customerId,
        string $scope,
        ?string $type = 'standard',
        ?string $size = null,
        ?string $date = null
    ): ?self {
        $rows = static::active()->current($date)
            ->where('wash_scope', $scope)
            ->when($type, fn ($q) => $q->where('wash_type', $type))
            ->where(fn ($q) => $q->where('customer_id', $customerId)->orWhereNull('customer_id'))
            ->where(fn ($q) => $q->where('container_size', $size)->orWhereNull('container_size'))
            ->get();

        // Lower rank = better. Customer match (weight 2) outranks size match
        // (weight 1), so a customer-specific all-sizes row beats a default
        // exact-size row.
        return $rows->sortBy(fn ($r) =>
            ($customerId !== null && $r->customer_id === $customerId ? 0 : 2)
            + ($size !== null && $r->container_size === $size ? 0 : 1)
        )->first();
    }
}
