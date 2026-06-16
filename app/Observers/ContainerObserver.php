<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

class ContainerObserver extends AuditObserver
{
    protected function getModule(): string            { return 'containers'; }
    protected function getReference(Model $m): ?string { return $m->container_no ?? null; }

    protected function describeCreated(Model $m, ?string $ref): string
    {
        return "Container master record created — {$ref}";
    }

    protected function describeUpdated(Model $m, ?string $ref, array $diff): string
    {
        $changed = implode(', ', array_keys($diff['old'] ?? []));
        return "Container record updated [{$changed}] — {$ref}";
    }

    protected function describeDeleted(Model $m, ?string $ref): string
    {
        return "Container master record deleted — {$ref}";
    }
}
