<?php

namespace App\Services\Finance;

use App\Models\AccountingPeriod;
use App\Models\FinancialYear;
use App\Models\GlEntry;
use App\Models\GlJournal;
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

        if (round($totalDebit, 4) !== round($totalCredit, 4)) {
            throw new \InvalidArgumentException(
                "Journal does not balance: debit {$totalDebit} ≠ credit {$totalCredit}"
            );
        }

        return DB::transaction(function () use ($header, $lines, $totalDebit, $totalCredit) {
            $date   = Carbon::parse($header['journal_date']);
            $period = $this->resolvePeriod($date);

            $journal = GlJournal::create([
                'journal_no'        => $this->nextJournalNo(),
                'journal_date'      => $date->toDateString(),
                'financial_year_id' => $period->financial_year_id,
                'period_id'         => $period->id,
                'journal_type'      => $header['journal_type'] ?? 'journal',
                'reference_type'    => $header['reference_type'] ?? null,
                'reference_id'      => $header['reference_id'] ?? null,
                'narration'         => $header['narration'],
                'total_debit'       => $totalDebit,
                'total_credit'      => $totalCredit,
                'status'            => 'draft',
                'created_by'        => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $journal->entries()->create([
                    'account_id' => $line['account_id'],
                    'debit'      => $line['debit'] ?? 0,
                    'credit'     => $line['credit'] ?? 0,
                    'narration'  => $line['narration'] ?? null,
                ]);
            }

            return $journal;
        });
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
            // Build reversal lines
            $reversalLines = $journal->entries->map(function ($entry) {
                return [
                    'account_id' => $entry->account_id,
                    'debit'      => $entry->credit,
                    'credit'     => $entry->debit,
                    'narration'  => $entry->narration,
                ];
            })->toArray();

            $reversal = $this->createJournal([
                'journal_date'   => $journal->journal_date->toDateString(),
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

    private function resolvePeriod(Carbon $date): AccountingPeriod
    {
        $period = $this->periods->periodFor($date);
        if (!$period) {
            throw new \RuntimeException(
                "No open accounting period found for date {$date->toDateString()}. Open a financial year and period first."
            );
        }
        return $period;
    }

    private function nextJournalNo(): string
    {
        // Use DB lock to avoid race conditions
        $setting = \App\Models\CompanySetting::current();
        $prefix  = $setting->prefix_journal ?? 'JV';

        $last = GlJournal::where('journal_no', 'like', "{$prefix}-%")
            ->orderByDesc('journal_no')
            ->lockForUpdate()
            ->value('journal_no');

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $seq   = ((int) end($parts)) + 1;
        }

        return $prefix . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }
}
