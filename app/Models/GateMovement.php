<?php

namespace App\Models;

use App\Traits\HasApprovals;
use App\Traits\HasDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GateMovement extends Model
{
    use HasFactory, HasDocuments, HasApprovals;

    protected $fillable = [
        'container_id', 'survey_id', 'job_type_id', 'job_type_code', 'yard_job_id',
        'container_no', 'customer_id', 'transporter_id', 'movement_type', 'eir_no', 'size',
        'container_type', 'ventilation_type', 'vent_count',
        'location_zone', 'location_row', 'location_bay', 'location_tier',
        'condition', 'grade_id', 'cargo_status', 'seal_no', 'no_seal_reason', 'vehicle_plate', 'driver_name',
        'driver_ic', 'driver_phone', 'release_order', 'gate_in_time', 'gate_out_time',
        'movement_status', 'remarks', 'created_by',
        // Gate-In: import shipment information
        'vessel_name', 'voyage_no', 'berthing_date', 'bl_number', 'do_expiry_date', 'fcl_expiry_date', 'consignee',
        // Gate-In: overtime receipt link
        'ot_receipt_id', 'is_overtime', 'ot_override_reason',
        // Gate-Out: export information
        'loading_vessel', 'loading_voyage', 'sailing_date', 'shipper',
        'gate_out_purpose', 'container_booking_id',
        'codeco_exported_at', 'csv_exported_at',
        'codeco_exported_by', 'csv_exported_by',
        'codeco_batch_ref', 'csv_batch_ref',
        'container_ocr_image_path', 'plate_ocr_image_path',
        'share_code', 'share_expires_at',
    ];

    /** Days a freshly-sent driver gate-pass link stays valid. */
    public const SHARE_LINK_DAYS = 7;

    protected static function booted(): void
    {
        // Give every new movement a short, unguessable code for the shareable
        // driver gate-pass link (/g/{code}).
        static::creating(function (GateMovement $movement) {
            if (empty($movement->share_code)) {
                $movement->share_code = static::generateShareCode();
            }
        });
    }

    public static function generateShareCode(): string
    {
        do {
            $code = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(12));
        } while (static::where('share_code', $code)->exists());

        return $code;
    }

    /** Start (or refresh) the share link's validity window from now. */
    public function refreshShareLink(): void
    {
        if (empty($this->share_code)) {
            $this->share_code = static::generateShareCode();
        }
        $this->share_expires_at = now()->addDays(self::SHARE_LINK_DAYS);
        $this->save();
    }

    /** True while the driver share link is live (sent and not yet expired). */
    public function shareLinkIsValid(): bool
    {
        return $this->share_code
            && $this->share_expires_at
            && $this->share_expires_at->isFuture();
    }

    protected $casts = [
        'vent_count'         => 'integer',
        'share_expires_at'   => 'datetime',
        'gate_in_time'       => 'datetime',
        'gate_out_time'      => 'datetime',
        'berthing_date'      => 'date',
        'do_expiry_date'     => 'date',
        'fcl_expiry_date'    => 'date',
        'sailing_date'       => 'date',
        'codeco_exported_at' => 'datetime',
        'csv_exported_at'    => 'datetime',
    ];

    // Relationships
    public function yardJob()
    {
        return $this->belongsTo(\App\Models\YardJob::class, 'yard_job_id');
    }

    public function jobType()
    {
        return $this->belongsTo(\App\Models\YardJobType::class, 'job_type_id');
    }

    public function grade()
    {
        return $this->belongsTo(\App\Models\ContainerGrade::class, 'grade_id');
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    /** The Guard Post capture this movement was promoted from, if any. */
    public function guardCapture()
    {
        return $this->hasOne(\App\Models\GuardCapture::class, 'linked_gate_movement_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function transporter()
    {
        return $this->belongsTo(Customer::class, 'transporter_id');
    }

    public function otReceipt()
    {
        return $this->belongsTo(\App\Models\OtReceipt::class, 'ot_receipt_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function survey()
    {
        return $this->belongsTo(\App\Models\Inquiry::class, 'survey_id');
    }

    public function photos()
    {
        return $this->hasMany(GateMovementPhoto::class);
    }

    public function codecoExportedBy()
    {
        return $this->belongsTo(User::class, 'codeco_exported_by');
    }

    public function csvExportedBy()
    {
        return $this->belongsTo(User::class, 'csv_exported_by');
    }

    public function isPendingCodecoExport(): bool
    {
        return is_null($this->codeco_exported_at);
    }

    public function isPendingCsvExport(): bool
    {
        return is_null($this->csv_exported_at);
    }

    public function getContainerOcrImageUrlAttribute(): ?string
    {
        return $this->container_ocr_image_path
            ? asset('storage/' . $this->container_ocr_image_path)
            : null;
    }

    public function getPlateOcrImageUrlAttribute(): ?string
    {
        return $this->plate_ocr_image_path
            ? asset('storage/' . $this->plate_ocr_image_path)
            : null;
    }
}
