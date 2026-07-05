<?php

namespace App\Services\Finance;

use App\Models\CompanySetting;
use App\Models\PaymentVoucher;
use App\Models\Receipt;

/**
 * Withholding-tax report for a period, in base currency.
 *
 *  - Payable: WHT we deducted from suppliers on payment vouchers and must remit
 *    to the IRD (the basis for the WHT certificates we issue to suppliers).
 *  - Receivable: WHT customers deducted from their payments to us — a credit we
 *    can claim against our own income tax.
 *
 * Only confirmed (GL-posted) documents with a WHT amount are included, grouped
 * by party for certificate/remittance purposes.
 */
class WhtReportService
{
    public function build(string $from, string $to): array
    {
        return [
            'base'       => CompanySetting::baseCurrency(),
            'from'       => $from,
            'to'         => $to,
            'payable'    => $this->payable($from, $to),
            'receivable' => $this->receivable($from, $to),
        ];
    }

    /** WHT deducted from suppliers (payment vouchers). */
    private function payable(string $from, string $to): array
    {
        $vouchers = PaymentVoucher::with('supplier')
            ->where('status', 'confirmed')
            ->where('wht_amount', '>', 0)
            ->whereBetween('voucher_date', [$from, $to])
            ->orderBy('voucher_date')->get();

        $rows = $vouchers->map(fn ($v) => $this->row(
            $v->voucher_date, $v->voucher_no, optional($v->supplier)->name ?? $v->payee_name,
            optional($v->supplier)->id, $v->wht_type, $v->wht_rate,
            (float) $v->amount, (float) $v->wht_amount, (float) ($v->exchange_rate ?: 1)
        ));

        return $this->group($rows);
    }

    /** WHT customers deducted from payments to us (receipts). */
    private function receivable(string $from, string $to): array
    {
        $receipts = Receipt::with('customer')
            ->where('status', 'confirmed')
            ->where('wht_amount', '>', 0)
            ->whereBetween('receipt_date', [$from, $to])
            ->orderBy('receipt_date')->get();

        $rows = $receipts->map(fn ($r) => $this->row(
            $r->receipt_date, $r->receipt_no, optional($r->customer)->name ?? '—',
            optional($r->customer)->id, $r->wht_type, $r->wht_rate,
            (float) $r->amount, (float) $r->wht_amount, (float) ($r->exchange_rate ?: 1)
        ));

        return $this->group($rows);
    }

    private function row($date, ?string $no, string $party, ?int $partyId, ?string $whtType, $rate, float $gross, float $wht, float $fx): array
    {
        return [
            'date'      => ($date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date))->toDateString(),
            'no'        => $no ?? '—',
            'party'     => $party,
            'party_id'  => $partyId ?? 0,
            'nature'    => $this->natureLabel($whtType),
            'rate'      => round((float) $rate, 2),
            'gross'     => round($gross * $fx, 2),   // base currency
            'wht'       => round($wht * $fx, 2),
            'net'       => round(($gross - $wht) * $fx, 2),
        ];
    }

    /** Group rows by party with subtotals, plus grand totals. */
    private function group($rows): array
    {
        $groups = collect($rows)->groupBy('party_id')->map(function ($items) {
            return [
                'party' => $items->first()['party'],
                'rows'  => $items->values()->all(),
                'gross' => round($items->sum('gross'), 2),
                'wht'   => round($items->sum('wht'), 2),
                'net'   => round($items->sum('net'), 2),
            ];
        })->sortBy('party')->values()->all();

        return [
            'parties' => $groups,
            'gross'   => round(collect($rows)->sum('gross'), 2),
            'wht'     => round(collect($rows)->sum('wht'), 2),
            'net'     => round(collect($rows)->sum('net'), 2),
            'count'   => count($rows),
        ];
    }

    private function natureLabel(?string $code): string
    {
        if (!$code) {
            return '—';
        }
        foreach (config('wht.types', []) as $t) {
            if (($t['code'] ?? null) === $code) {
                return $t['label'];
            }
        }

        return ucfirst(str_replace('_', ' ', $code));
    }
}
