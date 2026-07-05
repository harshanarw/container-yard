<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\User;
use App\Services\Finance\PeriodManager;
use App\Services\Finance\PostingEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only diagnostic (everything is rolled back — safe to run on production).
 *
 * Question: voidJournal() marks the original journal 'voided' AND posts a
 * reversal, while every GL balance query filters status = 'posted' (excluding
 * voided). Does voiding therefore return the affected accounts to baseline
 * (correct), or overshoot by the reversal amount (double-count)?
 *
 * It posts a tiny balanced test journal, voids it, and measures the net change
 * to the two accounts under two interpretations — then prints a verdict.
 */
class DiagnoseVoidSemantics extends Command
{
    protected $signature = 'cyms:diagnose-void {--amount=137.11}';
    protected $description = 'Diagnose whether voiding a GL journal nets to zero (all changes rolled back).';

    public function handle(PostingEngine $engine, PeriodManager $periods): int
    {
        $amount = round((float) $this->option('amount'), 2);
        if ($amount <= 0) {
            $this->error('--amount must be positive.');
            return self::FAILURE;
        }

        $today = Carbon::today();
        if (!$periods->canPost($today)) {
            $this->error("No open accounting period for today ({$today->toDateString()}). Open one, then retry.");
            return self::FAILURE;
        }

        $accts = Account::where('is_active', true)->where('is_posting', true)->orderBy('code')->take(2)->get();
        if ($accts->count() < 2) {
            $this->error('Need at least two active posting accounts to run the test.');
            return self::FAILURE;
        }
        [$a, $b] = [$accts[0], $accts[1]];

        $userId = User::query()->value('id');
        if (!$userId) {
            $this->error('No users found (posted_by requires a valid user).');
            return self::FAILURE;
        }

        $bal = function (int $accountId, array $statuses): float {
            $row = DB::table('gl_entries')
                ->join('gl_journals', 'gl_journals.id', '=', 'gl_entries.journal_id')
                ->where('gl_entries.account_id', $accountId)
                ->whereIn('gl_journals.status', $statuses)
                ->selectRaw('COALESCE(SUM(gl_entries.debit) - SUM(gl_entries.credit), 0) as bal')
                ->value('bal');

            return round((float) $row, 2);
        };

        $POSTED   = ['posted'];             // what Trial Balance / all reports use
        $NOTDRAFT = ['posted', 'voided'];   // include voided (the reversal-based interpretation)

        $this->line('');
        $this->info('VOID SEMANTICS DIAGNOSTIC — all changes are rolled back (safe).');
        $this->line("Accounts under test:  A = {$a->code} {$a->name}   |   B = {$b->code} {$b->name}");
        $this->line("Test journal: DR A / CR B  {$amount}  dated {$today->toDateString()}");
        $this->line('');

        DB::beginTransaction();
        try {
            $baseA_p  = $bal($a->id, $POSTED);
            $baseB_p  = $bal($b->id, $POSTED);
            $baseA_nd = $bal($a->id, $NOTDRAFT);
            $baseB_nd = $bal($b->id, $NOTDRAFT);

            $j = $engine->createJournal([
                'journal_date' => $today->toDateString(),
                'journal_type' => 'journal',
                'narration'    => 'VOID DIAGNOSTIC (rolled back)',
            ], [
                ['account_id' => $a->id, 'debit' => $amount, 'credit' => 0, 'narration' => 'diag DR'],
                ['account_id' => $b->id, 'debit' => 0, 'credit' => $amount, 'narration' => 'diag CR'],
            ]);
            $engine->postJournal($j, $userId);

            $rev = $engine->voidJournal($j->load('entries'), $userId, 'void diagnostic');

            $finA_p  = $bal($a->id, $POSTED);
            $finB_p  = $bal($b->id, $POSTED);
            $finA_nd = $bal($a->id, $NOTDRAFT);
            $finB_nd = $bal($b->id, $NOTDRAFT);

            $dA_p  = round($finA_p - $baseA_p, 2);
            $dB_p  = round($finB_p - $baseB_p, 2);
            $dA_nd = round($finA_nd - $baseA_nd, 2);
            $dB_nd = round($finB_nd - $baseB_nd, 2);

            $this->line("Created journal {$j->journal_no}, voided via reversal {$rev->journal_no}.");
            $this->line('');
            $this->line('Net change to A and B caused by (post + void) — should be 0.00 if voiding is correct:');
            $this->line(sprintf("  Under 'posted' filter        (Trial Balance / all reports):  A = %+0.2f   B = %+0.2f", $dA_p, $dB_p));
            $this->line(sprintf("  Under 'posted + voided' filter (include voided originals):    A = %+0.2f   B = %+0.2f", $dA_nd, $dB_nd));
            $this->line('');

            $postedZero   = abs($dA_p) < 0.005 && abs($dB_p) < 0.005;
            $notDraftZero = abs($dA_nd) < 0.005 && abs($dB_nd) < 0.005;

            if ($postedZero) {
                $this->info('VERDICT: NO BUG. Voiding already nets to zero under the posted-only filter that reports use.');
            } elseif ($notDraftZero) {
                $this->error('VERDICT: CONFIRMED DOUBLE-COUNT.');
                $this->line("  The posted-only filter overshoots by the reversal amount ({$amount}); the accounts only");
                $this->line("  return to baseline when voided originals are INCLUDED. Every report that filters");
                $this->line("  status = 'posted' understates (or overstates) by each voided journal's amount.");
                $this->line('  FIX DIRECTION: balance queries should include voided journals (status <> draft),');
                $this->line("  since the reversal — not the exclusion — is what neutralises a void.");
            } else {
                $this->warn('VERDICT: INCONCLUSIVE — neither interpretation nets to zero. Investigate manually.');
                $this->line("  posted deltas: A={$dA_p} B={$dB_p} ; not-draft deltas: A={$dA_nd} B={$dB_nd}");
            }
        } finally {
            DB::rollBack();
            $this->line('');
            $this->info('All test data rolled back. Nothing was persisted.');
        }

        return self::SUCCESS;
    }
}
