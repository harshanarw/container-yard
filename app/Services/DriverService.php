<?php

namespace App\Services;

use App\Models\Driver;

/**
 * Maintains the driver master from the details captured at each gate movement /
 * Guard Post capture. NIC/passport is the natural key; a movement with no NIC is
 * skipped (we never create keyless rows). Name and phone are overwritten with the
 * latest non-blank values so the master tracks the most recent contact details.
 */
class DriverService
{
    /**
     * Record (create or update) a driver from a movement's captured details.
     * Returns the Driver, or null when there's no NIC to key on.
     *
     * Callers should treat this as best-effort — wrap in try/catch so a master
     * write can never break the gate flow.
     */
    public function remember(?string $name, ?string $nic, ?string $phone, ?int $userId = null): ?Driver
    {
        $nic = Driver::normalizeNic($nic);
        if ($nic === '') {
            return null; // NIC primary — skip when blank
        }

        $name  = trim((string) $name);
        $phone = trim((string) $phone);

        $driver = Driver::firstOrNew(['nic_number' => $nic]);

        // Overwrite with the latest non-blank values (don't wipe good data with a blank).
        if ($name !== '') {
            $driver->name = $name;
        }
        if ($phone !== '') {
            $driver->phone = $phone;
        }

        $driver->movement_count = ($driver->movement_count ?? 0) + 1;
        $driver->last_seen_at   = now();

        if ($userId) {
            $driver->updated_by = $userId;
            if (! $driver->exists) {
                $driver->created_by = $userId;
            }
        }

        $driver->save();

        return $driver;
    }
}
