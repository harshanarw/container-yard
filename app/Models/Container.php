<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Container extends Model
{
    use HasFactory;

    const CATEGORY_CONSIGNEE = 'consignee';
    const CATEGORY_OWNED     = 'owned';
    const CATEGORY_LEASED    = 'leased';

    const CATEGORIES = [
        self::CATEGORY_CONSIGNEE => 'Consignee',
        self::CATEGORY_OWNED     => 'Owned',
        self::CATEGORY_LEASED    => 'Leased',
    ];

    // ── Master profile fields (edited via Container Master CRUD) ─────────────
    const MASTER_FIELDS = [
        'container_no', 'category', 'equipment_type_id', 'grade_id', 'size', 'type_code',
        'ventilation_type', 'vent_count',
        'manufacture_year', 'manufacturer', 'owner_code', 'owner_name',
        'gross_weight_kg', 'tare_weight_kg', 'max_payload_kg',
        'csc_plate_no', 'csc_expiry_date', 'notes', 'customer_id',
        // leasing
        'lessor_name', 'lessor_code', 'lease_reference',
        'lease_start_date', 'lease_end_date',
    ];

    // ── Operational snapshot fields (updated by Gate-In / Gate-Out only) ─────
    //    Full per-cycle history lives in gate_movements table (1 row per event).
    //    These fields reflect the *current* state of the container in the yard.
    const OPERATIONAL_FIELDS = [
        'status', 'condition', 'cargo_status',
        'location_zone', 'location_row', 'location_bay', 'location_tier',
        'seal_no', 'gate_in_date', 'gate_out_date', 'csc_plate_valid',
    ];

    protected $fillable = [
        // master
        'container_no', 'category', 'equipment_type_id', 'grade_id', 'size', 'type_code',
        'ventilation_type', 'vent_count',
        'manufacture_year', 'manufacturer', 'owner_code', 'owner_name',
        'gross_weight_kg', 'tare_weight_kg', 'max_payload_kg',
        'csc_plate_no', 'csc_expiry_date', 'notes',
        // leasing
        'lessor_name', 'lessor_code', 'lease_reference',
        'lease_start_date', 'lease_end_date',
        // operational snapshot (gate operations only)
        'customer_id', 'condition', 'cargo_status', 'status',
        'location_zone', 'location_row', 'location_bay', 'location_tier',
        'seal_no', 'gate_in_date', 'gate_out_date', 'csc_plate_valid',
        // disposition lifecycle
        'status_changed_at', 'available_since',
        // booking allocation (reserved → booking line)
        'container_booking_line_id', 'reserved_at',
    ];

    protected $casts = [
        'gate_in_date'      => 'date',
        'gate_out_date'     => 'date',
        'csc_expiry_date'   => 'date',
        'lease_start_date'  => 'date',
        'lease_end_date'    => 'date',
        'csc_plate_valid'   => 'boolean',
        'gross_weight_kg'   => 'decimal:2',
        'tare_weight_kg'    => 'decimal:2',
        'max_payload_kg'    => 'decimal:2',
        'vent_count'        => 'integer',
        'status_changed_at' => 'datetime',
        'available_since'   => 'datetime',
        'reserved_at'       => 'datetime',
    ];

    /** Dispositions where the container is physically present in the yard. */
    public const IN_YARD_STATUSES = ['in_yard', 'in_repair', 'reserved', 'available'];

    /** Available (sound / repaired) stock, ready for allocation. */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /** Reserved against a booking line. */
    public function scopeReserved($query)
    {
        return $query->where('status', 'reserved');
    }

    /** The booking line this container is currently reserved against (if any). */
    public function bookingLine()
    {
        return $this->belongsTo(ContainerBookingLine::class, 'container_booking_line_id');
    }

    // ── Holds (cross-cutting block, independent of disposition) ──────────────
    public function holds()
    {
        return $this->hasMany(ContainerHold::class);
    }

    /** Uncleared holds. */
    public function activeHolds()
    {
        return $this->holds()->whereNull('cleared_at');
    }

    /** True while any hold is uncleared. */
    public function isHeld(): bool
    {
        return $this->activeHolds()->exists();
    }

    /** Containers with at least one uncleared hold. */
    public function scopeHeld($query)
    {
        return $query->whereHas('holds', fn ($q) => $q->whereNull('cleared_at'));
    }

    /** Containers with no uncleared hold. */
    public function scopeNotHeld($query)
    {
        return $query->whereDoesntHave('holds', fn ($q) => $q->whereNull('cleared_at'));
    }

    /** Physically present in the yard (any non-released disposition). */
    public function scopeInYard($query)
    {
        return $query->whereIn('status', self::IN_YARD_STATUSES);
    }

    /** Whole days the container has sat in the available pool (null if not available). */
    public function getAvailableDaysAttribute(): ?int
    {
        return $this->status === 'available' && $this->available_since
            ? (int) $this->available_since->diffInDays(now())
            : null;
    }

    // Relationships
    public function grade()
    {
        return $this->belongsTo(ContainerGrade::class, 'grade_id');
    }

    public function equipmentType()
    {
        return $this->belongsTo(EquipmentType::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function inquiries()
    {
        return $this->hasMany(Inquiry::class);
    }

    public function estimates()
    {
        return $this->hasMany(Estimate::class);
    }

    public function gateMovements()
    {
        return $this->hasMany(GateMovement::class);
    }

    public function yardStorage()
    {
        return $this->hasMany(YardStorage::class);
    }

    public function hires()
    {
        return $this->hasMany(ContainerHire::class);
    }

    public function activeHire()
    {
        return $this->hasOne(ContainerHire::class)->where('status', 'active');
    }

    public function yardLocation()
    {
        return $this->hasOne(YardLocation::class);
    }

    // Helpers
    public function getDaysInYardAttribute(): int
    {
        $start = $this->gate_in_date ?? now();
        $end   = $this->gate_out_date ?? now();
        return (int) $start->diffInDays($end);
    }

    /** Ventilation type: container's own value, or falls back to its EQT master. */
    public function getEffectiveVentilationTypeAttribute(): ?string
    {
        return $this->ventilation_type
            ?? $this->loadMissing('equipmentType')->equipmentType?->ventilation_type;
    }

    /** Vent count: container's own value, or falls back to its EQT master. */
    public function getEffectiveVentCountAttribute(): ?int
    {
        return $this->vent_count
            ?? $this->loadMissing('equipmentType')->equipmentType?->vent_count;
    }

    public function getFullSizeTypeAttribute(): string
    {
        return "{$this->size}ft {$this->type_code}";
    }
}
