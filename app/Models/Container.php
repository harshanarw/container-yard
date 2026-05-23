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
        'container_no', 'category', 'equipment_type_id', 'size', 'type_code',
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
        'container_no', 'category', 'equipment_type_id', 'size', 'type_code',
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
    ];

    protected $casts = [
        'gate_in_date'     => 'date',
        'gate_out_date'    => 'date',
        'csc_expiry_date'  => 'date',
        'lease_start_date' => 'date',
        'lease_end_date'   => 'date',
        'csc_plate_valid'  => 'boolean',
        'gross_weight_kg'  => 'decimal:2',
        'tare_weight_kg'   => 'decimal:2',
        'max_payload_kg'   => 'decimal:2',
    ];

    // Relationships
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

    public function getFullSizeTypeAttribute(): string
    {
        return "{$this->size}ft {$this->type_code}";
    }
}
