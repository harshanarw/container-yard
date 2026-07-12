<?php

namespace App\Observers;

use App\Models\InvoicePosting;
use App\Services\AuditService;
use App\Services\Finance\InvoicePostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class GeneralInvoiceObserver extends AuditObserver
{
    protected function getModule(): string             { return 'billing.general'; }
    protected function getReference(Model $m): ?string  { return $m->invoice_no ?? null; }

    protected function describeCreated(Model $m, ?string $ref): string
    {
        return "General Invoice {$ref} created ({$m->type_label})";
    }

    protected function describeDeleted(Model $m, ?string $ref): string
    {
        return "General Invoice {$ref} deleted";
    }

    public function updated(Model $m): void
    {
        $diff = AuditService::updatedDiff($m);
        if (empty($diff)) return;

        $ref       = $this->getReference($m);
        $newStatus = $diff['new']['status'] ?? null;

        $eventMap = [
            'issued'    => ['approved', "General Invoice {$ref} issued"],
            'paid'      => ['updated',  "General Invoice {$ref} marked paid"],
            'cancelled' => ['deleted',  "General Invoice {$ref} cancelled"],
            'void'      => ['deleted',  "General Invoice {$ref} voided"],
        ];

        if ($newStatus && isset($eventMap[$newStatus])) {
            [$event, $desc] = $eventMap[$newStatus];
            AuditService::log(event: $event, module: $this->getModule(),
                description: $desc, reference: $ref, subject: $m, properties: $diff);

            if ($newStatus === 'issued') {
                // postSafely never throws: it records a durable 'failed' posting
                // and captures the reason (surfaced to the user by the controller)
                // instead of silently swallowing the failure.
                app(InvoicePostingService::class)->postSafely($m, 'general', auth()->id() ?? 1);
            }

            if (in_array($newStatus, ['cancelled', 'void'], true)) {
                $posting = InvoicePosting::where('invoice_type', 'general')
                    ->where('invoice_id', $m->id)
                    ->where('status', 'posted')
                    ->first();
                if ($posting) {
                    try {
                        app(InvoicePostingService::class)->void($posting, auth()->id() ?? 1, "Invoice {$ref} {$newStatus}");
                    } catch (\Throwable $e) {
                        Log::error("Auto-void failed for general invoice {$ref}: {$e->getMessage()}");
                    }
                }
            }

            return;
        }

        $changed = implode(', ', array_keys($diff['old'] ?? []));
        AuditService::log(event: 'updated', module: $this->getModule(),
            description: "General Invoice {$ref} updated [{$changed}]",
            reference: $ref, subject: $m, properties: $diff);
    }
}
