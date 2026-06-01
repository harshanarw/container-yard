<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MrTariffHeader extends Model
{
    protected $fillable = [
        'customer_id', 'name', 'valid_from', 'valid_to', 'currency',
        'applicable_sizes', 'is_active', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'valid_from'       => 'date',
        'valid_to'         => 'date',
        'is_active'        => 'boolean',
        'applicable_sizes' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function rules()
    {
        return $this->hasMany(MrTariffRule::class, 'mr_tariff_header_id');
    }

    public function items()
    {
        return $this->hasMany(MrTariffItem::class, 'mr_tariff_header_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getValidityLabelAttribute(): string
    {
        $from = $this->valid_from->format('d M Y');
        $to   = $this->valid_to ? $this->valid_to->format('d M Y') : 'open-ended';
        return "{$from} – {$to}";
    }
}
