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
        private PeriodManager $periods,
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
        $preview   = $this->preview($asOf);
        $summary   = $preview['summary'];
        $arDelta   = round((float) $summary['ar_delta'], 2);
        $apDelta   = round((float) $summary['ap_delta'], 2);
        $bankDelta = round((float) $summary['bank_delta'], 2);
        $bankItems = collect($preview['items'])->where('side', 'BANK')
            ->filter(fn ($i) => abs((float) $i['delta']) >= 0.01)->values();

        if (abs($arDelta) < 0.01 && abs($apDelta) < 0.01 && abs($bankDelta) < 0.01) {
            return ['posted' => false, 'message' => 'No open foreign balances to revalue (nothing posted).', 'gain' => 0.0, 'loss' => 0.0];
        }

        if ($this->isPosted($asOf)) {
            throw new \RuntimeException("An FX revaluation for {$asOf} has already been posted. Void it first to re-run.");
        }

        // The revaluation reverses the next day, so that day's period must be open
        // too — most often a problem at year-end, when the reversal falls into a new
        // financial year that has not been created/opened yet. Check up front and
        // explain, instead of letting the reversal fail deep inside the transaction.
        $reversalDate = Carbon::parse($asOf)->addDay();
        if (!$this->periods->canPost($reversalDate)) {
            throw new \RuntimeException(
                "This revaluation as of {$asOf} reverses on {$reversalDate->toDateString()}, but that date has no open "
                . "accounting period. Create/open the next period (or the next financial year, at year-end) before posting."
            );
        }

        [$arAcc, $apAcc, $gainAcc, $lossAcc] = $this->resolveAccounts();

        // AR and cash/bank are assets (delta + = revalued up = debit the account);
        // AP is a liability (delta + = revalued up = credit AP). Each cash/bank
        // account is adjusted on its own GL account. Gains/losses are shown gross.
        $bankGain = round((float) $bankItems->sum(fn ($i) => max(0.0, (float) $i['delta'])), 2);
        $bankLoss = round((float) $bankItems->sum(fn ($i) => max(0.0, -(float) $i['delta'])), 2);
        $gain = round(max(0.0, $arDelta) + max(0.0, -$apDelta) + $bankGain, 2);
        $loss = round(max(0.0, -$arDelta) + max(0.0, $apDelta) + $bankLoss, 2);

        $lines = [];
        if (abs($arDelta) >= 0.01) {
            $lines[] = ['account_id' => $arAcc->id, 'debit' => max(0.0, $arDelta), 'credit' => max(0.0, -$arDelta), 'narration' => 'AR revaluation'];
        }
        if (abs($apDelta) >= 0.01) {
            $lines[] = ['account_id' => $apAcc->id, 'debit' => max(0.0, -$apDelta), 'credit' => max(0.0, $apDelta), 'narration' => 'AP revaluation'];
        }
        foreach ($bankItems as $bi) {
            $d = (float) $bi['delta'];
            $lines[] = ['account_id' => (int) $bi['account_id'], 'debit' => max(0.0, $d), 'credit' => max(0.0, -$d), 'narration' => 'Cash/bank revaluation — ' . $bi['no']];
        }
        if ($gain >= 0.01) {
            $lines[] = ['account_id' => $gainAcc->id, 'debit' => 0.0, 'credit' => $gain, 'narration' => 'Unrealized exchange gain'];
        }
        if ($loss >= 0.01) {
            $lines[] = ['account_id' => $lossAcc->id, 'debit' => $loss, 'credit' => 0.0, 'narration' => 'Unrealized exchange loss'];
        }

        $refId           = $this->refId($asOf);
        $reversalDateStr = $reversalDate->toDateString();

        return DB::transaction(function () use ($lines, $asOf, $reversalDateStr, $refId, $userId, $gain, $loss) {
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
                'journal_date'   => $reversalDateStr,
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

    /**
     * Void a posted revaluation. It MUST void both journals — the adjustment and
     * its automatic next-day reversal — together: they net to zero, so voiding only
     * the adjustment (e.g. from the generic journal list) would leave the reversal
     * live and throw the ledger off by the revaluation amount. Voiding both is
     * balanced whichever way the void reversals fall.
     *
     * @return array{voided:int, journals:array<int,string>}
     */
    public function voidRevaluation(string $asOf, int $userId, string $reason = ''): array
    {
        $refId = $this->refId($asOf);

        $journals = GlJournal::whereIn('reference_type', ['fx-revaluation', 'fx-revaluation-reversal'])
            ->where('reference_id', $refId)
            ->where('status', 'posted')
            ->get();

        if ($journals->isEmpty()) {
            throw new \RuntimeException("No posted FX revaluation found for {$asOf} to void.");
        }

        return DB::transaction(function () use ($journals, $userId, $reason, $asOf) {
            $nos = [];
            foreach ($journals as $j) {
                $reversal = $this->engine->voidJournal($j->load('entries'), $userId, $reason ?: "Void FX revaluation as of {$asOf}");
                // voidJournal copies the original reference_type onto its reversal.
                // Re-tag it with a "-void" suffix so isPosted() (an exact match on
                // 'fx-revaluation') no longer sees a live revaluation and re-running
                // is allowed. Bank reconciliation still excludes it via its
                // 'fx-revaluation%' prefix filter.
                $reversal->update(['reference_type' => $j->reference_type . '-void']);
                $nos[] = $j->journal_no;
            }

            return ['voided' => count($nos), 'journals' => $nos];
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

        // Open foreign-currency cash/bank balances, taken straight from the posted
        // ledger (foreign balance from the transaction-currency legs, revalued to
        // base). AR/AP control accounts are excluded — they are revalued above.
        foreach ($this->bankBalances($base) as $bal) {
            $this->considerBank($bal, $asOf, $base, $items, $missing);
        }

        $col = fn (string $side, string $key) => collect($items)->where('side', $side)->sum($key);

        $arDelta   = round((float) $col('AR', 'delta'), 2);     // asset: + = gain
        $apDelta   = round((float) $col('AP', 'delta'), 2);     // liability: + = loss
        $bankDelta = round((float) $col('BANK', 'delta'), 2);   // asset: + = gain
        $net       = round($arDelta - $apDelta + $bankDelta, 2); // + = net unrealized gain

        $summary = [
            'ar_booked'     => round((float) $col('AR', 'booked_base'), 2),
            'ar_revalued'   => round((float) $col('AR', 'revalued_base'), 2),
            'ar_delta'      => $arDelta,
            'ap_booked'     => round((float) $col('AP', 'booked_base'), 2),
            'ap_revalued'   => round((float) $col('AP', 'revalued_base'), 2),
            'ap_delta'      => $apDelta,
            'bank_booked'   => round((float) $col('BANK', 'booked_base'), 2),
            'bank_revalued' => round((float) $col('BANK', 'revalued_base'), 2),
            'bank_delta'    => $bankDelta,
            'net_gain'      => $net,
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

    /**
     * Foreign-currency balances held in cash/bank GL accounts, one row per
     * (account, currency), taken from posted journals. The foreign balance is the
     * net of the transaction-currency legs; the base balance is the net of the
     * base legs. AR/AP control accounts are not cash/bank, so they are excluded.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    private function bankBalances(string $base): \Illuminate\Support\Collection
    {
        return DB::table('gl_entries')
            ->join('accounts', 'accounts.id', '=', 'gl_entries.account_id')
            ->join('gl_journals', 'gl_journals.id', '=', 'gl_entries.journal_id')
            ->where('accounts.is_cash_bank', true)
            ->whereIn('gl_journals.status', GlJournal::COUNTED_STATUSES)
            ->whereRaw('UPPER(COALESCE(gl_entries.currency, ?)) <> ?', [$base, strtoupper($base)])
            ->groupBy('gl_entries.account_id', 'gl_entries.currency', 'accounts.code', 'accounts.name')
            ->selectRaw(
                'gl_entries.account_id as account_id,
                 UPPER(gl_entries.currency) as currency,
                 accounts.code as code,
                 accounts.name as name,
                 SUM(gl_entries.txn_debit) - SUM(gl_entries.txn_credit) as foreign_balance,
                 SUM(gl_entries.debit) - SUM(gl_entries.credit) as base_balance'
            )
            ->get();
    }

    /**
     * Revalue one cash/bank foreign holding: base value implied by the ledger vs
     * (foreign units × as-of rate). Treated as an asset (delta + = gain).
     */
    private function considerBank(object $bal, string $asOf, string $base, array &$items, array &$missing): void
    {
        $foreign = round((float) $bal->foreign_balance, 4);
        if (abs($foreign) < 0.0001) {
            return; // fully settled — no open foreign balance
        }

        $asofRate = ExchangeRate::getRate($bal->currency, $base, $asOf);
        if (!$asofRate || $asofRate <= 0) {
            $missing[] = [
                'side' => 'BANK', 'type' => 'cash-bank',
                'no' => trim($bal->code . ' ' . $bal->name), 'id' => (int) $bal->account_id,
                'currency' => $bal->currency, 'doc_outstanding' => $foreign,
            ];
            return;
        }

        $bookedBase   = round((float) $bal->base_balance, 2);
        $revaluedBase = round($foreign * (float) $asofRate, 2);

        $items[] = [
            'side'            => 'BANK',
            'type'           => 'cash-bank',
            'no'             => trim($bal->code . ' ' . $bal->name),
            'id'             => (int) $bal->account_id,
            'account_id'     => (int) $bal->account_id,
            'currency'       => $bal->currency,
            'doc_outstanding'=> round($foreign, 2),
            'booked_rate'    => $foreign != 0.0 ? round($bookedBase / $foreign, 6) : 0.0,
            'asof_rate'      => round((float) $asofRate, 6),
            'booked_base'    => $bookedBase,
            'revalued_base'  => $revaluedBase,
            'delta'          => round($revaluedBase - $bookedBase, 2),
        ];
    }
}
