<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ChargeCode;
use App\Models\SupplierInvoice;
use App\Models\TaxCode;
use App\Services\CurrencyService;
use Illuminate\Support\Facades\DB;

/**
 * Posts a supplier (purchase) invoice to the GL — the AP counterpart to
 * InvoicePostingService.
 *
 *   DR  Expense account(s)       net + SSCL (SSCL is an irrecoverable cost, same as AR treatment)
 *   DR  Input VAT Receivable     VAT only (recoverable)
 *   CR  Trade Creditors (AP)     gross total
 *
 * Expense account is resolved per line via charge_expense AccountMapping keyed to
 * the line's charge_code_id. Falls back to expense_account_id when no mapping exists.
 * Input VAT account is resolved via tax_input AccountMapping keyed to tax_code_id,
 * then the global tax_input mapping, then the default account (1301).
 */
class SupplierInvoicePostingService
{
    public function __construct(private PostingEngine $engine) {}

    public function post(SupplierInvoice $invoice, int $userId): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice, $userId) {
            $locked = SupplierInvoice::where('id', $invoice->id)->lockForUpdate()->first();
            if ($locked->isPosted()) {
                throw new \RuntimeException(
                    "Supplier invoice {$invoice->invoice_no} is already posted to journal "
                    . ($locked->journal->journal_no ?? '?') . '.'
                );
            }

            $invoice->loadMissing('lines');
            if ($invoice->lines->isEmpty()) {
                throw new \InvalidArgumentException('Supplier invoice has no line items to post.');
            }

            $rate   = (float) ($invoice->exchange_rate ?: 1);
            $fxNote = $this->fxNote($invoice->currency, $rate);
            $lines  = [];

            // Document (transaction) currency for the per-line multi-currency amounts.
            // Supplier invoices store document amounts; base = document × rate, so the
            // transaction amount of any base figure is base ÷ docRate.
            $base    = CurrencyService::defaultCurrency();
            $docCcy  = strtoupper((string) ($invoice->currency ?? $base));
            $docRate = $docCcy === $base ? 1.0 : ($rate ?: 1.0);
            $toTxn   = fn (float $baseAmt) => $docRate > 0 ? round($baseAmt / $docRate, 2) : $baseAmt;

            // DR expense lines — one per line item; amount = (net + SSCL) in base currency.
            // SSCL (tax1) is an irrecoverable levy embedded in the cost of the service.
            foreach ($invoice->lines as $line) {
                $expenseBase = round(((float) $line->amount + (float) $line->tax1_amount) * $rate, 2);
                if ($expenseBase == 0.0) {
                    continue;
                }

                $expenseAccount = $this->resolveExpenseAccount($line->charge_code_id, $line->expense_account_id);
                if (!$expenseAccount) {
                    throw new \RuntimeException(
                        "No expense account for line \"{$line->description}\". "
                        . 'Configure Account Mappings → Charge Expense or set an account on the line.'
                    );
                }

                $lines[] = [
                    'account_id'   => $expenseAccount->id,
                    // Job costing dimension — the cost lands against the job/container.
                    'job_id'       => $line->yard_job_id,
                    'container_id' => $line->container_id,
                    'debit'        => $expenseBase,
                    'credit'       => 0,
                    'narration'    => $line->description . $fxNote,
                ];
            }

            // DR input VAT (recoverable) — resolved per line by tax code so mixed-rate
            // invoices post each VAT portion to the correct input-tax account.
            $vatByAccount = []; // account_id => accumulated amount
            foreach ($invoice->lines as $line) {
                $lineVatBase = round((float) $line->tax2_amount * $rate, 2);
                if ($lineVatBase <= 0) continue;

                $vatAccount = $this->resolveInputVatAccount($line->tax_code_id);
                if (!$vatAccount) {
                    throw new \RuntimeException(
                        'No input VAT account mapped. Configure Account Mappings → Tax Input or create an account with code 1301.'
                    );
                }
                $vatByAccount[$vatAccount->id] = round(($vatByAccount[$vatAccount->id] ?? 0) + $lineVatBase, 2);
            }

            foreach ($vatByAccount as $acctId => $vatAmt) {
                $lines[] = [
                    'account_id' => $acctId,
                    'debit'      => $vatAmt,
                    'credit'     => 0,
                    'narration'  => 'Input VAT',
                ];
            }

            if (empty($lines)) {
                throw new \InvalidArgumentException('Supplier invoice total must be greater than zero.');
            }

            // CR AP control — sum of ALL debit legs (computed last to avoid rounding gaps)
            $totalDr   = round(array_sum(array_column($lines, 'debit')), 2);
            $apAccount = $this->resolveApAccount();
            if (!$apAccount) {
                throw new \RuntimeException(
                    'No AP control account mapped. Configure Account Mappings → AR/AP Controls.'
                );
            }

            $lines[] = [
                'account_id' => $apAccount->id,
                'debit'      => 0,
                'credit'     => $totalDr,
                'narration'  => 'Trade creditors — ' . ($invoice->supplier->name ?? 'Supplier'),
            ];

            // Attach transaction-currency metadata to every line (base stays primary).
            $lines = array_map(fn ($l) => $l + [
                'currency'      => $docCcy,
                'exchange_rate' => $docRate,
                'txn_debit'     => $toTxn((float) ($l['debit'] ?? 0)),
                'txn_credit'    => $toTxn((float) ($l['credit'] ?? 0)),
            ], $lines);

            $journal = $this->engine->createJournal([
                'journal_date'   => $invoice->invoice_date->toDateString(),
                'journal_type'   => 'invoice',
                'reference_type' => SupplierInvoice::class,
                'reference_id'   => $invoice->id,
                'narration'      => "Supplier Invoice {$invoice->invoice_no} — " . ($invoice->supplier->name ?? ''),
            ], $lines);

            $this->engine->postJournal($journal, $userId);

            $invoice->update([
                'journal_id'    => $journal->id,
                'posting_error' => null,
            ]);

            return $invoice->fresh(['journal', 'supplier', 'lines']);
        });
    }

    public function void(SupplierInvoice $invoice, int $userId, string $reason = ''): void
    {
        if (!$invoice->isPosted()) {
            throw new \RuntimeException(
                "Supplier invoice {$invoice->invoice_no} is not posted — nothing to void."
            );
        }

        DB::transaction(function () use ($invoice, $userId, $reason) {
            $this->engine->voidJournal($invoice->journal->load('entries'), $userId, $reason);
            $invoice->update(['journal_id' => null]);
        });
    }

    // ─── Account resolution ───────────────────────────────────────────────────

    /**
     * Resolve expense account for a line: charge_expense mapping → expense_account_id fallback.
     */
    private function resolveExpenseAccount(?int $chargeCodeId, ?int $fallbackAccountId): ?Account
    {
        if ($chargeCodeId !== null) {
            $mapped = AccountMapping::where('mapping_type', 'charge_expense')
                ->where('source_type', ChargeCode::class)
                ->where('source_id', $chargeCodeId)
                ->where('is_active', true)
                ->first()?->account;

            if ($mapped) {
                return $mapped;
            }
        }

        return $fallbackAccountId ? Account::find($fallbackAccountId) : null;
    }

    /**
     * Resolve input VAT account: per-TaxCode tax_input mapping → global tax_input → default 1301.
     */
    private function resolveInputVatAccount(?int $taxCodeId): ?Account
    {
        if ($taxCodeId !== null) {
            $mapped = AccountMapping::where('mapping_type', 'tax_input')
                ->where('source_type', TaxCode::class)
                ->where('source_id', $taxCodeId)
                ->where('is_active', true)
                ->first()?->account;

            if ($mapped) {
                return $mapped;
            }
        }

        // Global (non-keyed) input tax mapping
        $global = AccountMapping::where('mapping_type', 'tax_input')
            ->whereNull('source_type')->whereNull('source_id')
            ->where('is_active', true)
            ->first()?->account;

        return $global ?? Account::where('code', '1301')->where('is_active', true)->first();
    }

    private function resolveApAccount(): ?Account
    {
        $mapping = AccountMapping::where('mapping_type', 'supplier_ap')
            ->whereNull('source_type')->whereNull('source_id')
            ->where('is_active', true)->first();

        return $mapping?->account
            ?? Account::where('code', '2011')->where('is_active', true)->first();
    }

    private function fxNote(?string $currency, float $rate): string
    {
        if ($rate == 1.0) {
            return '';
        }

        return ' (@ ' . rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.') . ' ' . ($currency ?? '') . ')';
    }
}
