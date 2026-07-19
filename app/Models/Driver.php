<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Driver master record, keyed on a normalised NIC/passport number. Populated
 * automatically from gate movements and Guard Post captures via DriverService.
 */
class Driver extends Model
{
    protected $fillable = [
        'nic_number', 'name', 'phone', 'license_number',
        'movement_count', 'last_seen_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'last_seen_at'   => 'datetime',
        'movement_count' => 'integer',
    ];

    /** Normalise an NIC/passport for use as the match key: trimmed, upper-cased. */
    public static function normalizeNic(?string $nic): string
    {
        return strtoupper(trim((string) $nic));
    }
}
