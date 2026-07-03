<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ExchangeRate;
use App\Models\GlJournal;
use App\Models\ReeferElectricityInvoice;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageInvoice;
use App\Models\SupplierInvoice;
use App\Services\CurrencyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Period-end FX revaluation (Phase E).
 *
 * preview() re-prices open foreign-currency monetary balances (AR open invoices,
 * AP open bills) from their booked rate to the rate as of a chosen date and
 * reports the UNREALIZED gain/loss (read-only). post() books that revaluation as
 * a balanced journal on the as-of date plus an automatic reversing journal the
 * next day — unrealized FX must reverse, since it only becomes realized on
 * settlement.
 *
 * Convention: exchange_rate = foreign → base. For an asset (AR), a higher
 * as-of rate means the receivable is worth more in base → unrealized gain. For a
 * liability (AP), a higher as-of rate means we owe more in base → unrealized loss.
 *
 * Accounts (mapping override, else fallback):
 *   AR control  — customer_ar → 1101,  AP control — supplier_ap → 2011
 *   Unrealized gain — forex_gain_unrealized → forex_gain → 4102
 *   Unrealized loss — forex_loss_unrealized → forex_loss → 7002
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
        private PostingEngine $engine,
    ) {}

    /**
     * Whether a revaluation has already been posted for this as-of date.
     */
    public function isPosted(string $asOf): bool
    {
        return GlJournal::where('reference_type', 'fx-revaluation')
            ->where('reference_id', $this->refId($asOf))
            ->where('status', 'posted')
            ->exists();
    }

    /**
     * Post the revaluation for $asOf: a balanced adjustment journal on the as-of
     * date and an automatic reversing journal the next day. Idempotent per date
     * (throws if already posted). Both journals are posted in one transaction, so
     * if the next day's period is not open the whole thing rolls back.
     *
     * @return array{posted:bool, message?:string, journal?:string, reversal?:string, gain:float, loss:float}
     */
    public function post(string $asOf, int $userId): array
    {
        $summary = $this->preview($asOf)['summary'];
        $arDelta = round((float) $summary['ar_delta'], 2);
        $apDelta = round((float) $summary['ap_delta'], 2);

        if (abs($arDelta) < 0.01 && abs($apDelta) < 0.01) {
            return ['posted' => false, 'message' => 'No open foreign balances to revalue (nothing posted).', 'gain' => 0.0, 'loss' => 0.0];
        }

        if ($this->isPosted($asOf)) {
            throw new \RuntimeException("An FX revaluation for {$asOf} has already been posted. Void it first to re-run.");
        }

        [$arAcc, $apAcc, $gainAcc, $lossAcc] = $this->resolveAccounts();

        // AR is an asset (delta + = revalued up = debit AR); AP is a liability
        // (delta + = revalued up = credit AP). Gains and losses are shown gross.
        $gain = round(max(0.0, $arDelta) + max(0.0, -$apDelta), 2);
        $loss = round(max(0.0, -$arDelta) + max(0.0, $apDelta), 2);

        $lines = [];
        if (abs($arDelta) >= 0.01) {
            $lines[] = ['account_id' => $arAcc->id, 'debit' => max(0.0, $arDelta), 'credit' => max(0.0, -$arDelta), 'narration' => 'AR revaluation'];
        }
        if (abs($apDelta) >= 0.01) {
            $lines[] = ['account_id' => $apAcc->id, 'debit' => max(0.0, -$apDelta), 'credit' => max(0.0, $apDelta), 'narration' => 'AP revaluation'];
        }
        if ($gain >= 0.01) {
            $lines[] = ['account_id' => $gainAcc->id, 'debit' => 0.0, 'credit' => $gain, 'narration' => 'Unrealized exchange gain'];
        }
        if ($loss >= 0.01) {
            $lines[] = ['account_id' => $lossAcc->id, 'debit' => $loss, 'credit' => 0.0, 'narration' => 'Unrealized exchange loss'];
        }

        $refId        = $this->refId($asOf);
        $reversalDate = Carbon::parse($asOf)->addDay()->toDateString();

        return DB::transaction(function () use ($lines, $asOf, $reversalDate, $refId, $userId, $gain, $loss) {
            $reval = $this->engine->createJournal([
                'journal_date'   => $asOf,
                'journal_type'   => 'adjustment',
                'reference_type' => 'fx-revaluation',
                'reference_id'   => $refId,
                'narration'      => "FX revaluation as of {$asOf}",
            ], $lines);
            $this->engine->postJournal($reval, $userId);

            $reversalLines = array_map(fn ($l) => [
                'account_id' => $l['account_id'],
                'debit'      => $l['credit'],
                'credit'     => $l['debit'],
                'narration'  => 'Reversal: ' . $l['narration'],
            ], $lines);

            $reversal = $this->engine->createJournal([
                'journal_date'   => $reversalDate,
                'journal_type'   => 'adjustment',
                'reference_type' => 'fx-revaluation-reversal',
                'reference_id'   => $refId,
                'narration'      => "Reversal of FX revaluation as of {$asOf}",
            ], $reversalLines);
            $this->engine->postJournal($reversal, $userId);

            return [
                'posted'   => true,
                'journal'  => $reval->journal_no,
                'reversal' => $reversal->journal_no,
                'gain'     => $gain,
                'loss'     => $loss,
            ];
        });
    }

    /** Stable per-date key (YYYYMMDD) used to make posting idempotent. */
    private function refId(string $asOf): int
    {
        return (int) Carbon::parse($asOf)->format('Ymd');
    }

    /** @return array{0:Account,1:Account,2:Account,3:Account} [arControl, apControl, gain, loss] */
    private function resolveAccounts(): array
    {
        $map = fn (string $type, ?string $fallbackCode) =>
            AccountMapping::where('mapping_type', $type)
                ->whereNull('source_type')->whereNull('source_id')->where('is_active', true)->first()?->account
            ?? ($fallbackCode ? Account::where('code', $fallbackCode)->where('is_active', true)->first() : null);

        $ar   = $map('customer_ar', '1101');
        $ap   = $map('supplier_ap', '2011');
        $gain = $map('forex_gain_unrealized', null) ?? $map('forex_gain', '4102');
        $loss = $map('forex_loss_unrealized', null) ?? $map('forex_loss', '7002');

        foreach (['AR control' => $ar, 'AP control' => $ap, 'FX gain' => $gain, 'FX loss' => $loss] as $label => $acc) {
            if (!$acc) {
                throw new \RuntimeException("No {$label} account resolved for FX revaluation. Configure Account Mappings / Chart of Accounts.");
            }
        }

        return [$ar, $ap, $gain, $loss];
    }

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
