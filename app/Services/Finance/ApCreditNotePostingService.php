<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ApCreditNote;
use App\Models\ChargeCode;
use Illuminate\Support\Facades\DB;

/**
 * Posts AP credit notes to the GL — the reverse of a supplier bill:
 *   DR AP Control,  CR Expense (per line) + CR Input VAT
 * All in base currency. Reduces both payables and recognised expense.
 */
class ApCreditNotePostingService
{
    public function __construct(private PostingEngine $engine) {}

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

        DB::transaction(function () use ($cn, $userId, $reason) {
            if ($cn->journal) {
                $this->engine->voidJournal($cn->journal->load('entries'), $userId, $reason);
            }
            $cn->update(['status' => 'cancelled']);
        });
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
