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
        'manufacture_year', 'manufacturer', 'owner_code', 'owner_name', 'owner_customer_id',
        'gross_weight_kg', 'tare_weight_kg', 'max_payload_kg',
        'csc_plate_no', 'csc_expiry_date', 'notes',
        // leasing
        'lessor_name', 'lessor_code', 'lease_reference',
        'lease_start_date', 'lease_end_date',
    ];

    // ── Operational snapshot fields (updated by Gate-In / Gate-Out only) ─────
    //    Full per-cycle history lives in gate_movements table (1 row per event).
    //    These fields reflect the *current* state of the container in the yard.
    const OPERATIONAL_FIELDS = [
        // customer_id sits here, not in MASTER_FIELDS: it is the *current
        // visit's* customer, set by gate-in, and has nothing to do with who
        // owns the box (see $ownerCustomer). It was declared a master field
        // while gate-in overwrote it every visit and gate-out read it as the
        // release party — which is how a container could leave under a
        // different customer than it arrived under.
        'customer_id',
        'status', 'condition', 'cargo_status',
        'location_zone', 'location_row', 'location_bay', 'location_tier',
        'seal_no', 'gate_in_date', 'gate_out_date', 'csc_plate_valid',
    ];

    protected $fillable = [
        // master
        'container_no', 'category', 'equipment_type_id', 'grade_id', 'size', 'type_code',
        'ventilation_type', 'vent_count',
        'manufacture_year', 'manufacturer', 'owner_code', 'owner_name', 'owner_customer_id',
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
        // reefer PTI (denormalised latest result)
        'pti_status', 'pti_at',
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
        'pti_at'            => 'datetime',
        // M&R status projection — written only by ContainerMrStatusService.
        'mr_status_at'         => 'datetime',
        'export_ready'         => 'boolean',
        'mr_status_expires_at' => 'date',
    ];

    /**
     * The derived M&R status columns.
     *
     * Deliberately absent from $fillable: ContainerMrStatusService::refresh()
     * is the only writer, and it uses forceFill. Nothing else may set them —
     * that single-writer rule is what keeps this projection from repeating the
     * containers.status stranding, where many controllers wrote the column and
     * one branch forgot.
     */
    public const MR_STATUS_FIELDS = [
        'mr_status', 'mr_status_group', 'mr_lane', 'mr_status_at',
        'export_ready', 'mr_status_expires_at',
    ];

    /**
     * Containers free to leave on an export booking.
     *
     * export_ready alone is not the answer: a reefer stored as ready stops
     * being ready the day its PTI lapses, and no row changes when that happens.
     * Comparing the stored expiry against today makes the answer exact at every
     * instant, with no scheduled recompute and no join.
     */
    public function scopeExportReady($query)
    {
        return $query->where('export_ready', true)
            ->where(fn ($q) => $q->whereNull('mr_status_expires_at')
                                 ->orWhere('mr_status_expires_at', '>=', \Illuminate\Support\Carbon::today()->toDateString()));
    }

    /**
     * Containers whose stored status rested on a date that has since passed —
     * today, a reefer with a lapsed PTI. The stored code still reads as it did
     * when the PTI was live, so lists overlay a chip off this rather than
     * re-deriving per row.
     */
    public function scopeStatusExpired($query)
    {
        return $query->whereNotNull('mr_status_expires_at')
            ->where('mr_status_expires_at', '<', \Illuminate\Support\Carbon::today()->toDateString());
    }

    /** Has this container's stored M&R status aged out? */
    public function mrStatusHasExpired(): bool
    {
        return $this->mr_status_expires_at !== null
            && $this->mr_status_expires_at->lt(\Illuminate\Support\Carbon::today());
    }

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

    // ── Reefer PTI ───────────────────────────────────────────────────────────
    public function ptiInspections()
    {
        return $this->hasMany(ReeferPtiInspection::class);
    }

    /** True for Reefer / Reefer High-Cube container types. */
    public function isReefer(): bool
    {
        return in_array($this->type_code, ['RF', 'RH'], true);
    }

    /** A currently-valid passing PTI (passed and not expired). */
    public function hasValidPti(): bool
    {
        if ($this->pti_status !== 'passed') {
            return false;
        }

        // Reuse the eager-loaded relation when present (the show page loads it and
        // calls this twice) to avoid extra queries; fall back to a scoped query.
        $latest = $this->relationLoaded('ptiInspections')
            ? $this->ptiInspections->where('result', 'pass')->sortByDesc('inspected_at')->first()
            : $this->ptiInspections()->where('result', 'pass')->latest('inspected_at')->first();

        // valid_until is a date and is inclusive — the PTI stays valid through that
        // whole day, so it only lapses once today is past it.
        return $latest && (!$latest->valid_until || !$latest->valid_until->lt(\Illuminate\Support\Carbon::today()));
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

    /**
     * The customer of the container's *current* visit.
     *
     * Maintained by gate-in. It is a convenience cache of "who has the box
     * right now", not a property of the box — the authoritative per-visit value
     * lives on the YardJob, and gate-out reads it from there
     * (ContainerCustodyService). Do not treat this as the owner.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Who owns the box. A property of the asset, unrelated to who is at the
     * gate today. Null when the owner is a party the yard has no customer
     * record for — `owner_code` / `owner_name` remain the fallback.
     */
    public function ownerCustomer()
    {
        return $this->belongsTo(Customer::class, 'owner_customer_id');
    }

    /** Owner as a display string: the linked party, else the recorded text. */
    public function ownerLabel(): ?string
    {
        return $this->ownerCustomer?->name
            ?: ($this->owner_name ?: $this->owner_code);
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
