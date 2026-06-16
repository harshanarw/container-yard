<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class EstimateObserver extends AuditObserver
{
    protected function getModule(): string            { return 'estimates'; }
    protected function getReference(Model $m): ?string { return $m->estimate_no ?? null; }

    protected function describeCreated(Model $m, ?string $ref): string
    {
        return "Estimate {$ref} created"
            . ($m->container_no ? " — {$m->container_no}" : '');
    }

    protected function describeDeleted(Model $m, ?string $ref): string
    {
        return "Estimate {$ref} deleted"
            . ($m->container_no ? " — {$m->container_no}" : '');
    }

    public function updated(Model $m): void
    {
        $diff = AuditService::updatedDiff($m);
        if (empty($diff)) return;

        $ref = $this->getReference($m);

        // Surface approve/reject as dedicated event types for easy filtering
        $newStatus = $diff['new']['status'] ?? null;
        if ($newStatus === 'approved') {
            AuditService::log(
                event: 'approved',
                module: $this->getModule(),
                description: "Estimate {$ref} approved"
                    . ($m->container_no ? " — {$m->container_no}" : ''),
                reference: $ref,
                subject: $m,
                properties: $diff,
            );
            return;
        }
        if ($newStatus === 'rejected') {
            AuditService::log(
                event: 'rejected',
                module: $this->getModule(),
                description: "Estimate {$ref} rejected"
                    . ($m->rejected_reason ? ': ' . $m->rejected_reason : '')
                    . ($m->container_no ? " — {$m->container_no}" : ''),
                reference: $ref,
                subject: $m,
                properties: $diff,
            );
            return;
        }

        $changed = implode(', ', array_keys($diff['old'] ?? []));
        AuditService::log(
            event: 'updated',
            module: $this->getModule(),
            description: "Estimate {$ref} updated [{$changed}]",
            reference: $ref,
            subject: $m,
            properties: $diff,
        );
    }
}
