<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GuardCapture extends Model
{
    protected $fillable = [
        'reference_no', 'direction', 'status',
        'container_image_path', 'container_number', 'iso_code', 'ocr_container_no',
        'plate_image_path', 'vehicle_number', 'vehicle_type', 'ocr_vehicle_no',
        'nic_front_path', 'nic_back_path', 'license_front_path',
        'driver_name', 'nic_number', 'driver_phone',
        'linked_gate_movement_id',
        'clearance_note', 'cleared_by', 'cleared_at',
        'captured_by', 'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'cleared_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function linkedGateMovement(): BelongsTo
    {
        return $this->belongsTo(GateMovement::class, 'linked_gate_movement_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isCleared(): bool   { return $this->status === 'cleared'; }
    public function isOnHold(): bool    { return $this->status === 'hold'; }
    public function isRejected(): bool  { return $this->status === 'rejected'; }

    public function getContainerImageUrlAttribute(): ?string
    {
        return $this->container_image_path ? Storage::url($this->container_image_path) : null;
    }

    public function getPlateImageUrlAttribute(): ?string
    {
        return $this->plate_image_path ? Storage::url($this->plate_image_path) : null;
    }

    public function getNicFrontUrlAttribute(): ?string
    {
        return $this->nic_front_path ? Storage::url($this->nic_front_path) : null;
    }

    public function getNicBackUrlAttribute(): ?string
    {
        return $this->nic_back_path ? Storage::url($this->nic_back_path) : null;
    }

    public function getLicenseFrontUrlAttribute(): ?string
    {
        return $this->license_front_path ? Storage::url($this->license_front_path) : null;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'cleared'  => 'success',
            'hold'     => 'warning',
            'rejected' => 'danger',
            default    => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'  => 'Pending',
            'cleared'  => 'Cleared',
            'hold'     => 'On Hold',
            'rejected' => 'Rejected',
            default    => ucfirst($this->status),
        };
    }

    public function getDirectionLabelAttribute(): string
    {
        return $this->direction === 'gate_in' ? 'Gate In' : 'Gate Out';
    }

    // ── Reference number ─────────────────────────────────────────────────────

    public static function generateReference(): string
    {
        $prefix = 'GP-' . now()->format('Ymd') . '-';
        $last = static::where('reference_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('reference_no');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
