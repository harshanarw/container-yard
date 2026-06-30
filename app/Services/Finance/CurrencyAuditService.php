<?php

namespace App\Services\Finance;

use App\Models\ApCreditNote;
use App\Models\ArCreditNote;
use App\Models\GlJournal;
use App\Models\InvoicePosting;
use App\Models\PaymentVoucher;
use App\Models\Receipt;
use App\Models\ReeferElectricityInvoice;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageInvoice;
use App\Models\SupplierInvoice;
use App\Services\CurrencyService;

/**
 * Read-only audit of foreign-currency postings (P1 remediation, step 1).
 *
 * Surfaces journals that may have been posted before the multi-currency fixes:
 *   - base_mismatch: an invoice's posted journal base total does not match the
 *     document's expected base value — catches the old storage/storage-handling
 *     double-conversion (journal ≈ base × rate instead of base).
 *   - suspect_rate: a posted document is in a foreign currency but was booked at
 *     exchange_rate ≤ 1 — almost always the old silent 1.0 fallback, which
 *     understates the base-currency ledger.
 *
 * This service NEVER writes anything. The remediation tool consumes scan().
 */
class CurrencyAuditService
{
    /** Relative+absolute tolerance for base comparisons. */
    private const TOL_ABS = 0.05;

    private const AR_MODELS = [
        'storage'          => StorageInvoice::class,
        'storage-handling' => StorageHandlingInvoice::class,
        'reefer'           => ReeferElectricityInvoice::class,
        'repair'           => RepairInvoice::class,
    ];

    /**
     * @return array{base:string, findings:array<int,array<string,mixed>>, counts:array<string,int>}
     */
    public function scan(): array
    {
        $base     = CurrencyService::defaultCurrency();
        $findings = [];

        $this->scanArInvoices($base, $findings);
        $this->scanSupplierInvoices($base, $findings);
        $this->scanSimpleDocs($base, $findings);

        $counts = [
            'total'         => count($findings),
            'base_mismatch' => count(array_filter($findings, fn ($f) => $f['issue'] === 'base_mismatch')),
            'suspect_rate'  => count(array_filter($findings, fn ($f) => $f['issue'] === 'suspect_rate')),
        ];

        return ['base' => $base, 'findings' => $findings, 'counts' => $counts];
    }

    /** AR invoices are posted through InvoicePosting; compare journal base to expected. */
    private function scanArInvoices(string $base, array &$findings): void
    {
        InvoicePosting::where('status', 'posted')
            ->whereNotNull('journal_id')
            ->orderBy('id')
            ->chunk(200, function ($postings) use ($base, &$findings) {
                foreach ($postings as $p) {
                    $class = self::AR_MODELS[$p->invoice_type] ?? null;
                    if (!$class) {
                        continue;
                    }
                    $inv = $class::find($p->invoice_id);
                    $journal = $p->journal;
                    if (!$inv || !$journal || $journal->status !== 'posted') {
                        continue;
                    }

                    $currency = strtoupper((string) ($inv->invoice_currency ?? $inv->currency ?? $base));
                    $rate     = (float) ($inv->exchange_rate ?? 1) ?: 1.0;
                    $expected = $this->expectedArBase($inv, $p->invoice_type, $rate);
                    $actual   = (float) $journal->total_debit;

                    $this->evaluate($findings, [
                        'doc'        => $p->invoice_type,
                        'id'         => $inv->id,
                        'no'         => $inv->invoice_no ?? "#{$inv->id}",
                        'journal_no' => $journal->journal_no,
                        'journal_id' => $journal->id,
                        'currency'   => $currency,
                        'rate'       => $rate,
                        'expected'   => $expected,
                        'actual'     => $actual,
                    ], $base);
                }
            });
    }

    /** Supplier invoices carry journal_id directly; AP credited at base = total × rate. */
    private function scanSupplierInvoices(string $base, array &$findings): void
    {
        SupplierInvoice::whereNotNull('journal_id')
            ->orderBy('id')
            ->chunk(200, function ($invoices) use ($base, &$findings) {
                foreach ($invoices as $inv) {
                    $journal = GlJournal::find($inv->journal_id);
                    if (!$journal || $journal->status !== 'posted') {
                        continue;
                    }
                    $currency = strtoupper((string) ($inv->currency ?? $base));
                    $rate     = (float) ($inv->exchange_rate ?? 1) ?: 1.0;
                    $expected = round((float) ($inv->total_amount ?? 0) * ($currency === $base ? 1.0 : $rate), 2);
                    $actual   = (float) $journal->total_credit;

                    $this->evaluate($findings, [
                        'doc'        => 'supplier-invoice',
                        'id'         => $inv->id,
                        'no'         => $inv->invoice_no ?? "#{$inv->id}",
                        'journal_no' => $journal->journal_no,
                        'journal_id' => $journal->id,
                        'currency'   => $currency,
                        'rate'       => $rate,
                        'expected'   => $expected,
                        'actual'     => $actual,
                    ], $base);
                }
            });
    }

    /**
     * Receipts, vouchers and credit notes have FX legs / per-invoice relief, so a
     * journal-total comparison is noisy. For these we only raise the suspect_rate
     * flag (foreign currency booked at rate ≤ 1).
     */
    private function scanSimpleDocs(string $base, array &$findings): void
    {
        $sources = [
            ['receipt',        Receipt::class,       'receipt_no'],
            ['voucher',        PaymentVoucher::class, 'voucher_no'],
            ['ar-credit-note', ArCreditNote::class,  'credit_note_no'],
            ['ap-credit-note', ApCreditNote::class,  'credit_note_no'],
        ];

        foreach ($sources as [$label, $class, $noField]) {
            $class::whereNotNull('journal_id')
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($base, $label, $noField, &$findings) {
                    foreach ($rows as $doc) {
                        $currency = strtoupper((string) ($doc->currency ?? $base));
                        if ($currency === $base) {
                            continue;
                        }
                        $rate = (float) ($doc->exchange_rate ?? 1) ?: 1.0;
                        if ($rate > 1.0) {
                            continue;
                        }
                        $findings[] = [
                            'issue'      => 'suspect_rate',
                            'doc'        => $label,
                            'id'         => $doc->id,
                            'no'         => $doc->{$noField} ?? "#{$doc->id}",
                            'journal_no' => optional(GlJournal::find($doc->journal_id))->journal_no,
                            'currency'   => $currency,
                            'rate'       => $rate,
                            'expected'   => null,
                            'actual'     => null,
                            'note'       => "Foreign-currency {$label} booked at rate {$rate} — likely a silent 1.0 fallback.",
                        ];
                    }
                });
        }
    }

    private function expectedArBase($inv, string $type, float $rate): float
    {
        return match ($type) {
            // storage / handling persist base amounts already
            'storage', 'storage-handling' => round((float) ($inv->total_value ?? $inv->total_amount ?? 0), 2),
            // reefer persists document amounts; total_value is the base equivalent
            'reefer'                       => round((float) ($inv->total_value ?? ((float) ($inv->total_amount ?? 0) * $rate)), 2),
            // repair persists document amounts; base = grand_total × rate
            'repair'                       => round((float) ($inv->grand_total ?? 0) * $rate, 2),
            default                        => 0.0,
        };
    }

    /** Push a base_mismatch and/or suspect_rate finding for an invoice-type row. */
    private function evaluate(array &$findings, array $row, string $base): void
    {
        $expected = (float) $row['expected'];
        $actual   = (float) $row['actual'];

        if ($expected > 0 && abs($actual - $expected) > max(self::TOL_ABS, $expected * 0.0005)) {
            $ratio = $expected != 0.0 ? round($actual / $expected, 4) : null;
            $findings[] = $row + [
                'issue' => 'base_mismatch',
                'note'  => "Posted base {$actual} ≠ expected {$expected}"
                    . ($ratio ? " (ratio {$ratio}" . (abs($ratio - $row['rate']) < 0.01 ? ' ≈ exchange rate → double-conversion' : '') . ')' : ''),
            ];
        }

        if ($row['currency'] !== $base && (float) $row['rate'] <= 1.0) {
            $findings[] = $row + [
                'issue' => 'suspect_rate',
                'note'  => "Foreign-currency {$row['doc']} booked at rate {$row['rate']} — likely a silent 1.0 fallback.",
            ];
        }
    }
}
