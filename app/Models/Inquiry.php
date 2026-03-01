<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'inquiry_no', 'container_id', 'container_no', 'size', 'type_code',
        'customer_id', 'inquiry_type', 'inspector_id', 'inspection_date',
        'gate_in_ref', 'priority', 'overall_condition', 'findings',
        'recommended_action', 'status', 'estimated_repair_cost',
    ];

    protected $casts = [
        'inspection_date'      => 'date',
        'estimated_repair_cost' => 'decimal:2',
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
