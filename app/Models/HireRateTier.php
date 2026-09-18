<?php

namespace App\Models;

use App\Services\Billing\HireTierPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One step of a tiered rental agreement.
 *
 * "The first 30 days at the monthly rate, daily thereafter" is two of these.
 * Tiers are consumed in `sequence` order and measured in **elapsed duration**
 * from the on-hire date, not against the calendar — which is what lets an
 * agreement be written before anyone knows when the box comes back.
 *
 * `max_units` null means "everything left", so the last tier is normally
 * open-ended. Belongs to either direction: {@see LessorOnHire} (the yard pays)
 * or {@see ContainerHire} (the yard is paid).
 *
 * The arithmetic lives in {@see HireTierPricing} and takes plain numbers, so it
 * is testable without a database.
 */
class HireRateTier extends Model
{
    protected $fillable = [
        'hireable_type', 'hireable_id',
        'sequence', 'unit', 'rate', 'max_units',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'sequence'  => 'integer',
        'rate'      => 'decimal:2',
        'max_units' => 'integer',
    ];

    public function hireable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isMonthly(): bool
    {
        return $this->unit === HireTierPricing::UNIT_MONTHLY;
    }

    /** True when this tier absorbs whatever is left of the hire. */
    public function isOpenEnded(): bool
    {
        return $this->max_units === null;
    }

    /**
     * How this tier reads on an agreement: "1 month @ 30,000.00" or
     * "Daily @ 1,200.00".
     */
    public function label(): string
    {
        $rate = number_format((float) $this->rate, 2);

        if (! $this->isMonthly()) {
            return $this->isOpenEnded()
                ? "Daily @ {$rate}"
                : "Up to {$this->max_units} day(s) @ {$rate}";
        }

        return $this->isOpenEnded()
            ? "Monthly @ {$rate}"
            : "{$this->max_units} month(s) @ {$rate}";
    }

    /** The shape {@see HireTierPricing::price()} expects. */
    public function toPricingArray(): array
    {
        return [
            'unit'      => $this->unit,
            'rate'      => (float) $this->rate,
            'max_units' => $this->max_units,
        ];
    }
}
