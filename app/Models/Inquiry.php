<?php

namespace App\Models;

use App\Traits\HasDocuments;
use App\Traits\HasYardJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    use HasFactory, HasDocuments, HasYardJob;

    protected $fillable = [
        'yard_job_id',
        'inquiry_no', 'container_id', 'container_no', 'equipment_type_id', 'size', 'type_code',
        'customer_id', 'inquiry_type', 'inspector_id', 'inspection_date',
        'gate_in_ref', 'priority', 'overall_condition', 'findings',
        'recommended_action', 'status', 'estimated_repair_cost',
        'wash_required', 'wash_scope', 'wash_type',
    ];

    protected $casts = [
        'inspection_date'      => 'date',
        'estimated_repair_cost' => 'decimal:2',
        'wash_required'        => 'boolean',
    ];

    // Relationships
    public function equipmentType()
    {
        return $this->belongsTo(EquipmentType::class);
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function damages()
    {
        return $this->hasMany(Damage::class);
    }

    public function checklists()
    {
        return $this->hasMany(InquiryChecklist::class);
    }

    public function photos()
    {
        return $this->hasMany(InquiryPhoto::class);
    }

    public function estimate()
    {
        return $this->hasOne(Estimate::class);
    }
}
