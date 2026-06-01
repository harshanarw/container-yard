<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageAssessmentRule extends Model
{
    protected $fillable = [
        'name',
        'location_code_id',
        'component_code_id',
        'damage_code_id',
        'repair_code_id',
        'default_severity',
        'description',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function locationCode()  { return $this->belongsTo(MrCode::class, 'location_code_id'); }
    public function componentCode() { return $this->belongsTo(MrCode::class, 'component_code_id'); }
    public function damageCode()    { return $this->belongsTo(MrCode::class, 'damage_code_id'); }
    public function repairCode()    { return $this->belongsTo(MrCode::class, 'repair_code_id'); }
    public function createdBy()     { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive($query) { return $query->where('is_active', true); }
}
