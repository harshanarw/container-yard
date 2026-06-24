<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ChargeCode;
use App\Models\Customer;
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
        return DB::transaction(function () use ($invoice, $invoiceType, $userId) {
            // Guard inside transaction with row-lock to prevent concurrent double-posts
            $existing = InvoicePosting::where('invoice_type', $invoiceType)
                ->where('invoice_id', $invoice->id)
                ->lockForUpdate()
                ->first();

            if ($existing?->status === 'posted') {
                throw new \RuntimeException(
                    "Invoice {$invoiceType}#{$invoice->id} is already posted to GL journal "
                    . ($existing->journal->journal_no ?? '?') . '.'
                );
            }

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

    // ─── Line building ────────────────────────────────────────────────────────

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
        $arAccount = $this->resolveAccount('customer_ar', Customer::class, $customerId)
            ?? $this->resolveAccount('customer_ar', null, null);

        if (!$arAccount) {
            throw new \RuntimeException(
                'No AR control account mapped. Configure Account Mappings → AR/AP Controls.'
            );
        }

        // Tax extraction per invoice type
        $taxAmount = (float) $this->extractTax($invoice, $invoiceType);
        $netAmount = $total - $taxAmount;
        if ($netAmount <= 0) {
            $netAmount = $total;
            $taxAmount = 0;
        }

        $taxAccount = null;
        if ($taxAmount > 0) {
            $taxAccount = $this->resolveAccount('tax_output', null, null)
                ?? Account::where('code', '2101')->where('is_active', true)->first();
        }

        // Build revenue credit lines broken down by charge code → account
        $revenueCredits = $this->buildRevenueCredits($invoice, $invoiceType, $netAmount);

        if (empty($revenueCredits)) {
            throw new \RuntimeException(
                "No revenue account could be resolved for invoice type '{$invoiceType}'. "
                . "Configure Account Mappings → Revenue or ensure charge codes have account mappings."
            );
        }

        $lines = [];

        // Debit AR (full invoice amount)
        $lines[] = [
            'account_id' => $arAccount->id,
            'debit'      => $total,
            'credit'     => 0,
            'narration'  => 'Trade debtors',
        ];

        // Credit revenue — one line per distinct account
        foreach ($revenueCredits as $rc) {
            $lines[] = [
                'account_id' => $rc['account_id'],
                'debit'      => 0,
                'credit'     => $rc['amount'],
                'narration'  => $rc['narration'],
            ];
        }

        // Credit tax
        if ($taxAmount > 0 && $taxAccount) {
            $lines[] = [
                'account_id' => $taxAccount->id,
                'debit'      => 0,
                'credit'     => $taxAmount,
                'narration'  => 'Output tax',
            ];
        } elseif ($taxAmount > 0 && !$taxAccount) {
            // No tax account mapped — absorb into first revenue credit line
            $lines[1]['credit'] += $taxAmount;
        }

        return $lines;
    }

    /**
     * Build revenue credit line(s) split by charge code → account mapping.
     *
     * Each invoice type has a distinct strategy:
     *
     * storage-handling — two separate header aggregates (storage_subtotal /
     *   handling_subtotal) are posted to their own revenue accounts (4001 / 4002).
     *   This is the core fix: previously everything was collapsed into a single
     *   4002 credit, so Storage Income was never recorded.
     *
     * storage / reefer — iterate detail/line rows, group by charge_code_id and
     *   resolve the account via charge_revenue AccountMapping. Falls back to the
     *   type-default account code when no mapping exists.
     *
     * repair — same per-line approach. Different charge codes (e.g. SRV/EST for
     *   survey lines vs DMR/WLD for repair lines) correctly map to different
     *   revenue accounts (4005 vs 4003).
     *
     * All strategies fall back to a single net-amount credit to the type-default
     * account when no lines are present or no charge code mappings resolve.
     */
    private function buildRevenueCredits(Model $invoice, string $invoiceType, float $netAmount): array
    {
        $accumulator = []; // account_id => ['amount' => float, 'narration' => string]

        $add = function (int $accountId, float $amount, string $narration) use (&$accumulator): void {
            if (!isset($accumulator[$accountId])) {
                $accumulator[$accountId] = ['amount' => 0.0, 'narration' => $narration];
            }
            $accumulator[$accountId]['amount'] += $amount;
        };

        switch ($invoiceType) {
            case 'storage-handling':
                // The header already carries pre-aggregated subtotals per component.
                // Storage lines use charge_code_id  → resolves to 4001 (Storage Revenue).
                // Handling lines use handling_charge_code_id → resolves to 4002 (Handling Revenue).
                // We use the header aggregates for amounts to avoid per-line float accumulation.
                $storageAmt  = round((float) ($invoice->storage_subtotal  ?? 0), 2);
                $handlingAmt = round((float) ($invoice->handling_subtotal ?? 0), 2);

                if ($storageAmt > 0) {
                    $acc = $this->resolveDefaultRevenueAccount('storage');
                    if ($acc) $add($acc->id, $storageAmt, 'Storage income');
                }
                if ($handlingAmt > 0) {
                    $acc = $this->resolveDefaultRevenueAccount('storage-handling');
                    if ($acc) $add($acc->id, $handlingAmt, 'Handling income');
                }
                break;

            case 'storage':
                $invoice->loadMissing('details');
                foreach ($invoice->details as $detail) {
                    $amt = round((float) ($detail->subtotal ?? 0), 2);
                    if ($amt <= 0) continue;
                    $acc = $this->resolveChargeRevenueAccount($detail->charge_code_id, '4001');
                    if ($acc) $add($acc->id, $amt, 'Storage income');
                }
                break;

            case 'reefer':
                $invoice->loadMissing('lines');
                foreach ($invoice->lines as $line) {
                    $amt = round((float) ($line->subtotal ?? 0), 2);
                    if ($amt <= 0) continue;
                    $acc = $this->resolveChargeRevenueAccount($line->charge_code_id, '4004');
                    if ($acc) $add($acc->id, $amt, 'Reefer electricity income');
                }
                break;

            case 'repair':
                // line_amount is the pre-tax net per line (gross_amount = line_amount + taxes)
                $invoice->loadMissing('lines');
                foreach ($invoice->lines as $line) {
                    $amt = round((float) ($line->line_amount ?? 0), 2);
                    if ($amt <= 0) continue;
                    $acc = $this->resolveChargeRevenueAccount($line->charge_code_id, '4003');
                    if ($acc) $add($acc->id, $amt, 'Repair income');
                }
                break;
        }

        // Fallback: if no per-line amounts resolved, post the full net to the type default
        if (empty($accumulator)) {
            $acc = $this->resolveDefaultRevenueAccount($invoiceType);
            if ($acc) {
                $add($acc->id, $netAmount, 'Revenue');
            }
        }

        $credits = [];
        foreach ($accumulator as $accountId => $entry) {
            $credits[] = [
                'account_id' => $accountId,
                'amount'     => round($entry['amount'], 2),
                'narration'  => $entry['narration'],
            ];
        }

        return $credits;
    }

    // ─── Account resolution helpers ──────────────────────────────────────────

    /**
     * Resolve revenue account for a charge code via charge_revenue AccountMapping.
     * Falls back to the account with $fallbackCode when no mapping is configured.
     */
    private function resolveChargeRevenueAccount(?int $chargeCodeId, string $fallbackCode): ?Account
    {
        if ($chargeCodeId !== null) {
            $mapped = $this->resolveAccount('charge_revenue', ChargeCode::class, $chargeCodeId);
            if ($mapped) {
                return $mapped;
            }
        }

        return Account::where('code', $fallbackCode)->where('is_active', true)->first();
    }

    /**
     * Resolve the type-level default revenue account (used for storage-handling splits
     * and as the last-resort fallback when no per-line charge codes are present).
     */
    private function resolveDefaultRevenueAccount(string $invoiceType): ?Account
    {
        $systemCodeMap = [
            'storage'          => '4001',
            'storage-handling' => '4002',
            'reefer'           => '4004',
            'repair'           => '4003',
        ];

        $code = $systemCodeMap[$invoiceType] ?? null;

        return $code
            ? Account::where('code', $code)->where('is_active', true)->first()
            : null;
    }

    private function resolveAccount(string $mappingType, ?string $sourceType, ?int $sourceId): ?Account
    {
        $query = AccountMapping::where('mapping_type', $mappingType)->where('is_active', true);

        // Eloquent's where('col', null) generates "col = NULL" which never matches in SQL.
        // Must use whereNull() / where() explicitly.
        $sourceType === null ? $query->whereNull('source_type') : $query->where('source_type', $sourceType);
        $sourceId   === null ? $query->whereNull('source_id')   : $query->where('source_id',   $sourceId);

        return $query->first()?->account;
    }

    // ─── Tax extraction ───────────────────────────────────────────────────────

    private function extractTax(Model $invoice, string $invoiceType): float
    {
        return match ($invoiceType) {
            // Repair: header sscl_total + vat_total (equals its tax_amount) — don't double-count.
            'repair'           => (float)(($invoice->sscl_total ?? 0) + ($invoice->vat_total ?? 0)),
            // Reefer: tax is split into separate SSCL + VAT columns on the header.
            'reefer'           => (float)(($invoice->sscl_amount ?? 0) + ($invoice->vat_amount ?? 0)),
            // Storage & storage-handling: store sscl_amount + vat_amount separately;
            // tax_amount is a legacy fallback for rows that predate the split columns.
            'storage'          => (float)(($invoice->sscl_amount ?? 0) + ($invoice->vat_amount ?? 0))
                                  ?: (float)($invoice->tax_amount ?? 0),
            'storage-handling' => (float)(($invoice->sscl_amount ?? 0) + ($invoice->vat_amount ?? 0))
                                  ?: (float)($invoice->tax_amount ?? 0),
            default            => 0.0,
        };
    }

    // ─── Narration ────────────────────────────────────────────────────────────

    private function narration(Model $invoice, string $invoiceType): string
    {
        $no   = $invoice->invoice_no ?? $invoice->reference_no ?? "#{$invoice->id}";
        $type = InvoicePosting::typeLabel($invoiceType);
        return "{$type} {$no}";
    }
}
