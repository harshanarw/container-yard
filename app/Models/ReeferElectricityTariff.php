<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReeferElectricityTariff extends Model
{
    /** Service types: PTI = short-term hourly; long_term = daily electricity. */
    const SERVICE_TYPES = [
        'pti'       => 'Short-Term PTI',
        'long_term' => 'Long-Term Electricity',
    ];

    /** Conventional billing basis per service type (UI default, still editable). */
    const DEFAULT_BILLING_MODE = [
        'pti'       => 'hourly',
        'long_term' => 'daily',
    ];

    protected $fillable = [
        'customer_id', 'tariff_name', 'service_type', 'billing_mode', 'currency',
        'charge_code_id', 'hourly_rate', 'daily_rate', 'free_hours', 'free_days',
        'minimum_charge', 'valid_from', 'valid_to', 'is_active',
        'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'valid_from'      => 'date',
        'valid_to'        => 'date',
        'is_active'       => 'boolean',
        'hourly_rate'     => 'decimal:2',
        'daily_rate'      => 'decimal:2',
        'minimum_charge'  => 'decimal:2',
        'free_hours'      => 'integer',
        'free_days'       => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function chargeCode()
    {
        return $this->belongsTo(ChargeCode::class, 'charge_code_id');
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
     * Resolve the active reefer tariff for a customer on a given date.
     * Falls back to the default tariff (customer_id IS NULL) when no
     * customer-specific tariff is found.
     */
    public static function resolveFor(int $customerId, ?string $date = null): ?static
    {
        $date = $date ?? today()->toDateString();

        $base = static::where('is_active', true)
            ->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            });

        // Customer-specific first
        $tariff = (clone $base)
            ->where('customer_id', $customerId)
            ->latest('valid_from')
            ->first();

        if ($tariff) {
            return $tariff;
        }

        // Default (customer_id IS NULL)
        return (clone $base)
            ->whereNull('customer_id')
            ->latest('valid_from')
            ->first();
    }

    /**
     * Resolve the active reefer tariff for a customer + service type on a date.
     * Customer-specific row wins; falls back to the default (customer_id IS NULL)
     * row for that same service type. Eager-loads the charge code + tax code so
     * billing can map the correct charge/tax per bill type.
     */
    public static function resolveForType(int $customerId, string $serviceType, ?string $date = null): ?static
    {
        $date = $date ?? today()->toDateString();

        $base = static::with('chargeCode.taxCode')
            ->where('is_active', true)
            ->where('service_type', $serviceType)
            ->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            });

        $tariff = (clone $base)
            ->where('customer_id', $customerId)
            ->latest('valid_from')
            ->first();

        if ($tariff) {
            return $tariff;
        }

        return (clone $base)
            ->whereNull('customer_id')
            ->latest('valid_from')
            ->first();
    }

    public function getServiceTypeLabelAttribute(): string
    {
        return self::SERVICE_TYPES[$this->service_type] ?? ucfirst((string) $this->service_type);
    }

    public function getValidityLabelAttribute(): string
    {
        $from = $this->valid_from->format('d M Y');
        $to   = $this->valid_to ? $this->valid_to->format('d M Y') : 'Open-ended';
        return "{$from} — {$to}";
    }

    public function getRateLabelAttribute(): string
    {
        if ($this->billing_mode === 'hourly') {
            $rate = number_format((float) $this->hourly_rate, 2);
            $free = $this->free_hours > 0 ? " ({$this->free_hours} free hrs)" : '';
            return "{$this->currency} {$rate}/hr{$free}";
        }
        $rate = number_format((float) $this->daily_rate, 2);
        $free = $this->free_days > 0 ? " ({$this->free_days} free days)" : '';
        return "{$this->currency} {$rate}/day{$free}";
    }
}
