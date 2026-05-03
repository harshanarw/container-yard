<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GateMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'container_id', 'survey_id', 'container_no', 'customer_id', 'movement_type', 'size',
        'container_type', 'location_row', 'location_bay', 'location_tier',
        'condition', 'cargo_status', 'seal_no', 'vehicle_plate', 'driver_name',
        'driver_ic', 'release_order', 'gate_in_time', 'gate_out_time',
        'movement_status', 'remarks', 'created_by',
        'codeco_exported_at', 'csv_exported_at',
        'codeco_exported_by', 'csv_exported_by',
        'codeco_batch_ref', 'csv_batch_ref',
    ];

    protected $casts = [
        'gate_in_time'       => 'datetime',
        'gate_out_time'      => 'datetime',
        'codeco_exported_at' => 'datetime',
        'csv_exported_at'    => 'datetime',
    ];

    // Relationships
    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
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
}
