<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ChargeCode;
use App\Models\SupplierInvoice;
use App\Models\TaxCode;
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
                    'account_id' => $expenseAccount->id,
                    'debit'      => $expenseBase,
                    'credit'     => 0,
                    'narration'  => $line->description . $fxNote,
                ];
            }

            // DR input VAT (recoverable) — aggregated across all lines.
            $vatBase = round((float) $invoice->vat_amount * $rate, 2);
            if ($vatBase > 0) {
                // Try per-invoice tax code first (use first line's tax_code_id as representative)
                $taxCodeId  = $invoice->lines->first()?->tax_code_id;
                $vatAccount = $this->resolveInputVatAccount($taxCodeId);

                if (!$vatAccount) {
                    // No input VAT account mapped — fold into the last expense leg to keep balance
                    if (empty($lines)) {
                        throw new \RuntimeException('Cannot post VAT with no expense lines.');
                    }
                    $lines[count($lines) - 1]['debit'] = round($lines[count($lines) - 1]['debit'] + $vatBase, 2);
                } else {
                    $lines[] = [
                        'account_id' => $vatAccount->id,
                        'debit'      => $vatBase,
                        'credit'     => 0,
                        'narration'  => 'Input VAT',
                    ];
                }
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
