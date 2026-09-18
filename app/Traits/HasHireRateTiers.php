<?php

namespace App\Traits;

use App\Models\CompanySetting;
use App\Models\HireRateTier;
use App\Services\Billing\HireTierPricing;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A hire agreement that is priced in tiers.
 *
 * Shared by both directions deliberately. What the shipping line charges the
 * yard ({@see \App\Models\LessorOnHire}, AP) and what the yard charges its
 * customer ({@see \App\Models\ContainerHire}, AR) have the same shape, so the
 * margin is one `priceFor()` minus the other. Two separate implementations
 * would eventually price the same 37 days two different ways, and the margin
 * would be the difference between two bugs.
 */
trait HasHireRateTiers
{
    public function rateTiers(): MorphMany
    {
        return $this->morphMany(HireRateTier::class, 'hireable')->orderBy('sequence');
    }

    /**
     * What this agreement charges for a given number of days.
     *
     * Days in, money out. Deliberately takes a day count rather than two dates:
     * *which* days are being billed is a separate question — an interim bill
     * covers part of a hire, and {@see \App\Services\Billing\DateWindow} and the
     * prior-billing check decide that. Mixing the two would make this untestable
     * as arithmetic.
     *
     * @return array{segments: array, total: float, priced_days: int, unpriced_days: int, warnings: array}
     */
    public function priceFor(int $days): array
    {
        return HireTierPricing::price(
            $days,
            $this->rateTiers->map->toPricingArray()->all(),
            $this->partial_tier_rule ?? HireTierPricing::PARTIAL_DAILY_FALLBACK,
            static::hireMonthDays(),
        );
    }

    /** The currency this agreement is written in. */
    public function hireCurrency(): string
    {
        return $this->hire_currency ?: CompanySetting::baseCurrency();
    }

    /** True once the agreement can actually produce a bill. */
    public function hasRates(): bool
    {
        return $this->rateTiers()->exists();
    }

    /**
     * Days in a "month" for a monthly tier.
     *
     * A setting, not a constant: 30 days flat is the common convention and the
     * one the worked example implies, but a calendar month from the 15th runs
     * 31 days and some lessors count it that way.
     */
    public static function hireMonthDays(): int
    {
        return (int) (CompanySetting::current()->hire_month_days ?? 30) ?: 30;
    }
}
