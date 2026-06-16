<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class StorageHandlingInvoiceObserver extends AuditObserver
{
    protected function getModule(): string            { return 'billing.storage-handling'; }
    protected function getReference(Model $m): ?string { return $m->invoice_no ?? null; }

    protected function describeCreated(Model $m, ?string $ref): string { return "S&H Invoice {$ref} created"; }
    protected function describeDeleted(Model $m, ?string $ref): string { return "S&H Invoice {$ref} deleted"; }

    public function updated(Model $m): void
    {
        $diff = AuditService::updatedDiff($m);
        if (empty($diff)) return;

        $ref       = $this->getReference($m);
        $newStatus = $diff['new']['status'] ?? null;

        $descriptions = [
            'issued'    => ['approved', "S&H Invoice {$ref} issued"],
            'paid'      => ['updated',  "S&H Invoice {$ref} marked paid"],
            'cancelled' => ['deleted',  "S&H Invoice {$ref} cancelled"],
        ];

        if ($newStatus && isset($descriptions[$newStatus])) {
            [$event, $desc] = $descriptions[$newStatus];
        } else {
            $changed = implode(', ', array_keys($diff['old'] ?? []));
            $event   = 'updated';
            $desc    = "S&H Invoice {$ref} updated [{$changed}]";
        }

        AuditService::log(event: $event, module: $this->getModule(),
            description: $desc, reference: $ref, subject: $m, properties: $diff);
    }
}
