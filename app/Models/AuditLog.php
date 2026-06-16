<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'log_name', 'event', 'description', 'reference',
        'subject_type', 'subject_id',
        'causer_id', 'causer_name', 'causer_role',
        'properties', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}
