<?php

namespace App\Services\Finance;

use App\Models\ApCreditNote;
use App\Models\ArCreditNote;
use App\Models\CompanySetting;
use App\Models\PaymentVoucher;
use App\Models\Receipt;
use App\Models\ReeferElectricityInvoice;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageInvoice;
use App\Models\SupplierInvoice;
use Carbon\Carbon;

/**
 * Builds a party statement of account (opening balance → dated transactions with
 * a running balance → closing balance). All amounts are normalised to base
 * currency so the statement reconciles to the AR/AP sub-ledger and aging; the
 * document currency + amount are carried for reference.
 *
 * Convention for both sides: "debit" = a charge that increases the balance the
 * party owes/we owe, "credit" = a settlement that reduces it. Closing balance
 * therefore equals the outstanding amount.
 */
class StatementService
{
    public function __construct(
        private ArAllocationService $ar,
        private ApAllocationService $ap,
    ) {}

    /** Customer (AR) statement: invoices (Dr) vs receipts + AR credit notes (Cr). */
    public function customer(int $customerId, string $from, string $to): array
    {
        return $this->assemble($this->customerTransactions($customerId), $from, $to);
    }

    /** Supplier (AP) statement: supplier bills (Dr) vs payments + AP credit notes (Cr). */
    public function supplier(int $supplierId, string $from, string $to): array
    {
        return $this->assemble($this->supplierTransactions($supplierId), $from, $to);
    }

    /** @return array<int,array<string,mixed>> */
    private function customerTransactions(int $id): array
    {
        $rows = [];

        // AR invoices (posted-to-ledger statuses) — one debit per invoice.
        $arTypes = [
            'storage'          => [StorageInvoice::class,           'customer_id'],
            'storage-handling' => [StorageHandlingInvoice::class,   'shipping_line_id'],
            'reefer'           => [ReeferElectricityInvoice::class, 'customer_id'],
            'repair'           => [RepairInvoice::class,            'customer_id'],
            'general'          => [\App\Models\GeneralInvoice::class, 'billing_party_id'],
        ];
        foreach ($arTypes as $type => [$class, $col]) {
            $class::where($col, $id)
                ->whereIn('status', ['issued', 'partially_paid', 'paid', 'overdue'])
                ->get()
                ->each(function ($inv) use ($type, &$rows) {
                    $cb = $this->ar->currencyBreakdown($inv, $type);
                    $rows[] = $this->row($inv->invoice_date, 'Invoice', ucwords(str_replace('-', ' ', $type)),
                        $inv->invoice_no, $cb['base_total'], 0.0, $cb['currency'], $cb['doc_total']);
                });
        }

        // Receipts (credits) — confirmed only.
        Receipt::where('customer_id', $id)->where('status', 'confirmed')->get()
            ->each(function ($r) use (&$rows) {
                $rows[] = $this->row($r->receipt_date, 'Receipt', 'Payment',
                    $r->receipt_no, 0.0, $this->base($r->base_amount, $r->amount, $r->exchange_rate),
                    $r->currency, $r->amount);
            });

        // AR credit notes (credits) — approved only.
        ArCreditNote::where('customer_id', $id)->where('status', 'approved')->get()
            ->each(function ($cn) use (&$rows) {
                $rows[] = $this->row($cn->credit_date, 'Credit Note', 'Credit',
                    $cn->credit_note_no, 0.0, $this->base($cn->base_amount, $cn->total_amount, $cn->exchange_rate),
                    $cn->currency, $cn->total_amount);
            });

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private function supplierTransactions(int $id): array
    {
        $rows = [];

        // Supplier bills (debits) — posted to the ledger.
        SupplierInvoice::where('customer_id', $id)
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->whereNotNull('journal_id')
            ->get()
            ->each(function ($inv) use (&$rows) {
                $cb = $this->ap->currencyBreakdown($inv);
                $rows[] = $this->row($inv->invoice_date, 'Bill', 'Supplier Invoice',
                    $inv->invoice_no, $cb['base_total'], 0.0, $cb['currency'], $cb['doc_total']);
            });

        // Payment vouchers (credits) — anything not draft/cancelled/voided.
        PaymentVoucher::where('customer_id', $id)
            ->whereNotIn('status', ['draft', 'cancelled', 'voided'])
            ->get()
            ->each(function ($v) use (&$rows) {
                $rows[] = $this->row($v->voucher_date, 'Payment', 'Voucher',
                    $v->voucher_no, 0.0, $this->base($v->base_amount, $v->amount, $v->exchange_rate),
                    $v->currency, $v->amount);
            });

        // AP credit notes (credits) — approved only.
        ApCreditNote::where('customer_id', $id)->where('status', 'approved')->get()
            ->each(function ($cn) use (&$rows) {
                $rows[] = $this->row($cn->credit_date, 'Debit Note', 'AP Credit Note',
                    $cn->credit_note_no, 0.0, $this->base($cn->base_amount, $cn->total_amount, $cn->exchange_rate),
                    $cn->currency, $cn->total_amount);
            });

        return $rows;
    }

    /** Assemble opening balance, the in-period running ledger, and the closing balance. */
    private function assemble(array $rows, string $from, string $to): array
    {
        $opening = 0.0;
        foreach ($rows as $r) {
            if ($r['date'] < $from) {
                $opening += $r['debit'] - $r['credit'];
            }
        }
        $opening = round($opening, 2);

        $period = array_values(array_filter($rows, fn ($r) => $r['date'] >= $from && $r['date'] <= $to));
        usort($period, fn ($a, $b) => [$a['date'], $a['type']] <=> [$b['date'], $b['type']]);

        $running = $opening;
        $debitTotal = 0.0;
        $creditTotal = 0.0;
        $lines = [];
        foreach ($period as $r) {
            $running = round($running + $r['debit'] - $r['credit'], 2);
            $debitTotal  += $r['debit'];
            $creditTotal += $r['credit'];
            $lines[] = $r + ['balance' => $running];
        }

        return [
            'base'         => CompanySetting::baseCurrency(),
            'opening'      => $opening,
            'lines'        => $lines,
            'debit_total'  => round($debitTotal, 2),
            'credit_total' => round($creditTotal, 2),
            'closing'      => round($running, 2),
        ];
    }

    private function row($date, string $type, string $sub, ?string $ref, float $debit, float $credit, ?string $currency, $docAmount): array
    {
        return [
            'date'       => ($date instanceof Carbon ? $date : Carbon::parse($date))->toDateString(),
            'type'       => $type,
            'sub'        => $sub,
            'ref'        => $ref ?? '—',
            'debit'      => round($debit, 2),
            'credit'     => round($credit, 2),
            'currency'   => strtoupper((string) ($currency ?: CompanySetting::baseCurrency())),
            'doc_amount' => round((float) $docAmount, 2),
        ];
    }

    /** Base-currency value of a settlement: prefer the stored base_amount, else amount × rate. */
    private function base($baseAmount, $amount, $rate): float
    {
        $b = (float) ($baseAmount ?? 0);
        if ($b > 0) {
            return round($b, 2);
        }
        return round((float) $amount * ((float) ($rate ?: 1) ?: 1.0), 2);
    }
}
