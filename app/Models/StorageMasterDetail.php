<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorageMasterDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'storage_master_header_id',
        'equipment_type_id',
        'storage_rate',
        'currency',
    ];

    protected $casts = [
        'storage_rate' => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function header()
    {
        return $this->belongsTo(StorageMasterHeader::class, 'storage_master_header_id');
    }

    public function equipmentType()
    {
        return $this->belongsTo(EquipmentType::class);
    }
}
