<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class YardJobType extends Model
{
    protected $fillable = [
        'job_type_code',
        'type_short_code',
        'job_type_name',
        'movement_direction',
        'description',
        'is_active',
        'sort_order',
        'handling_applicable',
        'survey_applicable',
        'estimate_applicable',
        'repair_applicable',
        'storage_applicable',
        'wash_applicable',
        'reefer_applicable',
        'customs_applicable',
        'cargo_transfer_applicable',
        'booking_applicable',
        'approval_required',
        'damage_capture_required',
        'default_next_status',
        'remarks',
        'is_system',
    ];

    protected $casts = [
        'is_active'                 => 'boolean',
        'sort_order'                => 'integer',
        'handling_applicable'       => 'boolean',
        'survey_applicable'         => 'boolean',
        'estimate_applicable'       => 'boolean',
        'repair_applicable'         => 'boolean',
        'storage_applicable'        => 'boolean',
        'wash_applicable'           => 'boolean',
        'reefer_applicable'         => 'boolean',
        'customs_applicable'        => 'boolean',
        'cargo_transfer_applicable' => 'boolean',
        'booking_applicable'        => 'boolean',
        'approval_required'         => 'boolean',
        'damage_capture_required'   => 'boolean',
        'is_system'                 => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForGateIn(Builder $query): Builder
    {
        return $query->where('movement_direction', 'gate_in');
    }

    public function scopeForGateOut(Builder $query): Builder
    {
        return $query->where('movement_direction', 'gate_out');
    }

    /**
     * Job types that are never a gate purpose.
     *
     * A lease-in and a re-let are agreements, not movements: the yard's custody
     * of the box changes, the box does not. Offering them in a gate dropdown
     * invites an operator to record an arrival that never happened, which is the
     * phantom movement migration 000316 was written to eliminate. They are
     * opened by their own services, which also handle the storage split and the
     * parent job — neither of which a gate form knows to do.
     *
     * `forGateIn()` and `forGateOut()` match on the exact value, so they exclude
     * these already; this scope exists to name the set rather than leave it as
     * "whatever is left over".
     */
    public function scopeCommercial(Builder $query): Builder
    {
        return $query->where('movement_direction', 'commercial');
    }

    /** True when this type may be chosen as a purpose at a gate. */
    public function isGatePurpose(): bool
    {
        return in_array($this->movement_direction, ['gate_in', 'gate_out'], true);
    }

    /** Human label for a direction value. */
    public static function directionLabel(string $direction): string
    {
        return match ($direction) {
            'gate_in'    => 'Gate In',
            'gate_out'   => 'Gate Out',
            'commercial' => 'Commercial (no gate)',
            default      => ucfirst(str_replace('_', ' ', $direction)),
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * All workflow/revenue flag columns with human-readable labels.
     */
    public static function workflowFlags(): array
    {
        return [
            'handling_applicable'       => 'Handling',
            'survey_applicable'         => 'Survey / Inspection',
            'estimate_applicable'       => 'Estimate Preparation',
            'repair_applicable'         => 'Repair',
            'storage_applicable'        => 'Storage',
            'wash_applicable'           => 'Wash / Cleaning',
            'reefer_applicable'         => 'Reefer Monitoring',
            'customs_applicable'        => 'Customs / Hold',
            'cargo_transfer_applicable' => 'Cargo Transfer',
        ];
    }

    /**
     * Returns only the flags that are true on this instance, as labels.
     */
    public function activeFlags(): array
    {
        return array_values(array_filter(
            self::workflowFlags(),
            fn($label, $col) => (bool) $this->$col,
            ARRAY_FILTER_USE_BOTH
        ));
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function gateMovements()
    {
        return $this->hasMany(\App\Models\GateMovement::class, 'job_type_id');
    }

    public function yardJobs()
    {
        return $this->hasMany(\App\Models\YardJob::class, 'job_type_id');
    }

    /**
     * Badge colour map for movement_direction display.
     */
    public static function directionBadge(string $direction): string
    {
        return match ($direction) {
            'gate_in'  => 'bg-success-subtle text-success',
            'gate_out' => 'bg-danger-subtle text-danger',
            default    => 'bg-secondary-subtle text-secondary',
        };
    }
}
