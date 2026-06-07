<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContainerGrade extends Model
{
    protected $fillable = ['code', 'name', 'description', 'color', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function containers()
    {
        return $this->hasMany(Container::class, 'grade_id');
    }

    public function gateMovements()
    {
        return $this->hasMany(GateMovement::class, 'grade_id');
    }
}
