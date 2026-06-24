<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Posts a supplier (purchase) invoice to the GL — the AP counterpart to
 * InvoicePostingService.
 *
 *   DR  Expense / Asset account(s)   (one leg per invoice line, net of tax)
 *   DR  Input Tax Receivable          (recoverable tax, if any)
 *   CR  Trade Creditors (AP Control)  (gross total)
 *
 * Amounts are converted to base/reporting currency via exchange_rate so the
 * GL stays single-currency. The credit leg is built from the exact sum of the
 * debit legs, so the journal is balanced by construction.
 */
class SupplierInvoicePostingService
{
    public function __construct(private PostingEngine $engine) {}

    public function post(SupplierInvoice $invoice, int $userId): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice, $userId) {
            // Lock + guard against a concurrent double-post.
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

            $rate    = (float) ($invoice->exchange_rate ?: 1);
            $fxNote  = $this->fxNote($invoice->currency, $rate);
            $lines   = [];
            $totalDr = 0.0;

            // DR each expense/asset line (net), converted to base currency.
            foreach ($invoice->lines as $line) {
                $base = round((float) $line->amount * $rate, 2);
                if ($base == 0.0) {
                    continue;
                }
                $totalDr += $base;
                $lines[] = [
                    'account_id' => $line->expense_account_id,
                    'debit'      => $base,
                    'credit'     => 0,
                    'narration'  => $line->description . $fxNote,
                ];
            }

            // DR input tax (recoverable), if any.
            $taxBase = round((float) $invoice->tax_amount * $rate, 2);
            if ($taxBase > 0) {
                $taxAccount = $this->resolveTaxInputAccount();
                if (!$taxAccount) {
                    // No input-tax account mapped — fold tax into the last expense leg
                    // so the journal still balances rather than silently dropping it.
                    if (empty($lines)) {
                        throw new \RuntimeException('Cannot post tax with no expense lines.');
                    }
                    $lines[count($lines) - 1]['debit'] = round($lines[count($lines) - 1]['debit'] + $taxBase, 2);
                } else {
                    $lines[] = [
                        'account_id' => $taxAccount->id,
                        'debit'      => $taxBase,
                        'credit'     => 0,
                        'narration'  => 'Input tax',
                    ];
                }
                $totalDr += $taxBase;
            }

            if ($totalDr <= 0) {
                throw new \InvalidArgumentException('Supplier invoice total must be greater than zero.');
            }

            // CR AP control — recompute from the FINAL debit legs (the tax-fallback
            // path may have re-rounded the last leg) so the credit exactly matches
            // what was debited and the journal balances to the cent.
            $totalDr = round(array_sum(array_column($lines, 'debit')), 2);

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

    /**
     * Void the posted journal for a supplier invoice (used on cancel).
     */
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

    private function fxNote(?string $currency, float $rate): string
    {
        if ($rate == 1.0) {
            return '';
        }

        return ' (@ ' . rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.') . ' ' . ($currency ?? '') . ')';
    }

    private function resolveApAccount(): ?Account
    {
        $mapping = AccountMapping::where('mapping_type', 'supplier_ap')
            ->whereNull('source_type')->whereNull('source_id')
            ->where('is_active', true)->first();

        return $mapping?->account
            ?? Account::where('code', '2011')->where('is_active', true)->first();
    }

    private function resolveTaxInputAccount(): ?Account
    {
        $mapping = AccountMapping::where('mapping_type', 'tax_input')
            ->where('is_active', true)->first();

        return $mapping?->account
            ?? Account::where('code', '1301')->where('is_active', true)->first();
    }
}
