<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\GlJournal;
use App\Models\InvoicePosting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InvoicePostingService
{
    public function __construct(private PostingEngine $engine) {}

    /**
     * Post an invoice to the GL.
     *
     * $invoice:     StorageInvoice | StorageHandlingInvoice | ReeferElectricityInvoice | RepairInvoice
     * $invoiceType: 'storage' | 'storage-handling' | 'reefer' | 'repair'
     *
     * Returns the InvoicePosting record.
     */
    public function post(Model $invoice, string $invoiceType, int $userId): InvoicePosting
    {
        // Guard: already posted?
        $existing = InvoicePosting::where('invoice_type', $invoiceType)
            ->where('invoice_id', $invoice->id)
            ->where('status', 'posted')
            ->first();

        if ($existing) {
            throw new \RuntimeException(
                "Invoice {$invoiceType}#{$invoice->id} is already posted to GL journal "
                . ($existing->journal->journal_no ?? '?') . '.'
            );
        }

        return DB::transaction(function () use ($invoice, $invoiceType, $userId) {
            // Create or retrieve the posting record
            $posting = InvoicePosting::firstOrCreate(
                ['invoice_type' => $invoiceType, 'invoice_id' => $invoice->id],
                ['status' => 'pending', 'created_by' => $userId]
            );

            try {
                $lines = $this->buildLines($invoice, $invoiceType);

                $journalDate = ($invoice->invoice_date ?? $invoice->created_at);
                if ($journalDate instanceof \Illuminate\Support\Carbon || $journalDate instanceof \Carbon\Carbon) {
                    $journalDateStr = $journalDate->toDateString();
                } else {
                    $journalDateStr = now()->toDateString();
                }

                $journal = $this->engine->createJournal([
                    'journal_date'   => $journalDateStr,
                    'journal_type'   => 'invoice',
                    'reference_type' => get_class($invoice),
                    'reference_id'   => $invoice->id,
                    'narration'      => $this->narration($invoice, $invoiceType),
                ], $lines);

                $this->engine->postJournal($journal, $userId);

                $posting->update([
                    'journal_id'    => $journal->id,
                    'status'        => 'posted',
                    'posted_at'     => now(),
                    'posted_by'     => $userId,
                    'error_message' => null,
                ]);
            } catch (\Throwable $e) {
                $posting->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                throw $e;
            }

            return $posting->fresh(['journal']);
        });
    }

    /**
     * Void a posted invoice journal.
     */
    public function void(InvoicePosting $posting, int $userId, string $reason = ''): void
    {
        if (!$posting->isPosted()) {
            throw new \RuntimeException('Only posted invoice journals can be voided.');
        }

        DB::transaction(function () use ($posting, $userId, $reason) {
            $this->engine->voidJournal($posting->journal, $userId, $reason);
            $posting->update(['status' => 'voided']);
        });
    }

    private function buildLines(Model $invoice, string $invoiceType): array
    {
        // RepairInvoice uses grand_total; all others use total_amount
        $total = (float) ($invoiceType === 'repair'
            ? ($invoice->grand_total ?? 0)
            : ($invoice->total_amount ?? 0));

        if ($total <= 0) {
            throw new \InvalidArgumentException('Invoice total must be greater than zero.');
        }

        // Resolve customer id — StorageHandlingInvoice uses shipping_line_id
        $customerId = $invoice->customer_id ?? $invoice->shipping_line_id ?? null;

        // Resolve AR control account
        $arAccount = $this->resolveAccount('customer_ar', \App\Models\Customer::class, $customerId)
            ?? $this->resolveAccount('customer_ar', null, null);

        if (!$arAccount) {
            throw new \RuntimeException(
                'No AR control account mapped. Configure Account Mappings → AR/AP Controls.'
            );
        }

        // Resolve revenue account based on invoice type
        $revenueAccount = $this->resolveRevenueAccount($invoiceType);

        if (!$revenueAccount) {
            throw new \RuntimeException(
                "No revenue account mapped for invoice type '{$invoiceType}'. Configure Account Mappings → Revenue."
            );
        }

        // Tax extraction per invoice type (avoids double-counting):
        // - RepairInvoice:           tax_amount = sscl_total + vat_total (already aggregated), use sscl_total+vat_total
        // - StorageHandlingInvoice:  separate sscl_amount + vat_amount; tax_amount not stored
        // - ReeferElectricityInvoice: separate sscl_amount + vat_amount
        // - StorageInvoice:          total_amount is inclusive; no breakdown available
        $taxAmount = (float) $this->extractTax($invoice, $invoiceType);

        $netAmount = $total - $taxAmount;
        if ($netAmount <= 0) {
            $netAmount = $total;
            $taxAmount = 0;
        }

        $taxAccount = null;
        if ($taxAmount > 0) {
            $taxAccount = $this->resolveAccount('tax_output', null, null);
        }

        $lines = [];

        // Debit AR (full invoice amount)
        $lines[] = [
            'account_id' => $arAccount->id,
            'debit'      => $total,
            'credit'     => 0,
            'narration'  => 'Trade debtors',
        ];

        // Credit Revenue (net amount)
        $lines[] = [
            'account_id' => $revenueAccount->id,
            'debit'      => 0,
            'credit'     => $netAmount,
            'narration'  => 'Revenue',
        ];

        // Credit Tax (if applicable)
        if ($taxAmount > 0 && $taxAccount) {
            $lines[] = [
                'account_id' => $taxAccount->id,
                'debit'      => 0,
                'credit'     => $taxAmount,
                'narration'  => 'Output tax',
            ];
        } elseif ($taxAmount > 0 && !$taxAccount) {
            // No tax account mapped — merge tax into revenue credit
            $lines[1]['credit'] += $taxAmount;
        }

        return $lines;
    }

    private function extractTax(Model $invoice, string $invoiceType): float
    {
        return match ($invoiceType) {
            // tax_amount already equals sscl_total + vat_total — don't double-count
            'repair'           => (float)(($invoice->sscl_total ?? 0) + ($invoice->vat_total ?? 0)),
            'storage-handling' => (float)(($invoice->sscl_amount ?? 0) + ($invoice->vat_amount ?? 0)),
            'reefer'           => (float)(($invoice->sscl_amount ?? 0) + ($invoice->vat_amount ?? 0)),
            default            => 0.0,
        };
    }

    private function resolveRevenueAccount(string $invoiceType): ?Account
    {
        // System fallback account codes by invoice type
        $systemCodeMap = [
            'storage'          => '4001',
            'storage-handling' => '4002',
            'reefer'           => '4004',
            'repair'           => '4003',
        ];

        $code = $systemCodeMap[$invoiceType] ?? null;
        if ($code) {
            return Account::where('code', $code)->where('is_active', true)->first();
        }

        return null;
    }

    private function resolveAccount(string $mappingType, ?string $sourceType, ?int $sourceId): ?Account
    {
        $mapping = AccountMapping::where('mapping_type', $mappingType)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('is_active', true)
            ->first();

        return $mapping?->account;
    }

    private function narration(Model $invoice, string $invoiceType): string
    {
        $no   = $invoice->invoice_no ?? $invoice->reference_no ?? "#{$invoice->id}";
        $type = InvoicePosting::typeLabel($invoiceType);
        return "{$type} {$no}";
    }
}
