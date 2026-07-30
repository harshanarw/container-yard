<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An effective-dated set of overtime tariff rates (e.g. ACDO Revised Depot OT
 * effective 01 Apr 2026). New rate circulars become new versions — historical
 * versions are never edited.
 */
class OtTariffVersion extends Model
{
    protected $fillable = [
        'version_code', 'name', 'effective_from', 'effective_to', 'currency',
        'source_reference', 'approval_status', 'active', 'created_by', 'approved_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'active'         => 'boolean',
    ];

    const APPROVAL_STATUSES = [
        'draft'    => 'Draft',
        'approved' => 'Approved',
        'active'   => 'Active',
        'retired'  => 'Retired',
    ];

    public function rules()
    {
        return $this->hasMany(OtTariffRule::class);
    }

    public function receipts()
    {
        return $this->hasMany(OtReceipt::class, 'ot_tariff_version_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)->where('approval_status', 'active');
    }

    /**
     * A version already billed against is history: editing its rates would rewrite
     * receipts that were printed and posted. Revisions clone into a new version
     * instead. Retired versions are likewise frozen.
     */
    public function isLocked(): bool
    {
        return $this->approval_status === 'retired' || $this->receipts()->exists();
    }

    public function lockReason(): ?string
    {
        if ($this->approval_status === 'retired') {
            return 'This version is retired. Clone it to make a revision.';
        }
        if ($this->receipts()->exists()) {
            return 'Receipts have already been issued against this version. Clone it to revise the rates.';
        }

        return null;
    }

    public function statusLabel(): string
    {
        return self::APPROVAL_STATUSES[$this->approval_status] ?? ucfirst((string) $this->approval_status);
    }

    /**
     * True when this version is the one the resolver picks for the given date.
     * Hinted on CarbonInterface so a base Carbon\Carbon (e.g. from Carbon::parse)
     * is accepted as readily as an Illuminate\Support\Carbon.
     */
    public function isEffectiveOn(\Carbon\CarbonInterface $date): bool
    {
        return $this->active
            && $this->approval_status === 'active'
            && $this->effective_from->lte($date)
            && ($this->effective_to === null || $this->effective_to->gte($date));
    }
}
