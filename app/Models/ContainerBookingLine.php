<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContainerBookingLine extends Model
{
    protected $fillable = [
        'container_booking_id', 'size', 'type_code', 'grade_id',
        'quantity', 'allocated_qty', 'released_qty',
    ];

    protected $casts = [
        'quantity'      => 'integer',
        'allocated_qty' => 'integer',
        'released_qty'  => 'integer',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(ContainerBooking::class, 'container_booking_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(ContainerGrade::class, 'grade_id');
    }

    /** Containers currently reserved against this line. */
    public function containers(): HasMany
    {
        return $this->hasMany(Container::class, 'container_booking_line_id');
    }

    /** Units still to be released (quantity − released). */
    public function getRemainingAttribute(): int
    {
        return max(0, (int) $this->quantity - (int) $this->released_qty);
    }

    /** Units still needing a container — neither currently reserved nor already released. */
    public function getUnallocatedAttribute(): int
    {
        return max(0, (int) $this->quantity - (int) $this->allocated_qty - (int) $this->released_qty);
    }

    public function getLabelAttribute(): string
    {
        return trim($this->size . ' ' . $this->type_code . ($this->grade ? ' · ' . $this->grade->code : ''));
    }
}
