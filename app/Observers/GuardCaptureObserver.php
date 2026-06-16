<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

class GuardCaptureObserver extends AuditObserver
{
    protected function getModule(): string            { return 'guard-post'; }
    protected function getReference(Model $m): ?string { return $m->reference_no ?? $m->container_number ?? null; }

    protected function describeCreated(Model $m, ?string $ref): string
    {
        $dir = $m->direction === 'gate_out' ? 'Gate-Out' : 'Gate-In';
        return "Guard capture {$ref} recorded ({$dir})"
            . ($m->container_number ? " — {$m->container_number}" : '');
    }
}
