<?php

namespace App\Services\Finance;

use App\Models\ApCreditNote;
use App\Models\ArCreditNote;
use App\Models\CompanySetting;
use App\Models\ReeferElectricityInvoice;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageInvoice;
use App\Models\SupplierInvoice;

/**
 * Builds a VAT / SSCL return for a period. Mirrors the general ledger:
 *
 *  - Output tax is collected on sales invoices (Storage, Storage & Handling,
 *    Reefer Electricity, Repair). Each carries a VAT and an SSCL amount.
 *  - Input VAT is paid on supplier bills and is recoverable; SSCL paid on
 *    purchases is a cost, NOT recoverable, so it is shown for information only
 *    and never netted against the SSCL liability.
 *  - Credit notes reverse tax. The posting engine books the whole credit-note
 *    tax_amount as VAT (account 2101 / its output|input-VAT mapping) with no
 *    SSCL component, so here a credit note reduces VAT only — matching the GL.
 *
 * All amounts are normalised to base currency (doc amount × exchange rate) so
 * the return is filed in the reporting currency and reconciles to the ledger.
 *
 *  Net VAT payable = Output VAT − Input VAT
 *  SSCL payable    = Output SSCL   (input SSCL is not creditable)
 */
class VatSsclReturnService
{
    /** Sales-invoice statuses that have raised a tax liability. */
    private const SALE_STATUSES = ['issued', 'partially_paid', 'paid', 'overdue'];

    /**
     * @return array<string,mixed>
     */
    public function build(string $from, string $to): array
    {
        $base = CompanySetting::baseCurrency();

        // ── Output tax (sales) ────────────────────────────────────────────
        $outputRows = [
            $this->saleRow('Storage', StorageInvoice::class, 'sscl_amount', 'vat_amount', $from, $to),
            $this->saleRow('Storage & Handling', StorageHandlingInvoice::class, 'sscl_amount', 'vat_amount', $from, $to),
            $this->saleRow('Reefer Electricity', ReeferElectricityInvoice::class, 'sscl_amount', 'vat_amount', $from, $to),
            $this->saleRow('Repair', RepairInvoice::class, 'sscl_total', 'vat_total', $from, $to),
        ];
        $arCn = $this->creditNoteRow('Less: AR Credit Notes', ArCreditNote::class, $from, $to);
        $outputRows[] = $arCn;

        // ── Input tax (purchases) ─────────────────────────────────────────
        $inputRows = [
            $this->supplierRow('Supplier Bills', $from, $to),
        ];
        $apCn = $this->creditNoteRow('Less: AP Credit Notes', ApCreditNote::class, $from, $to);
        $inputRows[] = $apCn;

        $outTaxable = $this->sum($outputRows, 'taxable');
        $outSscl    = $this->sum($outputRows, 'sscl');
        $outVat     = $this->sum($outputRows, 'vat');
        $inTaxable  = $this->sum($inputRows, 'taxable');
        $inSscl     = $this->sum($inputRows, 'sscl');   // informational only
        $inVat      = $this->sum($inputRows, 'vat');

        return [
            'base'   => $base,
            'from'   => $from,
            'to'     => $to,
            'output' => ['rows' => $outputRows, 'taxable' => $outTaxable, 'sscl' => $outSscl, 'vat' => $outVat],
            'input'  => ['rows' => $inputRows,  'taxable' => $inTaxable,  'sscl' => $inSscl,  'vat' => $inVat],
            'summary' => [
                'output_taxable'  => $outTaxable,
                'input_taxable'   => $inTaxable,
                'output_vat'      => $outVat,
                'input_vat'       => $inVat,
                'net_vat_payable' => round($outVat - $inVat, 2),
                'output_sscl'     => $outSscl,
                'input_sscl'      => $inSscl,   // paid on purchases — not recoverable
                'sscl_payable'    => $outSscl,
            ],
        ];
    }

    /** One row for a sales-invoice type: taxable value + SSCL + VAT (all base ccy). */
    private function saleRow(string $label, string $class, string $ssclCol, string $vatCol, string $from, string $to): array
    {
        $rows = $class::whereIn('status', self::SALE_STATUSES)
            ->whereBetween('invoice_date', [$from, $to])
            ->get(['subtotal', $ssclCol, $vatCol, 'exchange_rate']);

        $taxable = $sscl = $vat = 0.0;
        foreach ($rows as $r) {
            $rate = $this->rate($r->exchange_rate);
            $taxable += (float) $r->subtotal * $rate;
            $sscl    += (float) $r->{$ssclCol} * $rate;
            $vat     += (float) $r->{$vatCol} * $rate;
        }

        return $this->row($label, $rows->count(), $taxable, $sscl, $vat);
    }

    /** Supplier bills posted to the ledger: input taxable + SSCL (info) + input VAT. */
    private function supplierRow(string $label, string $from, string $to): array
    {
        $rows = SupplierInvoice::whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->whereNotNull('journal_id')
            ->whereBetween('invoice_date', [$from, $to])
            ->get(['subtotal', 'sscl_amount', 'vat_amount', 'exchange_rate']);

        $taxable = $sscl = $vat = 0.0;
        foreach ($rows as $r) {
            $rate = $this->rate($r->exchange_rate);
            $taxable += (float) $r->subtotal * $rate;
            $sscl    += (float) $r->sscl_amount * $rate;
            $vat     += (float) $r->vat_amount * $rate;
        }

        return $this->row($label, $rows->count(), $taxable, $sscl, $vat);
    }

    /**
     * Credit notes reverse tax. AR credit notes carry VAT only (tax_amount).
     * AP credit notes now also carry an SSCL reversal (sscl_amount), so both are
     * netted. Returned as negative amounts so they reduce the gross totals.
     */
    private function creditNoteRow(string $label, string $class, string $from, string $to): array
    {
        $hasSscl = $class === ApCreditNote::class;
        $cols    = ['subtotal', 'tax_amount', 'base_amount', 'total_amount', 'exchange_rate'];
        if ($hasSscl) {
            $cols[] = 'sscl_amount';
        }

        $rows = $class::where('status', 'approved')
            ->whereBetween('credit_date', [$from, $to])
            ->get($cols);

        $taxable = $sscl = $vat = 0.0;
        foreach ($rows as $r) {
            $rate = $this->rate($r->exchange_rate);
            $taxable += (float) $r->subtotal * $rate;
            $vat     += (float) $r->tax_amount * $rate;
            $sscl    += $hasSscl ? (float) $r->sscl_amount * $rate : 0.0;
        }

        // Negative: a credit note reduces the corresponding output/input tax.
        return $this->row($label, $rows->count(), -$taxable, -$sscl, -$vat);
    }

    private function row(string $label, int $count, float $taxable, float $sscl, float $vat): array
    {
        return [
            'label'   => $label,
            'count'   => $count,
            'taxable' => round($taxable, 2),
            'sscl'    => round($sscl, 2),
            'vat'     => round($vat, 2),
        ];
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function sum(array $rows, string $key): float
    {
        return round(array_sum(array_map(fn ($r) => (float) $r[$key], $rows)), 2);
    }

    /** Base currency per one unit of document currency; defaults to 1 for local docs. */
    private function rate($exchangeRate): float
    {
        $r = (float) $exchangeRate;

        return $r > 0 ? $r : 1.0;
    }
}
