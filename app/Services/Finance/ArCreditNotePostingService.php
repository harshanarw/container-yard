<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ArCreditNote;
use App\Models\ChargeCode;
use Illuminate\Support\Facades\DB;

/**
 * Posts AR credit notes to the GL — the reverse of a sales invoice:
 *   DR Revenue (per line) + DR Output VAT,  CR AR Control
 * All in base currency. Reduces both receivables and recognised revenue.
 */
class ArCreditNotePostingService
{
    public function __construct(private PostingEngine $engine) {}

    public function approve(ArCreditNote $cn, int $userId): ArCreditNote
    {
        if (!$cn->isDraft()) {
            throw new \RuntimeException('Only draft credit notes can be approved.');
        }

        $cn->loadMissing(['lines.revenueAccount', 'customer']);

        if ($cn->lines->isEmpty()) {
            throw new \RuntimeException('Add at least one line before approving the credit note.');
        }

        return DB::transaction(function () use ($cn, $userId) {
            $arAccount = $this->resolveArAccount();
            if (!$arAccount) {
                throw new \RuntimeException('No AR control account mapped. Configure Account Mappings → AR/AP Controls.');
            }

            $rate       = (float) ($cn->exchange_rate ?: 1);
            $defaultRev = Account::where('code', '4001')->where('is_active', true)->first();

            // Debit revenue per line (reverse the income), in base currency.
            $debits = [];
            foreach ($cn->lines as $line) {
                $acc = $line->revenueAccount
                    ?? ($line->charge_code_id
                        ? $this->resolveAccount('charge_revenue', ChargeCode::class, $line->charge_code_id)
                        : null)
                    ?? $defaultRev;

                if (!$acc) {
                    throw new \RuntimeException("No revenue account resolved for line: {$line->description}");
                }

                $debits[] = [
                    'account_id' => $acc->id,
                    'debit'      => round((float) $line->amount * $rate, 2),
                    'credit'     => 0,
                    'narration'  => $line->description,
                ];
            }

            // Reverse output VAT, if any.
            $tax = (float) $cn->tax_amount;
            if ($tax > 0) {
                $taxAcc = $this->resolveAccount('tax_output', null, null)
                    ?? Account::where('code', '2101')->where('is_active', true)->first();
                $vatBase = round($tax * $rate, 2);
                if ($taxAcc) {
                    $debits[] = ['account_id' => $taxAcc->id, 'debit' => $vatBase, 'credit' => 0, 'narration' => 'Output VAT reversal'];
                } else {
                    $last = count($debits) - 1;
                    $debits[$last]['debit'] = round($debits[$last]['debit'] + $vatBase, 2);
                }
            }

            // Credit AR = sum of debits (keeps the journal balanced to the cent).
            $crAr = round(array_sum(array_column($debits, 'debit')), 2);

            $lines = $debits;
            $lines[] = [
                'account_id' => $arAccount->id,
                'debit'      => 0,
                'credit'     => $crAr,
                'narration'  => 'Trade debtors — credit note',
            ];

            $journal = $this->engine->createJournal([
                'journal_date'   => $cn->credit_date->toDateString(),
                'journal_type'   => 'credit_note',
                'reference_type' => ArCreditNote::class,
                'reference_id'   => $cn->id,
                'narration'      => "Credit Note {$cn->credit_note_no} — " . ($cn->customer->name ?? 'Customer'),
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

    /** Void the GL journal and mark the credit note cancelled. */
    public function cancel(ArCreditNote $cn, int $userId, string $reason = ''): void
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

    private function resolveArAccount(): ?Account
    {
        return $this->resolveAccount('customer_ar', null, null)
            ?? Account::where('code', '1101')->where('is_active', true)->first();
    }

    private function resolveAccount(string $mappingType, ?string $sourceType, ?int $sourceId): ?Account
    {
        $query = AccountMapping::where('mapping_type', $mappingType)->where('is_active', true);
        $sourceType === null ? $query->whereNull('source_type') : $query->where('source_type', $sourceType);
        $sourceId   === null ? $query->whereNull('source_id')   : $query->where('source_id', $sourceId);

        return $query->first()?->account;
    }
}
