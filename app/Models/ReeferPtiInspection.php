<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReeferPtiInspection extends Model
{
    protected $fillable = [
        'container_id', 'inspected_by', 'inspected_at',
        'set_point_temp', 'result', 'findings', 'valid_until',
    ];

    protected $casts = [
        'inspected_at'   => 'datetime',
        'valid_until'    => 'date',
        'set_point_temp' => 'decimal:2',
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
