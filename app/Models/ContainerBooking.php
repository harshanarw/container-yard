<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContainerBooking extends Model
{
    protected $fillable = [
        'booking_no', 'customer_id', 'status', 'valid_from', 'valid_to', 'remarks', 'created_by',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ContainerBookingLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'partial'], true);
    }

    public function totalQuantity(): int
    {
        return (int) $this->lines->sum('quantity');
    }

    public function totalAllocated(): int
    {
        return (int) $this->lines->sum('allocated_qty');
    }

    public function totalReleased(): int
    {
        return (int) $this->lines->sum('released_qty');
    }
}
