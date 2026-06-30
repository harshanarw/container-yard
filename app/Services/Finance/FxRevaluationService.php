<?php

namespace App\Services\Finance;

use App\Models\ExchangeRate;
use App\Models\ReeferElectricityInvoice;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageInvoice;
use App\Models\SupplierInvoice;
use App\Services\CurrencyService;

/**
 * Period-end FX revaluation — PREVIEW ONLY (Phase E, step 1).
 *
 * Re-prices open foreign-currency monetary balances (AR open invoices, AP open
 * bills) from their booked rate to the rate as of a chosen date, and reports the
 * UNREALIZED gain/loss. This service makes NO GL changes — posting the
 * (reversing) revaluation journal is a separate, sign-off-gated step.
 *
 * Convention: exchange_rate = foreign → base. For an asset (AR), a higher
 * as-of rate means the receivable is worth more in base → unrealized gain. For a
 * liability (AP), a higher as-of rate means we owe more in base → unrealized loss.
 */
class FxRevaluationService
{
    private const AR_SOURCES = [
        ['storage',          StorageInvoice::class,          ['issued']],
        ['storage-handling', StorageHandlingInvoice::class,  ['issued']],
        ['reefer',           ReeferElectricityInvoice::class, ['issued']],
        ['repair',           RepairInvoice::class,           ['issued', 'partially_paid', 'overdue']],
    ];

    public function __construct(
        private ArAllocationService $ar,
        private ApAllocationService $ap,
    ) {}

    /**
     * @return array{
     *   as_of:string, base:string,
     *   items:array<int,array<string,mixed>>,
     *   missing:array<int,array<string,mixed>>,
     *   summary:array<string,float>
     * }
     */
    public function preview(string $asOf): array
    {
        $base    = CurrencyService::defaultCurrency();
        $items   = [];
        $missing = [];

        foreach (self::AR_SOURCES as [$type, $class, $statuses]) {
            foreach ($class::whereIn('status', $statuses)->orderBy('id')->get() as $inv) {
                $cb = $this->ar->currencyBreakdown($inv, $type);
                $this->consider('AR', $type, $inv->invoice_no ?? "#{$inv->id}", $inv->id, $cb, $asOf, $base, $items, $missing);
            }
        }

        foreach (SupplierInvoice::whereIn('status', ['approved', 'partially_paid'])
            ->whereNotNull('journal_id')->orderBy('id')->get() as $inv) {
            $cb = $this->ap->currencyBreakdown($inv);
            $this->consider('AP', 'supplier-invoice', $inv->invoice_no ?? "#{$inv->id}", $inv->id, $cb, $asOf, $base, $items, $missing);
        }

        $col = fn (string $side, string $key) => collect($items)->where('side', $side)->sum($key);

        $arDelta = round((float) $col('AR', 'delta'), 2);   // asset: + = gain
        $apDelta = round((float) $col('AP', 'delta'), 2);   // liability: + = loss
        $net     = round($arDelta - $apDelta, 2);           // + = net unrealized gain

        $summary = [
            'ar_booked'    => round((float) $col('AR', 'booked_base'), 2),
            'ar_revalued'  => round((float) $col('AR', 'revalued_base'), 2),
            'ar_delta'     => $arDelta,
            'ap_booked'    => round((float) $col('AP', 'booked_base'), 2),
            'ap_revalued'  => round((float) $col('AP', 'revalued_base'), 2),
            'ap_delta'     => $apDelta,
            'net_gain'     => $net,
        ];

        return ['as_of' => $asOf, 'base' => $base, 'items' => $items, 'missing' => $missing, 'summary' => $summary];
    }

    private function consider(
        string $side, string $type, string $no, int $id, array $cb,
        string $asOf, string $base, array &$items, array &$missing
    ): void {
        if ($cb['currency'] === $base || $cb['doc_outstanding'] <= 0) {
            return;
        }

        $asofRate = ExchangeRate::getRate($cb['currency'], $base, $asOf);
        if (!$asofRate || $asofRate <= 0) {
            $missing[] = [
                'side' => $side, 'type' => $type, 'no' => $no, 'id' => $id,
                'currency' => $cb['currency'], 'doc_outstanding' => $cb['doc_outstanding'],
            ];
            return;
        }

        $bookedBase   = round((float) $cb['base_outstanding'], 2);
        $revaluedBase = round((float) $cb['doc_outstanding'] * (float) $asofRate, 2);

        $items[] = [
            'side'            => $side,
            'type'           => $type,
            'no'             => $no,
            'id'             => $id,
            'currency'       => $cb['currency'],
            'doc_outstanding'=> round((float) $cb['doc_outstanding'], 2),
            'booked_rate'    => round((float) $cb['rate'], 6),
            'asof_rate'      => round((float) $asofRate, 6),
            'booked_base'    => $bookedBase,
            'revalued_base'  => $revaluedBase,
            'delta'          => round($revaluedBase - $bookedBase, 2),
        ];
    }
}
