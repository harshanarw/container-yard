<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class WorkOrderObserver extends AuditObserver
{
    protected function getModule(): string            { return 'work-orders'; }
    protected function getReference(Model $m): ?string { return $m->wo_no ?? null; }

    protected function describeCreated(Model $m, ?string $ref): string
    {
        return "Work Order {$ref} created"
            . ($m->container_no ? " — {$m->container_no}" : '');
    }

    protected function describeDeleted(Model $m, ?string $ref): string
    {
        return "Work Order {$ref} deleted"
            . ($m->container_no ? " — {$m->container_no}" : '');
    }

    public function updated(Model $m): void
    {
        $diff = AuditService::updatedDiff($m);
        if (empty($diff)) return;

        $ref       = $this->getReference($m);
        $newStatus = $diff['new']['status'] ?? null;

        if ($newStatus === 'approved') {
            AuditService::log(event: 'approved', module: $this->getModule(),
                description: "Work Order {$ref} approved", reference: $ref, subject: $m, properties: $diff);
            return;
        }
        if ($newStatus === 'completed') {
            AuditService::log(event: 'updated', module: $this->getModule(),
                description: "Work Order {$ref} marked completed", reference: $ref, subject: $m, properties: $diff);
            return;
        }

        $changed = implode(', ', array_keys($diff['old'] ?? []));
        AuditService::log(
            event: 'updated',
            module: $this->getModule(),
            description: "Work Order {$ref} updated [{$changed}]",
            reference: $ref, subject: $m, properties: $diff,
        );
    }
}
