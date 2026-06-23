<?php

namespace App\Observers;

use App\Services\AuditService;
use App\Services\Finance\InvoicePostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class RepairInvoiceObserver extends AuditObserver
{
    protected function getModule(): string            { return 'billing.repair'; }
    protected function getReference(Model $m): ?string { return $m->invoice_no ?? null; }

    protected function describeCreated(Model $m, ?string $ref): string
    {
        return "Repair Invoice {$ref} created"
            . ($m->container_no ? " — {$m->container_no}" : '');
    }

    protected function describeDeleted(Model $m, ?string $ref): string
    {
        return "Repair Invoice {$ref} deleted"
            . ($m->container_no ? " — {$m->container_no}" : '');
    }

    public function updated(Model $m): void
    {
        $diff = AuditService::updatedDiff($m);
        if (empty($diff)) return;

        $ref       = $this->getReference($m);
        $newStatus = $diff['new']['status'] ?? null;

        $eventMap = [
            'issued'   => ['approved', "Repair Invoice {$ref} issued"],
            'paid'     => ['updated',  "Repair Invoice {$ref} marked paid"],
            'cancelled'=> ['deleted',  "Repair Invoice {$ref} cancelled"],
            'void'     => ['deleted',  "Repair Invoice {$ref} voided"],
        ];

        if ($newStatus && isset($eventMap[$newStatus])) {
            [$event, $desc] = $eventMap[$newStatus];
            AuditService::log(event: $event, module: $this->getModule(),
                description: $desc, reference: $ref, subject: $m, properties: $diff);

            if ($newStatus === 'issued') {
                try {
                    app(InvoicePostingService::class)->post($m, 'repair', auth()->id() ?? 1);
                } catch (\Throwable $e) {
                    Log::error("Auto-post failed for repair invoice {$ref}: {$e->getMessage()}");
                }
            }

            return;
        }

        $changed = implode(', ', array_keys($diff['old'] ?? []));
        AuditService::log(event: 'updated', module: $this->getModule(),
            description: "Repair Invoice {$ref} updated [{$changed}]",
            reference: $ref, subject: $m, properties: $diff);
    }
}
