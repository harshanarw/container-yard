<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorageMasterDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'storage_master_header_id',
        'equipment_type_id',
        'cargo_status',
        'reefer_mode',
        'storage_rate',
        'currency',
        'charge_code_id',
    ];

    protected $casts = [
        'storage_rate' => 'decimal:2',
    ];

    /**
     * The rate row for one container's circumstances.
     *
     * Three steps, and each exists for a state the live data is actually in:
     *
     * 1. **Exact match** on the movement's reefer mode.
     * 2. **The row that leaves it null** — "any mode". This is what lets a
     *    tariff nobody has migrated keep pricing unchanged, rather than every
     *    storage line silently costing zero the day the column landed.
     * 3. **`operating`, when the movement had no mode recorded.** A reefer
     *    gated in before `gate_movements.reefer_mode` existed carries null, and
     *    on a tariff whose rows have all been made explicit there is no null row
     *    left to fall back to. Without this step every one of those containers
     *    would resolve to no rate and bill at zero — a silent revenue hole
     *    affecting exactly the boxes that were in the yard longest.
     *
     * Step 3 is the same rule `GateMovement::isOperatingReefer()` states: a
     * reefer with nothing recorded was running, because that is how every one of
     * them behaved when it was written.
     *
     * A dry container passes `null` and matches its own null row at step 2. It
     * never reaches step 3, because the backfill leaves dry rows alone and there
     * is no `operating` row for a dry equipment type to find.
     *
     * @param  \Illuminate\Support\Collection<int,self>  $details  a header's rows, already loaded
     */
    public static function resolve($details, ?int $equipmentTypeId, ?string $cargoStatus, ?string $reeferMode): ?self
    {
        $forContainer = $details
            ->where('equipment_type_id', $equipmentTypeId)
            ->where('cargo_status', $cargoStatus);

        return $forContainer->firstWhere('reefer_mode', $reeferMode)
            ?? $forContainer->firstWhere('reefer_mode', null)
            ?? ($reeferMode === null ? $forContainer->firstWhere('reefer_mode', 'operating') : null);
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function header()
    {
        return $this->belongsTo(StorageMasterHeader::class, 'storage_master_header_id');
    }

    public function equipmentType()
    {
        return $this->belongsTo(EquipmentType::class);
    }

    public function chargeCode()
    {
        return $this->belongsTo(ChargeCode::class);
    }
}
