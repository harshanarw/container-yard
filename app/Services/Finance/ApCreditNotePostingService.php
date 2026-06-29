<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ApCreditNote;
use App\Models\ApCreditNoteApplication;
use App\Models\ChargeCode;
use App\Models\GlJournal;
use Illuminate\Support\Facades\DB;

/**
 * Posts AP credit notes to the GL — the reverse of a supplier bill:
 *   DR AP Control,  CR Expense (per line) + CR Input VAT
 * All in base currency. Reduces both payables and recognised expense.
 */
class ApCreditNotePostingService
{
    public function __construct(
        private PostingEngine $engine,
        private ApAllocationService $apAllocation,
    ) {}

    public function approve(ApCreditNote $cn, int $userId): ApCreditNote
    {
        if (!$cn->isDraft()) {
            throw new \RuntimeException('Only draft credit notes can be approved.');
        }

        $cn->loadMissing(['lines.expenseAccount', 'supplier']);

        if ($cn->lines->isEmpty()) {
            throw new \RuntimeException('Add at least one line before approving the credit note.');
        }

        return DB::transaction(function () use ($cn, $userId) {
            $apAccount = $this->resolveApAccount();
            if (!$apAccount) {
                throw new \RuntimeException('No AP control account mapped. Configure Account Mappings → AR/AP Controls.');
            }

            $rate           = (float) ($cn->exchange_rate ?: 1);
            $defaultExpense = Account::where('classification', 'expense')->where('is_posting', true)
                ->where('is_active', true)->orderBy('code')->first();

            // Credit expense per line (reverse the cost), in base currency.
            $credits = [];
            foreach ($cn->lines as $line) {
                $acc = $line->expenseAccount
                    ?? ($line->charge_code_id
                        ? $this->resolveAccount('charge_expense', ChargeCode::class, $line->charge_code_id)
                        : null)
                    ?? $defaultExpense;

                if (!$acc) {
                    throw new \RuntimeException("No expense account resolved for line: {$line->description}");
                }

                $credits[] = [
                    'account_id' => $acc->id,
                    'debit'      => 0,
                    'credit'     => round((float) $line->amount * $rate, 2),
                    'narration'  => $line->description,
                ];
            }

            // Reverse input VAT, if any.
            $tax = (float) $cn->tax_amount;
            if ($tax > 0) {
                $vatAcc = $this->resolveAccount('tax_input', null, null)
                    ?? Account::where('code', '1301')->where('is_active', true)->first();
                $vatBase = round($tax * $rate, 2);
                if ($vatAcc) {
                    $credits[] = ['account_id' => $vatAcc->id, 'debit' => 0, 'credit' => $vatBase, 'narration' => 'Input VAT reversal'];
                } else {
                    $last = count($credits) - 1;
                    $credits[$last]['credit'] = round($credits[$last]['credit'] + $vatBase, 2);
                }
            }

            // Debit AP = sum of credits (keeps the journal balanced to the cent).
            $drAp = round(array_sum(array_column($credits, 'credit')), 2);

            $lines = [[
                'account_id' => $apAccount->id,
                'debit'      => $drAp,
                'credit'     => 0,
                'narration'  => 'Trade creditors — credit note',
            ]];
            $lines = array_merge($lines, $credits);

            $journal = $this->engine->createJournal([
                'journal_date'   => $cn->credit_date->toDateString(),
                'journal_type'   => 'credit_note',
                'reference_type' => ApCreditNote::class,
                'reference_id'   => $cn->id,
                'narration'      => "AP Credit Note {$cn->credit_note_no} — " . ($cn->supplier->name ?? 'Supplier'),
            ], $lines);

            $this->engine->postJournal($journal, $userId);

            $cn->update([
                'status'        => 'approved',
                'journal_id'    => $journal->id,
                'approved_by'   => $userId,
                'approved_at'   => now(),
                'posting_error' => null,
            ]);

            return $cn->fresh(['journal']);
        });
    }

    public function cancel(ApCreditNote $cn, int $userId, string $reason = ''): void
    {
        if (!$cn->isApproved()) {
            throw new \RuntimeException('Only approved credit notes can be cancelled.');
        }

        $cn->loadMissing('applications');

        DB::transaction(function () use ($cn, $userId, $reason) {
            // Reverse any per-application FX adjustments before the main reversal.
            foreach ($cn->applications as $application) {
                $this->voidApplicationFx($application, $userId, $reason);
            }
            if ($cn->journal) {
                $this->engine->voidJournal($cn->journal->load('entries'), $userId, $reason);
            }
            $cn->update(['status' => 'cancelled']);
        });
    }

    /**
     * Post a per-application FX adjustment when an approved AP credit note settles a
     * supplier bill booked at a different exchange rate than the credit note itself.
     *
     * The credit note relieves AP at its OWN rate when approved, so matching it to
     * a bill booked at another rate leaves a residue in AP control of
     * applied × (invoiceRate − cnRate). This clears that residue to the FX
     * gain/loss accounts — the same realized FX that payment vouchers recognise on
     * settlement. No-op when the rates match, and a safe no-op until the credit
     * note is posted.
     */
    public function postApplicationFx(ApCreditNote $cn, ApCreditNoteApplication $application, int $userId): void
    {
        if (!$cn->isApproved()) {
            return; // the AP relief this corrects only exists once the CN is posted
        }

        $invoice = $this->apAllocation->resolveInvoice((int) $application->supplier_invoice_id);
        $invRate = $this->apAllocation->getExchangeRate($invoice);
        $cnRate  = (float) ($cn->exchange_rate ?: 1);

        $residue = round((float) $application->applied_amount * ($invRate - $cnRate), 2);
        if (abs($residue) < 0.01) {
            return;
        }

        $apAccount = $this->resolveApAccount();
        if (!$apAccount) {
            throw new \RuntimeException('No AP control account mapped. Configure Account Mappings → AR/AP Controls.');
        }
        [$gain, $loss] = $this->resolveFxAccounts();

        if ($residue > 0) {
            // AP under-relieved (leftover credit) → clear it, recognise an FX gain
            // (the payable was extinguished for fewer base-currency units).
            if (!$gain) {
                throw new \RuntimeException('No Foreign Exchange Gain account found (expected code 4102). Add it to the Chart of Accounts.');
            }
            $lines = [
                ['account_id' => $apAccount->id, 'debit' => $residue, 'credit' => 0, 'narration' => 'AP FX adjustment — credit note application'],
                ['account_id' => $gain->id, 'debit' => 0, 'credit' => $residue, 'narration' => 'Exchange gain on credit note application'],
            ];
        } else {
            // AP over-relieved (leftover debit) → clear it, recognise an FX loss.
            $mag = -$residue;
            if (!$loss) {
                throw new \RuntimeException('No Foreign Exchange Loss account found (expected code 7002). Add it to the Chart of Accounts.');
            }
            $lines = [
                ['account_id' => $apAccount->id, 'debit' => 0, 'credit' => $mag, 'narration' => 'AP FX adjustment — credit note application'],
                ['account_id' => $loss->id, 'debit' => $mag, 'credit' => 0, 'narration' => 'Exchange loss on credit note application'],
            ];
        }

        $journal = $this->engine->createJournal([
            'journal_date'   => now()->toDateString(),
            'journal_type'   => 'credit_note',
            'reference_type' => ApCreditNoteApplication::class,
            'reference_id'   => $application->id,
            'narration'      => "FX on AP credit note {$cn->credit_note_no} applied to bill #{$application->supplier_invoice_id}",
        ], $lines);

        $this->engine->postJournal($journal, $userId);
    }

    /** Void the FX adjustment journal (if any) for a removed/cancelled application. */
    public function voidApplicationFx(ApCreditNoteApplication $application, int $userId, string $reason = ''): void
    {
        GlJournal::where('reference_type', ApCreditNoteApplication::class)
            ->where('reference_id', $application->id)
            ->where('status', 'posted')
            ->get()
            ->each(fn ($j) => $this->engine->voidJournal($j->load('entries'), $userId, $reason));
    }

    /** Resolve [gainAccount, lossAccount] — mapping override, else by code. */
    private function resolveFxAccounts(): array
    {
        $gain = AccountMapping::where('mapping_type', 'forex_gain')
                ->whereNull('source_type')->whereNull('source_id')->where('is_active', true)->first()?->account
            ?? Account::where('code', '4102')->where('is_active', true)->first();

        $loss = AccountMapping::where('mapping_type', 'forex_loss')
                ->whereNull('source_type')->whereNull('source_id')->where('is_active', true)->first()?->account
            ?? Account::where('code', '7002')->where('is_active', true)->first();

        return [$gain, $loss];
    }

    private function resolveApAccount(): ?Account
    {
        return $this->resolveAccount('supplier_ap', null, null)
            ?? Account::where('code', '2011')->where('is_active', true)->first();
    }

    private function resolveAccount(string $mappingType, ?string $sourceType, ?int $sourceId): ?Account
    {
        $query = AccountMapping::where('mapping_type', $mappingType)->where('is_active', true);
        $sourceType === null ? $query->whereNull('source_type') : $query->where('source_type', $sourceType);
        $sourceId   === null ? $query->whereNull('source_id')   : $query->where('source_id', $sourceId);

        return $query->first()?->account;
    }
}
