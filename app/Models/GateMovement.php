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
        'condition', 'grade_id', 'cargo_status', 'seal_no', 'vehicle_plate', 'driver_name',
        'driver_ic', 'driver_phone', 'release_order', 'gate_in_time', 'gate_out_time',
        'movement_status', 'remarks', 'created_by',
        // Gate-In: import shipment information
        'vessel_name', 'voyage_no', 'berthing_date', 'bl_number', 'do_expiry_date', 'fcl_expiry_date', 'consignee',
        // Gate-Out: export information
        'loading_vessel', 'loading_voyage', 'sailing_date', 'shipper',
        'codeco_exported_at', 'csv_exported_at',
        'codeco_exported_by', 'csv_exported_by',
        'codeco_batch_ref', 'csv_batch_ref',
        'container_ocr_image_path', 'plate_ocr_image_path',
    ];

    protected $casts = [
        'vent_count'         => 'integer',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function transporter()
    {
        return $this->belongsTo(Customer::class, 'transporter_id');
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
