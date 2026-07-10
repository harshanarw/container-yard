<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ChargeCode;
use App\Models\Customer;
use App\Models\GlJournal;
use App\Models\InvoicePosting;
use App\Services\CurrencyService;
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

        // The GL is kept in base/reporting currency (LKR). Source documents are
        // NOT uniform about which currency their stored amounts are in:
        //   • storage / storage-handling → amounts are ALREADY in base LKR
        //     (StorageBillingController / StorageHandlingController convert the
        //     tariff rate to LKR via CurrencyService::tariffMultiplier before
        //     persisting; `total_value` == `total_amount`).
        //   • reefer / repair → amounts are stored in the invoice currency.
        //
        // So the conversion multiplier is decided by the STORED currency, not the
        // invoice type alone: multiply by exchange_rate only when the stored
        // amounts are in a non-base currency. Applying exchange_rate uniformly
        // double-converted any invoice whose amounts were already LKR while a
        // non-unity rate was on the record (e.g. a USD-issued storage invoice, or
        // an LKR reefer invoice that still carried the USD→LKR rate) — overstating
        // AR/revenue ~rate×.
        //
        // The AR debit is summed from the credits last to avoid rounding gaps;
        // this lets receipts relieve AR at the booked rate with a clean FX
        // gain/loss on settlement.
        $default        = CurrencyService::defaultCurrency();
        $storedCurrency = in_array($invoiceType, ['storage', 'storage-handling'], true)
            ? $default
            : strtoupper((string) ($invoice->invoice_currency ?? $invoice->currency ?? $default));
        $rate = $storedCurrency === $default
            ? 1.0
            : ((float) ($invoice->exchange_rate ?? 1) ?: 1.0);

        // Document (transaction) currency + rate for the per-line multi-currency
        // amounts. The base debit/credit are computed above; the transaction-
        // currency amount of any base figure is base ÷ docRate. This is uniform
        // across types: storage base/300 → USD, reefer base(=doc×300)/300 → doc.
        $docCcy  = strtoupper((string) ($invoice->invoice_currency ?? $invoice->currency ?? $default));
        $docRate = $docCcy === $default ? 1.0 : ((float) ($invoice->exchange_rate ?: 1) ?: 1.0);
        $toTxn   = fn (float $baseAmt) => $docRate > 0 ? round($baseAmt / $docRate, 2) : $baseAmt;

        $creditLines = [];

        // Credit revenue — one line per distinct account
        foreach ($revenueCredits as $rc) {
            $creditLines[] = [
                'account_id' => $rc['account_id'],
                'debit'      => 0,
                'credit'     => round((float) $rc['amount'] * $rate, 2),
                'narration'  => $rc['narration'],
            ];
        }

        // Credit VAT only (SSCL is embedded in the revenue lines)
        if ($taxAmount > 0 && $taxAccount) {
            $creditLines[] = [
                'account_id' => $taxAccount->id,
                'debit'      => 0,
                'credit'     => round($taxAmount * $rate, 2),
                'narration'  => 'Output VAT',
            ];
        } elseif ($taxAmount > 0 && !$taxAccount) {
            // Never silently fold VAT into revenue — that overstates income and
            // understates the VAT liability owed to the tax authority. Fail fast.
            throw new \RuntimeException(
                'Output VAT could not be posted: no tax account mapped. '
                . 'Configure Account Mappings → Tax Output or activate account 2101.'
            );
        }

        $arDebit = round(array_sum(array_column($creditLines, 'credit')), 2);

        // Debit AR (full invoice value, base currency)
        $lines = [[
            'account_id' => $arAccount->id,
            'debit'      => $arDebit,
            'credit'     => 0,
            'narration'  => 'Trade debtors',
        ]];

        // Attach transaction-currency metadata to every line (base stays primary).
        return array_map(fn ($l) => $l + [
            'currency'      => $docCcy,
            'exchange_rate' => $docRate,
            'txn_debit'     => $toTxn((float) ($l['debit'] ?? 0)),
            'txn_credit'    => $toTxn((float) ($l['credit'] ?? 0)),
        ], array_merge($lines, $creditLines));
    }

    /**
     * Build revenue credit line(s) split by charge code → account mapping.
     *
     * Each invoice type has a distinct strategy:
     *
     * Revenue amounts are SSCL-inclusive (net + SSCL) because SSCL is treated as
     * embedded income. Only VAT is separated out as a liability in buildLines().
     *
     * storage-handling — netAmount (= total - VAT) is split proportionally between
     *   the two revenue accounts (4001 storage / 4002 handling) using the header's
     *   storage_subtotal : handling_subtotal ratio. Proportional split is used because
     *   the header carries a single combined sscl_amount without a per-component split.
     *
     * storage / reefer — iterate detail/line rows; revenue = subtotal + line_sscl per
     *   line. Grouped by charge_code_id → account via charge_revenue AccountMapping,
     *   falling back to the type-default account code when no mapping exists.
     *
     * repair — same per-line approach; revenue = line_amount + tax1_amount (SSCL).
     *   Different charge codes (e.g. SRV/EST vs DMR/WLD) map to different accounts.
     *
     * All strategies fall back to a single netAmount credit to the type-default
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
                // netAmount = total - VAT = subtotal + SSCL (SSCL-inclusive revenue).
                // The header stores a single combined sscl_amount with no per-component split,
                // so we allocate netAmount proportionally by storage_subtotal : handling_subtotal.
                $storagePre  = (float) ($invoice->storage_subtotal  ?? 0);
                $handlingPre = (float) ($invoice->handling_subtotal ?? 0);
                $preTotal    = $storagePre + $handlingPre;

                if ($preTotal > 0) {
                    $storageAmt  = round($netAmount * ($storagePre  / $preTotal), 2);
                    $handlingAmt = round($netAmount - $storageAmt, 2); // remainder avoids rounding gap

                    if ($storageAmt > 0) {
                        $add($this->requireDefaultRevenueAccount('storage')->id, $storageAmt, 'Storage income');
                    }
                    if ($handlingAmt > 0) {
                        $add($this->requireDefaultRevenueAccount('storage-handling')->id, $handlingAmt, 'Handling income');
                    }
                }
                break;

            case 'storage':
                // Revenue = net + SSCL per line (subtotal + line_sscl).
                $invoice->loadMissing('details');
                foreach ($invoice->details as $detail) {
                    $amt = round((float) ($detail->subtotal ?? 0) + (float) ($detail->line_sscl ?? 0), 2);
                    if ($amt <= 0) continue;
                    $acc = $this->requireChargeRevenueAccount($detail->charge_code_id, '4001', 'a storage invoice line');
                    $add($acc->id, $amt, 'Storage income');
                }
                break;

            case 'reefer':
                // Revenue = net + SSCL per line (subtotal + line_sscl).
                $invoice->loadMissing('lines');
                foreach ($invoice->lines as $line) {
                    $amt = round((float) ($line->subtotal ?? 0) + (float) ($line->line_sscl ?? 0), 2);
                    if ($amt <= 0) continue;
                    $acc = $this->requireChargeRevenueAccount($line->charge_code_id, '4004', 'a reefer invoice line');
                    $add($acc->id, $amt, 'Reefer electricity income');
                }
                break;

            case 'repair':
                // Revenue = net + SSCL per line (line_amount + tax1_amount).
                $invoice->loadMissing('lines');
                foreach ($invoice->lines as $line) {
                    $amt = round((float) ($line->line_amount ?? 0) + (float) ($line->tax1_amount ?? 0), 2);
                    if ($amt <= 0) continue;
                    $acc = $this->requireChargeRevenueAccount($line->charge_code_id, '4003', 'a repair invoice line');
                    $add($acc->id, $amt, 'Repair income');
                }
                break;

            case 'general':
                // Same shape as repair: revenue = net + SSCL per line, split by
                // charge code → revenue account (fallback 4006 Other Operational).
                $invoice->loadMissing('lines');
                foreach ($invoice->lines as $line) {
                    $amt = round((float) ($line->line_amount ?? 0) + (float) ($line->tax1_amount ?? 0), 2);
                    if ($amt <= 0) continue;
                    $acc = $this->requireChargeRevenueAccount($line->charge_code_id, '4006', 'a general invoice line');
                    $add($acc->id, $amt, 'General invoice income');
                }
                break;
        }

        // Fallback: if no per-line amounts resolved, post the full net to the type default
        if (empty($accumulator) && $netAmount > 0) {
            $add($this->requireDefaultRevenueAccount($invoiceType)->id, $netAmount, 'Revenue');
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
     * Like resolveChargeRevenueAccount() but throws instead of returning null, so a
     * revenue line whose account cannot be resolved aborts the posting rather than
     * being silently dropped (which understates AR vs the invoice total).
     */
    private function requireChargeRevenueAccount(?int $chargeCodeId, string $fallbackCode, string $context): Account
    {
        $acc = $this->resolveChargeRevenueAccount($chargeCodeId, $fallbackCode);
        if (!$acc) {
            throw new \RuntimeException(
                "No revenue account resolved for {$context} (fallback account {$fallbackCode} is missing or inactive). "
                . "Map the charge code under Account Mappings → Charge Revenue, or activate account {$fallbackCode}."
            );
        }

        return $acc;
    }

    /** Throwing variant of resolveDefaultRevenueAccount(). */
    private function requireDefaultRevenueAccount(string $invoiceType): Account
    {
        $acc = $this->resolveDefaultRevenueAccount($invoiceType);
        if (!$acc) {
            throw new \RuntimeException(
                "No default revenue account resolved for invoice type '{$invoiceType}'. "
                . 'Activate the type\'s revenue account in the Chart of Accounts.'
            );
        }

        return $acc;
    }

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
            'general'          => '4006',
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
        // Only VAT (Tax 2) is extracted as a liability. SSCL (Tax 1) is recognised
        // as part of revenue because it is levied on the service provider and embedded
        // in the service price — VAT is then calculated on (net + SSCL).
        return match ($invoiceType) {
            'repair'           => (float)($invoice->vat_total ?? 0),
            'general'          => (float)($invoice->vat_total ?? 0),
            'reefer'           => (float)($invoice->vat_amount ?? 0),
            'storage'          => (float)($invoice->vat_amount ?? 0),
            'storage-handling' => (float)($invoice->vat_amount ?? 0),
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
