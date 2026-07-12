<?php

namespace App\Observers;

use App\Models\InvoicePosting;
use App\Services\AuditService;
use App\Services\Finance\InvoicePostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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

        if ($newStatus === 'issued') {
            // postSafely never throws: it records a durable 'failed' posting and
            // captures the reason (surfaced to the user by the controller) instead
            // of silently swallowing the failure.
            app(InvoicePostingService::class)->postSafely($m, 'storage-handling', auth()->id() ?? 1);
        }

        if ($newStatus === 'cancelled') {
            $posting = InvoicePosting::where('invoice_type', 'storage-handling')
                ->where('invoice_id', $m->id)
                ->where('status', 'posted')
                ->first();
            if ($posting) {
                try {
                    app(InvoicePostingService::class)->void($posting, auth()->id() ?? 1, "Invoice {$ref} cancelled");
                } catch (\Throwable $e) {
                    Log::error("Auto-void failed for S&H invoice {$ref}: {$e->getMessage()}");
                }
            }
        }
    }
}
