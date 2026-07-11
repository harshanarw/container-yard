<?php

namespace App\Models;

use App\Traits\HasYardJob;
use Illuminate\Database\Eloquent\Model;

class ReeferPlugSession extends Model
{
    use HasYardJob;

    protected $fillable = [
        'container_id', 'gate_movement_id', 'yard_job_id', 'customer_id', 'service_type',
        'plug_in_at', 'plug_out_at', 'status',
        'set_temperature', 'gate_out_movement_id',
        'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'plug_in_at'      => 'datetime',
        'plug_out_at'     => 'datetime',
        'set_temperature' => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function gateMovement()
    {
        return $this->belongsTo(GateMovement::class, 'gate_movement_id');
    }

    public function gateOutMovement()
    {
        return $this->belongsTo(GateMovement::class, 'gate_out_movement_id');
    }

    public function tempLogs()
    {
        return $this->hasMany(ReeferTempLog::class, 'plug_session_id')->orderBy('logged_at');
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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeUnbilled($query)
    {
        return $query->where('status', 'completed');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isActive(): bool    { return $this->status === 'active'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isBilled(): bool    { return $this->status === 'billed'; }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'pending'   => 'bg-warning-subtle text-warning border border-warning-subtle',
            'active'    => 'bg-success-subtle text-success border border-success-subtle',
            'completed' => 'bg-info-subtle text-info border border-info-subtle',
            'billed'    => 'bg-secondary-subtle text-secondary',
            default     => 'bg-light text-muted',
        };
    }

    /** Duration in decimal hours between plug_in_at and plug_out_at (or now). */
    public function totalHours(): float
    {
        if (!$this->plug_in_at) {
            return 0;
        }
        $end = $this->plug_out_at ?? now();
        return $this->plug_in_at->diffInMinutes($end) / 60;
    }

    /** Calendar days inclusive between plug_in date and plug_out date (or today). */
    public function totalDays(): int
    {
        if (!$this->plug_in_at) {
            return 0;
        }
        $end = ($this->plug_out_at ?? now())->startOfDay();
        return (int) $this->plug_in_at->startOfDay()->diffInDays($end) + 1;
    }
}
