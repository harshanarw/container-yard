<?php

namespace App\Services\Finance;

use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\GlEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bank reconciliation engine, modelled on the workflow used by mainstream
 * accounting packages (QuickBooks / Xero / Zoho):
 *
 *   beginning balance (from the prior reconciliation)
 *     + cleared deposits − cleared payments = cleared balance
 *   difference = statement ending balance − cleared balance   → 0 when reconciled
 *
 * A book (GL) bank line is "cleared" once it is confirmed to appear on the bank
 * statement. Uncleared deposits are deposits-in-transit; uncleared payments are
 * unpresented cheques — the two adjustments of the classic reconciliation
 * statement, which then ties the statement balance back to the ledger balance.
 */
class BankReconciliationService
{
    public function __construct(private PostingEngine $engine) {}

    /**
     * Posted GL lines on the bank's control account up to the statement date that
     * are either unreconciled or already cleared against THIS reconciliation.
     * debit = money into the bank, credit = money out.
     */
    public function availableEntries(BankReconciliation $recon): Collection
    {
        $accountId = $recon->bankAccount->gl_account_id;
        if (!$accountId) {
            return collect();
        }

        return GlEntry::query()
            ->join('gl_journals', 'gl_journals.id', '=', 'gl_entries.journal_id')
            ->where('gl_entries.account_id', $accountId)
            ->where('gl_journals.status', 'posted')
            ->whereDate('gl_journals.journal_date', '<=', $recon->statement_date->toDateString())
            ->where(function ($q) use ($recon) {
                $q->whereNull('gl_entries.bank_reconciliation_id')
                    ->orWhere('gl_entries.bank_reconciliation_id', $recon->id);
            })
            ->orderBy('gl_journals.journal_date')
            ->orderBy('gl_entries.id')
            ->select([
                'gl_entries.*',
                'gl_journals.journal_date as j_date',
                'gl_journals.journal_no as j_no',
                'gl_journals.journal_type as j_type',
                'gl_journals.narration as j_narration',
                'gl_journals.reference_type as j_ref_type',
                'gl_journals.reference_id as j_ref_id',
            ])
            ->get();
    }

    /** All reconciliation figures for the working screen and the report. */
    public function summary(BankReconciliation $recon): array
    {
        $entries = $this->availableEntries($recon);

        $cleared   = $entries->filter(fn ($e) => (int) $e->bank_reconciliation_id === $recon->id && $e->cleared_at);
        $uncleared = $entries->reject(fn ($e) => (int) $e->bank_reconciliation_id === $recon->id && $e->cleared_at);

        $clearedDeposits    = round($cleared->sum(fn ($e) => (float) $e->debit), 2);
        $clearedWithdrawals = round($cleared->sum(fn ($e) => (float) $e->credit), 2);
        $depositsInTransit  = round($uncleared->sum(fn ($e) => (float) $e->debit), 2);
        $unpresentedCheques = round($uncleared->sum(fn ($e) => (float) $e->credit), 2);

        $opening        = (float) $recon->opening_balance;
        $statementClose = (float) $recon->closing_balance;
        $clearedBalance = round($opening + $clearedDeposits - $clearedWithdrawals, 2);
        $difference     = round($statementClose - $clearedBalance, 2);

        $bookBalance = $this->glBookBalance($recon->bankAccount->gl_account_id, $recon->statement_date->toDateString());
        $adjustedBank = round($statementClose + $depositsInTransit - $unpresentedCheques, 2);

        return [
            'entries'              => $entries,
            'cleared_count'        => $cleared->count(),
            'uncleared_count'      => $uncleared->count(),
            'cleared_deposits'     => $clearedDeposits,
            'cleared_withdrawals'  => $clearedWithdrawals,
            'deposits_in_transit'  => $depositsInTransit,
            'unpresented_cheques'  => $unpresentedCheques,
            'opening_balance'      => round($opening, 2),
            'statement_balance'    => round($statementClose, 2),
            'cleared_balance'      => $clearedBalance,
            'difference'           => $difference,
            'is_balanced'          => abs($difference) < 0.01,
            'book_balance'         => $bookBalance,
            'adjusted_bank_balance' => $adjustedBank,
            // Ledger-vs-statement tie-out proof: adjusted bank should equal book balance.
            'tie_out_difference'   => round($adjustedBank - $bookBalance, 2),
        ];
    }

    /** GL balance of the bank account (posted entries) up to and including $date. */
    public function glBookBalance(?int $accountId, string $date): float
    {
        if (!$accountId) {
            return 0.0;
        }

        $row = GlEntry::query()
            ->join('gl_journals', 'gl_journals.id', '=', 'gl_entries.journal_id')
            ->where('gl_entries.account_id', $accountId)
            ->where('gl_journals.status', 'posted')
            ->whereDate('gl_journals.journal_date', '<=', $date)
            ->selectRaw('COALESCE(SUM(gl_entries.debit) - SUM(gl_entries.credit), 0) as bal')
            ->value('bal');

        return round((float) $row, 2);
    }

    /** Tick / untick a book entry as cleared against this reconciliation. */
    public function toggleClear(BankReconciliation $recon, int $glEntryId): void
    {
        abort_unless($recon->isDraft(), 422, 'Reconciliation is completed.');

        $entry = GlEntry::where('id', $glEntryId)
            ->where('account_id', $recon->bankAccount->gl_account_id)
            ->firstOrFail();

        if ((int) $entry->bank_reconciliation_id === $recon->id && $entry->cleared_at) {
            $entry->update(['bank_reconciliation_id' => null, 'cleared_at' => null]);
        } else {
            abort_unless(is_null($entry->bank_reconciliation_id), 422, 'Entry already cleared elsewhere.');
            $entry->update(['bank_reconciliation_id' => $recon->id, 'cleared_at' => now()]);
        }
    }

    /**
     * Auto-match unmatched statement lines to available uncleared book entries by
     * equal signed amount and a close date (±$dayTolerance days). First exact hit
     * wins; ambiguous or missing matches are left for manual handling.
     *
     * @return array{matched:int}
     */
    public function autoMatch(BankReconciliation $recon, int $dayTolerance = 5): array
    {
        abort_unless($recon->isDraft(), 422, 'Reconciliation is completed.');

        $entries = $this->availableEntries($recon)
            ->filter(fn ($e) => is_null($e->bank_reconciliation_id)); // still open

        $lines = $recon->statementLines()->where('status', 'unmatched')->get();

        $matched = 0;
        foreach ($lines as $line) {
            $target = round((float) $line->deposit - (float) $line->withdrawal, 2); // + into bank
            $hit = $entries->first(function ($e) use ($target, $line, $dayTolerance) {
                $entryAmt = round((float) $e->debit - (float) $e->credit, 2);
                if (abs($entryAmt - $target) >= 0.01) {
                    return false;
                }
                $days = abs($e->j_date ? \Carbon\Carbon::parse($e->j_date)->diffInDays($line->txn_date) : 999);

                return $days <= $dayTolerance;
            });

            if (!$hit) {
                continue;
            }

            $hit->update(['bank_reconciliation_id' => $recon->id, 'cleared_at' => now()]);
            $line->update(['status' => 'matched', 'matched_gl_entry_id' => $hit->id]);
            $entries = $entries->reject(fn ($e) => $e->id === $hit->id);
            $matched++;
        }

        return ['matched' => $matched];
    }

    /** Manually link a statement line to a book entry and clear it. */
    public function matchLine(BankReconciliation $recon, BankStatementLine $line, int $glEntryId): void
    {
        abort_unless($recon->isDraft(), 422, 'Reconciliation is completed.');

        $entry = GlEntry::where('id', $glEntryId)
            ->where('account_id', $recon->bankAccount->gl_account_id)
            ->whereNull('bank_reconciliation_id')
            ->firstOrFail();

        $entry->update(['bank_reconciliation_id' => $recon->id, 'cleared_at' => now()]);
        $line->update(['status' => 'matched', 'matched_gl_entry_id' => $entry->id]);
    }

    /** Break a statement line's match and un-clear the underlying book entry. */
    public function unmatchLine(BankReconciliation $recon, BankStatementLine $line): void
    {
        abort_unless($recon->isDraft(), 422, 'Reconciliation is completed.');

        if ($line->matched_gl_entry_id) {
            GlEntry::where('id', $line->matched_gl_entry_id)
                ->where('bank_reconciliation_id', $recon->id)
                ->update(['bank_reconciliation_id' => null, 'cleared_at' => null]);
        }
        $line->update(['status' => 'unmatched', 'matched_gl_entry_id' => null]);
    }

    /**
     * Book an adjusting journal for a statement line that has no matching book
     * entry (bank charge, interest, direct debit…). Posts it, clears the new bank
     * line against this reconciliation and matches the statement line to it.
     */
    public function bookAdjustment(BankReconciliation $recon, BankStatementLine $line, int $contraAccountId, ?int $userId): void
    {
        abort_unless($recon->isDraft(), 422, 'Reconciliation is completed.');
        abort_if($line->isMatched(), 422, 'Statement line is already matched.');

        $bankAccountId = $recon->bankAccount->gl_account_id;
        abort_unless($bankAccountId, 422, 'Bank account has no GL account mapped.');

        $deposit    = (float) $line->deposit;
        $withdrawal = (float) $line->withdrawal;
        $narration  = $line->description ?: 'Bank statement adjustment';

        if ($deposit > 0) {
            // Money into the bank: DR bank, CR contra (e.g. interest income).
            $lines = [
                ['account_id' => $bankAccountId,   'debit' => $deposit, 'credit' => 0, 'narration' => $narration],
                ['account_id' => $contraAccountId, 'debit' => 0, 'credit' => $deposit, 'narration' => $narration],
            ];
        } else {
            // Money out of the bank: DR contra (e.g. bank charges), CR bank.
            $lines = [
                ['account_id' => $contraAccountId, 'debit' => $withdrawal, 'credit' => 0, 'narration' => $narration],
                ['account_id' => $bankAccountId,   'debit' => 0, 'credit' => $withdrawal, 'narration' => $narration],
            ];
        }

        DB::transaction(function () use ($recon, $line, $bankAccountId, $narration, $lines, $userId) {
            $journal = $this->engine->createJournal([
                'journal_date'   => $line->txn_date->toDateString(),
                'journal_type'   => 'adjustment',
                'reference_type' => BankReconciliation::class,
                'reference_id'   => $recon->id,
                'narration'      => 'Bank reconciliation: ' . $narration,
            ], $lines);

            $this->engine->postJournal($journal, $userId ?? (auth()->id() ?? 0));

            // Clear the freshly-posted bank leg against this reconciliation.
            $bankEntry = $journal->entries()->where('account_id', $bankAccountId)->first();
            if ($bankEntry) {
                $bankEntry->update(['bank_reconciliation_id' => $recon->id, 'cleared_at' => now()]);
                $line->update(['status' => 'matched', 'matched_gl_entry_id' => $bankEntry->id]);
            }
        });
    }

    /** Finalise the reconciliation once the difference is zero. */
    public function complete(BankReconciliation $recon, ?int $userId): void
    {
        abort_unless($recon->isDraft(), 422, 'Reconciliation is already completed.');

        $summary = $this->summary($recon);
        abort_unless($summary['is_balanced'], 422, 'Cannot complete: the difference is not zero.');

        $recon->update([
            'status'        => 'completed',
            'reconciled_at' => now(),
            'reconciled_by' => $userId,
        ]);
    }

    /** Re-open a completed reconciliation (keeps the cleared marks). */
    public function reopen(BankReconciliation $recon): void
    {
        $recon->update(['status' => 'draft', 'reconciled_at' => null, 'reconciled_by' => null]);
    }

    /** Release all cleared entries and statement lines, then delete a draft reconciliation. */
    public function deleteReconciliation(BankReconciliation $recon): void
    {
        abort_unless($recon->isDraft(), 422, 'Completed reconciliations cannot be deleted; re-open first.');

        DB::transaction(function () use ($recon) {
            GlEntry::where('bank_reconciliation_id', $recon->id)
                ->update(['bank_reconciliation_id' => null, 'cleared_at' => null]);
            $recon->statementLines()->delete();
            $recon->delete();
        });
    }
}
