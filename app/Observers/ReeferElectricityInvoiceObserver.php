<?php

namespace App\Observers;

use App\Models\InvoicePosting;
use App\Services\AuditService;
use App\Services\Finance\InvoicePostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ReeferElectricityInvoiceObserver extends AuditObserver
{
    protected function getModule(): string            { return 'billing.reefer'; }
    protected function getReference(Model $m): ?string { return $m->invoice_no ?? null; }

    protected function describeCreated(Model $m, ?string $ref): string { return "Reefer Invoice {$ref} created"; }
    protected function describeDeleted(Model $m, ?string $ref): string { return "Reefer Invoice {$ref} deleted"; }

    public function updated(Model $m): void
    {
        $diff = AuditService::updatedDiff($m);
        if (empty($diff)) return;

        $ref       = $this->getReference($m);
        $newStatus = $diff['new']['status'] ?? null;

        $descriptions = [
            'issued'    => ['approved', "Reefer Invoice {$ref} issued"],
            'paid'      => ['updated',  "Reefer Invoice {$ref} marked paid"],
            'cancelled' => ['deleted',  "Reefer Invoice {$ref} cancelled"],
        ];

        if ($newStatus && isset($descriptions[$newStatus])) {
            [$event, $desc] = $descriptions[$newStatus];
        } else {
            $changed = implode(', ', array_keys($diff['old'] ?? []));
            $event   = 'updated';
            $desc    = "Reefer Invoice {$ref} updated [{$changed}]";
        }

        AuditService::log(event: $event, module: $this->getModule(),
            description: $desc, reference: $ref, subject: $m, properties: $diff);

        if ($newStatus === 'issued') {
            try {
                app(InvoicePostingService::class)->post($m, 'reefer', auth()->id() ?? 1);
            } catch (\Throwable $e) {
                Log::error("Auto-post failed for reefer invoice {$ref}: {$e->getMessage()}");
            }
        }

        if ($newStatus === 'cancelled') {
            $posting = InvoicePosting::where('invoice_type', 'reefer')
                ->where('invoice_id', $m->id)
                ->where('status', 'posted')
                ->first();
            if ($posting) {
                try {
                    app(InvoicePostingService::class)->void($posting, auth()->id() ?? 1, "Invoice {$ref} cancelled");
                } catch (\Throwable $e) {
                    Log::error("Auto-void failed for reefer invoice {$ref}: {$e->getMessage()}");
                }
            }
        }
    }
}
