<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerType extends Model
{
    protected $fillable = ['name', 'short_code', 'description', 'is_active', 'sort_order'];

    public function getDisplayCodeAttribute(): string
    {
        return $this->short_code ?: strtoupper(substr($this->name, 0, 3));
    }

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function customers()
    {
        return $this->belongsToMany(Customer::class);
    }
}
