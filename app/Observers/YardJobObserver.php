<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

class YardJobObserver extends AuditObserver
{
    protected function getModule(): string            { return 'yard.jobs'; }
    protected function getReference(Model $m): ?string { return $m->job_no ?? null; }

    protected function describeCreated(Model $m, ?string $ref): string
    {
        return "Yard Job {$ref} created"
            . ($m->job_type_code ? " — {$m->job_type_code}" : '');
    }

    protected function describeUpdated(Model $m, ?string $ref, array $diff): string
    {
        $changed = implode(', ', array_keys($diff['old'] ?? []));
        return "Yard Job {$ref} updated [{$changed}]";
    }

    protected function describeDeleted(Model $m, ?string $ref): string
    {
        return "Yard Job {$ref} deleted";
    }
}
