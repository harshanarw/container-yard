<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;

class InquiryObserver extends AuditObserver
{
    protected function getModule(): string            { return 'surveys'; }
    protected function getReference(Model $m): ?string
    {
        return $m->inquiry_no ?? $m->container_no ?? null;
    }

    protected function describeCreated(Model $m, ?string $ref): string
    {
        return "Survey {$ref} created"
            . ($m->container_no ? " — container {$m->container_no}" : '');
    }

    protected function describeUpdated(Model $m, ?string $ref, array $diff): string
    {
        $changed = implode(', ', array_keys($diff['old'] ?? []));
        return "Survey {$ref} updated [{$changed}]";
    }

    protected function describeDeleted(Model $m, ?string $ref): string
    {
        return "Survey {$ref} deleted";
    }
}
