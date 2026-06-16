<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class StorageInvoiceObserver extends AuditObserver
{
    protected function getModule(): string            { return 'billing.storage'; }
    protected function getReference(Model $m): ?string { return $m->invoice_no ?? null; }

    protected function describeCreated(Model $m, ?string $ref): string { return "Storage Invoice {$ref} created"; }
    protected function describeDeleted(Model $m, ?string $ref): string { return "Storage Invoice {$ref} deleted"; }

    public function updated(Model $m): void
    {
        $diff = AuditService::updatedDiff($m);
        if (empty($diff)) return;

        $ref       = $this->getReference($m);
        $newStatus = $diff['new']['status'] ?? null;

        $descriptions = [
            'issued'    => "Storage Invoice {$ref} issued",
            'paid'      => "Storage Invoice {$ref} marked paid",
            'cancelled' => "Storage Invoice {$ref} cancelled",
        ];

        $desc    = $descriptions[$newStatus] ?? null;
        $event   = $newStatus === 'issued' ? 'approved' : ($newStatus ? 'updated' : 'updated');
        $changed = implode(', ', array_keys($diff['old'] ?? []));

        AuditService::log(
            event: $event,
            module: $this->getModule(),
            description: $desc ?? "Storage Invoice {$ref} updated [{$changed}]",
            reference: $ref, subject: $m, properties: $diff,
        );
    }
}
