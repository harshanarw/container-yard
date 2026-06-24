<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\FinancialYear;
use App\Models\GlEntry;
use App\Models\GlJournal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Period-end P&L closing.
 *
 * Workflow (two steps per period):
 *   1. Close period (open → closed)  — stops normal posting   [PeriodManager]
 *   2. Close P&L     (closed → locked) — posts closing journal [this service]
 *
 * Monthly close: zeroes the period's income/expense accounts and moves the
 * net result into 3003 (Current Year P/L), an equity account.
 *
 * Year-end: when the final period of a fiscal year is P&L-closed, an extra
 * journal rolls the full 3003 balance into 3002 (Retained Earnings) and the
 * fiscal year is marked 'closed'.
 *
 * All closing journals carry journal_type='closing' so financial reports can
 * tell real activity apart from closing movements.
 */
class ClosingService
{
    public const CURRENT_YEAR_PL   = '3003';
    public const RETAINED_EARNINGS = '3002';

    public function __construct(private PostingEngine $engine) {}

    /**
     * Run the P&L close for a single period.
     *
     * @return array{period_journal: ?GlJournal, year_end_journal: ?GlJournal, net_pl: float, year_end: bool}
     */
    public function closePeriodPL(AccountingPeriod $period, int $userId): array
    {
        $period->loadMissing('financialYear');

        if ($period->status === 'open') {
            throw new RuntimeException("Close the period '{$period->name}' (period-end) before running the P&L close.");
        }
        if ($period->status === 'locked') {
            throw new RuntimeException("Period '{$period->name}' P&L is already closed.");
        }

        // Periods must be P&L-closed in chronological order.
        $earlierUnlocked = AccountingPeriod::where('financial_year_id', $period->financial_year_id)
            ->where('period_no', '<', $period->period_no)
            ->where('status', '!=', 'locked')
            ->orderBy('period_no')
            ->first();
        if ($earlierUnlocked) {
            throw new RuntimeException(
                "Close earlier period '{$earlierUnlocked->name}' first — periods must be P&L-closed in order."
            );
        }

        $plAccount = Account::where('code', self::CURRENT_YEAR_PL)->where('is_active', true)->first();
        if (!$plAccount) {
            throw new RuntimeException('Current Year P/L account (3003) is missing or inactive. Check the Chart of Accounts.');
        }

        return DB::transaction(function () use ($period, $userId, $plAccount) {
            $result = [
                'period_journal'   => null,
                'year_end_journal' => null,
                'net_pl'           => 0.0,
                'year_end'         => false,
            ];

            [$lines, $netPL] = $this->buildPeriodClosingLines($period, $plAccount);

            if (!empty($lines)) {
                $result['period_journal'] = $this->engine->createAndPostClosing([
                    'journal_date'   => $period->end_date->toDateString(),
                    'reference_type' => AccountingPeriod::class,
                    'reference_id'   => $period->id,
                    'narration'      => "P&L close — {$period->name}",
                ], $lines, $period, $userId);
            }
            $result['net_pl'] = $netPL;

            // Lock the period (P&L closed)
            $period->update([
                'status'    => 'locked',
                'closed_by' => $userId,
                'closed_at' => now(),
            ]);

            // Year-end: final period of the fiscal year rolls 3003 → 3002
            if ($this->isFinalPeriod($period)) {
                $result['year_end_journal'] = $this->rollupRetainedEarnings($period, $userId);
                $result['year_end']         = true;
                $period->financialYear?->update(['status' => 'closed']);
            }

            return $result;
        });
    }

    /**
     * Reverse a P&L close (locked → closed). Voids the period's closing
     * journals and, if this was a year-end close, reopens the fiscal year.
     */
    public function reversePeriodClose(AccountingPeriod $period, int $userId): void
    {
        $period->loadMissing('financialYear');

        if ($period->status !== 'locked') {
            throw new RuntimeException("Only P&L-closed periods can be reversed. '{$period->name}' is {$period->status}.");
        }

        // Reverse in reverse-chronological order.
        $laterLocked = AccountingPeriod::where('financial_year_id', $period->financial_year_id)
            ->where('period_no', '>', $period->period_no)
            ->where('status', 'locked')
            ->orderBy('period_no')
            ->first();
        if ($laterLocked) {
            throw new RuntimeException(
                "Reverse later period '{$laterLocked->name}' first — closes must be undone in reverse order."
            );
        }

        DB::transaction(function () use ($period, $userId) {
            // Year-end roll journals (referenced to the fiscal year, posted in this period)
            $yearEndJournals = GlJournal::where('journal_type', 'closing')
                ->where('status', 'posted')
                ->where('period_id', $period->id)
                ->where('reference_type', FinancialYear::class)
                ->where('reference_id', $period->financial_year_id)
                ->with('entries')
                ->get();

            foreach ($yearEndJournals as $j) {
                $this->voidClosingJournal($j, $period, $userId);
            }

            // Monthly P&L close journals (referenced to this period)
            $periodJournals = GlJournal::where('journal_type', 'closing')
                ->where('status', 'posted')
                ->where('reference_type', AccountingPeriod::class)
                ->where('reference_id', $period->id)
                ->with('entries')
                ->get();

            foreach ($periodJournals as $j) {
                $this->voidClosingJournal($j, $period, $userId);
            }

            $period->update(['status' => 'closed']);

            // If a year-end close was undone, reopen the fiscal year.
            $fy = $period->financialYear;
            if ($fy && $fy->status === 'closed' && $this->isFinalPeriod($period)) {
                $fy->update(['status' => 'open']);
            }
        });
    }

    /**
     * Build the zeroing lines for a period's income/expense accounts, plus the
     * balancing 3003 line. Returns [lines, netProfit].
     *
     * @return array{0: array<int, array>, 1: float}
     */
    private function buildPeriodClosingLines(AccountingPeriod $period, Account $plAccount): array
    {
        $accounts = Account::where('is_active', true)
            ->where('is_posting', true)
            ->whereIn('classification', ['income', 'expense'])
            ->get();

        $lines = [];

        foreach ($accounts as $acc) {
            $sums = GlEntry::where('account_id', $acc->id)
                ->whereHas('journal', fn ($j) => $j->where('status', 'posted')
                    ->where('period_id', $period->id)
                    ->where('journal_type', '!=', 'closing'))
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();

            $d = (float) $sums->d;
            $c = (float) $sums->c;

            if ($acc->classification === 'income') {
                $bal = round($c - $d, 2); // credit-normal
                if (abs($bal) < 0.005) continue;
                // Zero it: post on the debit side for a positive (credit) balance
                $lines[] = $bal > 0
                    ? ['account_id' => $acc->id, 'debit' => $bal,  'credit' => 0,     'narration' => "Close {$acc->name}"]
                    : ['account_id' => $acc->id, 'debit' => 0,     'credit' => -$bal, 'narration' => "Close {$acc->name}"];
            } else { // expense, debit-normal
                $bal = round($d - $c, 2);
                if (abs($bal) < 0.005) continue;
                $lines[] = $bal > 0
                    ? ['account_id' => $acc->id, 'debit' => 0,     'credit' => $bal,  'narration' => "Close {$acc->name}"]
                    : ['account_id' => $acc->id, 'debit' => -$bal, 'credit' => 0,     'narration' => "Close {$acc->name}"];
            }
        }

        if (empty($lines)) {
            return [[], 0.0];
        }

        // Balance to 3003 with the exact difference (immune to per-line rounding).
        $sumDebit  = array_sum(array_column($lines, 'debit'));
        $sumCredit = array_sum(array_column($lines, 'credit'));
        $netProfit = round($sumDebit - $sumCredit, 2); // income debits − expense credits = profit

        if ($netProfit > 0) {
            $lines[] = ['account_id' => $plAccount->id, 'debit' => 0, 'credit' => $netProfit, 'narration' => 'Net profit to Current Year P/L'];
        } elseif ($netProfit < 0) {
            $lines[] = ['account_id' => $plAccount->id, 'debit' => -$netProfit, 'credit' => 0, 'narration' => 'Net loss to Current Year P/L'];
        }

        return [$lines, $netProfit];
    }

    /**
     * Year-end: move the entire 3003 balance into 3002 (Retained Earnings).
     */
    private function rollupRetainedEarnings(AccountingPeriod $finalPeriod, int $userId): ?GlJournal
    {
        $pl = Account::where('code', self::CURRENT_YEAR_PL)->first();
        $re = Account::where('code', self::RETAINED_EARNINGS)->where('is_active', true)->first();

        if (!$pl) {
            throw new RuntimeException('Current Year P/L account (3003) is missing. Check the Chart of Accounts.');
        }

        if (!$re) {
            throw new RuntimeException('Retained Earnings account (3002) is missing or inactive. Check the Chart of Accounts.');
        }

        // Cumulative 3003 balance up to the fiscal-year end (all journal types).
        $sums = GlEntry::where('account_id', $pl->id)
            ->whereHas('journal', fn ($j) => $j->where('status', 'posted')
                ->where('journal_date', '<=', $finalPeriod->end_date->toDateString()))
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        $balCredit = round((float) $sums->c - (float) $sums->d, 2); // credit-normal balance
        if (abs($balCredit) < 0.005) {
            return null;
        }

        $fyCode = $finalPeriod->financialYear?->code ?? '';

        if ($balCredit > 0) {
            // Profit: DR 3003, CR 3002
            $lines = [
                ['account_id' => $pl->id, 'debit' => $balCredit, 'credit' => 0,         'narration' => 'Close Current Year P/L'],
                ['account_id' => $re->id, 'debit' => 0,          'credit' => $balCredit, 'narration' => 'Transfer to Retained Earnings'],
            ];
        } else {
            // Loss: CR 3003, DR 3002
            $amt = -$balCredit;
            $lines = [
                ['account_id' => $pl->id, 'debit' => 0,    'credit' => $amt, 'narration' => 'Close Current Year P/L'],
                ['account_id' => $re->id, 'debit' => $amt, 'credit' => 0,    'narration' => 'Transfer loss to Retained Earnings'],
            ];
        }

        return $this->engine->createAndPostClosing([
            'journal_date'   => $finalPeriod->end_date->toDateString(),
            'reference_type' => FinancialYear::class,
            'reference_id'   => $finalPeriod->financial_year_id,
            'narration'      => "Year-end close {$fyCode} — transfer Current Year P/L to Retained Earnings",
        ], $lines, $finalPeriod, $userId);
    }

    private function voidClosingJournal(GlJournal $journal, AccountingPeriod $period, int $userId): void
    {
        $reversal = $journal->entries->map(fn ($e) => [
            'account_id' => $e->account_id,
            'debit'      => $e->credit,
            'credit'     => $e->debit,
            'narration'  => $e->narration,
        ])->toArray();

        $this->engine->createAndPostClosing([
            'journal_date'   => $period->end_date->toDateString(),
            'reference_type' => $journal->reference_type,
            'reference_id'   => $journal->reference_id,
            'narration'      => "REVERSE: {$journal->journal_no}",
        ], $reversal, $period, $userId);

        $journal->update([
            'status'    => 'voided',
            'voided_by' => $userId,
            'voided_at' => now(),
        ]);
    }

    private function isFinalPeriod(AccountingPeriod $period): bool
    {
        $maxNo = AccountingPeriod::where('financial_year_id', $period->financial_year_id)->max('period_no');

        return (int) $period->period_no === (int) $maxNo;
    }
}
