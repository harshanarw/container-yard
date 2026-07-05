<?php

namespace App\Services\Finance;

use App\Models\AccountingPeriod;
use App\Models\FinancialYear;
use App\Models\GlEntry;
use App\Models\GlJournal;
use App\Services\CurrencyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PostingEngine
{
    public function __construct(private PeriodManager $periods) {}

    /**
     * Create a new draft journal. Lines = array of ['account_id', 'debit', 'credit', 'narration'].
     * Validates debit == credit total before creating.
     */
    public function createJournal(array $header, array $lines): GlJournal
    {
        $totalDebit  = collect($lines)->sum('debit');
        $totalCredit = collect($lines)->sum('credit');

        // Epsilon compare — strict !== on floats can reject a balanced journal on
        // the last bit (e.g. total vs (total - tax) + tax). 0.0001 is far below
        // the 2-dp money precision we post at.
        if (abs($totalDebit - $totalCredit) >= 0.0001) {
            throw new \InvalidArgumentException(
                "Journal does not balance: debit {$totalDebit} ≠ credit {$totalCredit}"
            );
        }

        return DB::transaction(function () use ($header, $lines, $totalDebit, $totalCredit) {
            $date   = Carbon::parse($header['journal_date']);
            $period = $this->resolvePeriod($date);
            $base   = CurrencyService::defaultCurrency();

            $entries = array_map(fn ($line) => $this->normalizeLine($line, $base), $lines);

            $journal = GlJournal::create([
                'journal_no'        => $this->nextJournalNo(),
                'journal_date'      => $date->toDateString(),
                'financial_year_id' => $period->financial_year_id,
                'period_id'         => $period->id,
                'journal_type'      => $header['journal_type'] ?? 'journal',
                'currency'          => $header['currency'] ?? $this->journalCurrency($entries, $base),
                'reference_type'    => $header['reference_type'] ?? null,
                'reference_id'      => $header['reference_id'] ?? null,
                'narration'         => $header['narration'],
                'total_debit'       => $totalDebit,
                'total_credit'      => $totalCredit,
                'status'            => 'draft',
                'created_by'        => auth()->id(),
            ]);

            foreach ($entries as $entry) {
                $journal->entries()->create($entry);
            }

            return $journal;
        });
    }

    /**
     * Normalise a posting line into a full GL entry attribute set. The base
     * debit/credit remain authoritative; the transaction currency/rate and
     * transaction-currency amounts are additive. Callers that only supply base
     * amounts get currency = base, rate = 1 and txn = base automatically, so
     * existing single-currency postings are unchanged.
     */
    private function normalizeLine(array $line, string $base): array
    {
        $debit  = (float) ($line['debit'] ?? 0);
        $credit = (float) ($line['credit'] ?? 0);

        return [
            'account_id'     => $line['account_id'],
            'debit'          => $debit,
            'credit'         => $credit,
            'narration'      => $line['narration'] ?? null,
            'currency'       => strtoupper((string) ($line['currency'] ?? $base)) ?: $base,
            'exchange_rate'  => (float) ($line['exchange_rate'] ?? 1) ?: 1.0,
            'txn_debit'      => array_key_exists('txn_debit', $line)  ? (float) $line['txn_debit']  : $debit,
            'txn_credit'     => array_key_exists('txn_credit', $line) ? (float) $line['txn_credit'] : $credit,
            'group_currency' => $line['group_currency'] ?? null,
            'group_debit'    => $line['group_debit'] ?? null,
            'group_credit'   => $line['group_credit'] ?? null,
        ];
    }

    /**
     * Document currency for the journal header. A foreign transaction typically has
     * its foreign legs plus a base-currency FX gain/loss leg, so the header is the
     * single non-base currency when there's exactly one (ignoring base-currency
     * lines like FX); a pure base journal is base; a genuinely multi-foreign
     * journal falls back to base.
     */
    private function journalCurrency(array $entries, string $base): string
    {
        $foreign = array_values(array_unique(array_filter(
            array_map(fn ($e) => $e['currency'], $entries),
            fn ($c) => $c !== $base
        )));

        if (count($foreign) === 1) {
            return $foreign[0];
        }

        return $base;
    }

    /**
     * Post a draft journal: validate period still open, set status=posted + posted_by/at.
     */
    public function postJournal(GlJournal $journal, int $userId): void
    {
        if (!$journal->isDraft()) {
            throw new \RuntimeException("Only draft journals can be posted.");
        }

        $date = $journal->journal_date;
        $this->resolvePeriod($date); // throws if period closed/not found

        DB::transaction(function () use ($journal, $userId) {
            $journal->update([
                'status'    => 'posted',
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);
        });
    }

    /**
     * Void a posted journal: creates a reversing journal (all debits/credits swapped),
     * posts the reversal, then marks the original as voided.
     */
    public function voidJournal(GlJournal $journal, int $userId, string $reason = ''): GlJournal
    {
        if (!$journal->isPosted()) {
            throw new \RuntimeException("Only posted journals can be voided.");
        }

        return DB::transaction(function () use ($journal, $userId, $reason) {
            // Reverse in the original's OWN period when it is still open — the
            // original and its reversal then net to zero within that period, so a
            // cross-year void no longer shifts results between two years' P&L. Only
            // when the original period is closed/locked does the reversal fall to
            // today's open period (you cannot post into a closed period).
            $originalDate = $journal->journal_date instanceof Carbon
                ? $journal->journal_date
                : Carbon::parse($journal->journal_date);
            $reversalDate = $this->periods->canPost($originalDate) ? $originalDate->copy() : Carbon::today();

            // Build reversal lines (debits and credits swapped, in both base and
            // transaction currency, carrying the original currency/rate).
            $reversalLines = $journal->entries->map(function ($entry) {
                return [
                    'account_id'     => $entry->account_id,
                    'debit'          => $entry->credit,
                    'credit'         => $entry->debit,
                    'narration'      => $entry->narration,
                    'currency'       => $entry->currency,
                    'exchange_rate'  => $entry->exchange_rate,
                    'txn_debit'      => $entry->txn_credit,
                    'txn_credit'     => $entry->txn_debit,
                    'group_currency' => $entry->group_currency,
                    'group_debit'    => $entry->group_credit,
                    'group_credit'   => $entry->group_debit,
                ];
            })->toArray();

            $reversal = $this->createJournal([
                'journal_date'   => $reversalDate->toDateString(),
                'journal_type'   => $journal->journal_type,
                'reference_type' => $journal->reference_type,
                'reference_id'   => $journal->reference_id,
                'narration'      => "VOID: {$journal->journal_no}" . ($reason ? " — {$reason}" : ''),
            ], $reversalLines);

            $this->postJournal($reversal, $userId);

            $journal->update([
                'status'    => 'voided',
                'voided_by' => $userId,
                'voided_at' => now(),
            ]);

            return $reversal;
        });
    }

    /**
     * Create and immediately post a 'closing' journal into an explicit period.
     *
     * Closing entries (monthly P&L close, year-end retained-earnings roll) must
     * post INTO the period being closed — which is no longer 'open' — so they
     * bypass the open-period resolution that normal journals go through. The
     * caller is responsible for supplying the correct period and a balanced set
     * of lines.
     */
    public function createAndPostClosing(array $header, array $lines, AccountingPeriod $period, int $userId): GlJournal
    {
        $totalDebit  = collect($lines)->sum('debit');
        $totalCredit = collect($lines)->sum('credit');

        if (abs($totalDebit - $totalCredit) >= 0.0001) {
            throw new \InvalidArgumentException(
                "Closing journal does not balance: debit {$totalDebit} ≠ credit {$totalCredit}"
            );
        }

        return DB::transaction(function () use ($header, $lines, $period, $userId, $totalDebit, $totalCredit) {
            $base    = CurrencyService::defaultCurrency();
            $entries = array_map(fn ($line) => $this->normalizeLine($line, $base), $lines);

            $journal = GlJournal::create([
                'journal_no'        => $this->nextJournalNo(),
                'journal_date'      => Carbon::parse($header['journal_date'])->toDateString(),
                'financial_year_id' => $period->financial_year_id,
                'period_id'         => $period->id,
                'journal_type'      => 'closing',
                'currency'          => $header['currency'] ?? $this->journalCurrency($entries, $base),
                'reference_type'    => $header['reference_type'] ?? null,
                'reference_id'      => $header['reference_id'] ?? null,
                'narration'         => $header['narration'],
                'total_debit'       => $totalDebit,
                'total_credit'      => $totalCredit,
                'status'            => 'posted',
                'created_by'        => $userId,
                'posted_by'         => $userId,
                'posted_at'         => now(),
            ]);

            foreach ($entries as $entry) {
                $journal->entries()->create($entry);
            }

            return $journal;
        });
    }

    private function resolvePeriod(Carbon $date): AccountingPeriod
    {
        $period = $this->periods->periodFor($date);
        if ($period) {
            return $period;
        }

        // No postable period — work out *why* so the message is actionable
        // instead of the generic "open a financial year and period first".
        $dateStr = $date->toDateString();

        // 1. Is any financial year open at all?
        if (! FinancialYear::where('status', 'open')->exists()) {
            $covering = FinancialYear::where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->first();

            if ($covering) {
                throw new \RuntimeException(
                    "Financial year {$covering->code} covers {$dateStr} but its status is "
                    . "'{$covering->status}', not open. Open it under Finance → Setup → "
                    . "Financial Years, then post again."
                );
            }

            throw new \RuntimeException(
                "No open financial year found for {$dateStr}. Create and open a financial year "
                . "under Finance → Setup → Financial Years before posting."
            );
        }

        // 2. An open year exists — is the covering period closed/locked?
        $coveringPeriod = AccountingPeriod::whereHas(
                'financialYear', fn ($q) => $q->where('status', 'open')
            )
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->first();

        if ($coveringPeriod) {
            $label = $coveringPeriod->status === 'locked' ? 'P&L-closed (locked)' : $coveringPeriod->status;
            throw new \RuntimeException(
                "Accounting period '{$coveringPeriod->name}' is {$label} for {$dateStr}. "
                . "Reopen the period before posting to it."
            );
        }

        // 3. Open year exists but the date sits outside its range.
        throw new \RuntimeException(
            "{$dateStr} falls outside the open financial year's date range. Post within the "
            . "open year, or open the financial year that covers this date."
        );
    }

    private function nextJournalNo(): string
    {
        return app(\App\Services\NumberSequenceService::class)->generate('journal_voucher');
    }
}
