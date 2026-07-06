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
