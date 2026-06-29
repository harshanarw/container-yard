<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ArCreditNote;
use App\Models\ArCreditNoteApplication;
use App\Models\ChargeCode;
use App\Models\GlJournal;
use Illuminate\Support\Facades\DB;

/**
 * Posts AR credit notes to the GL — the reverse of a sales invoice:
 *   DR Revenue (per line) + DR Output VAT,  CR AR Control
 * All in base currency. Reduces both receivables and recognised revenue.
 */
class ArCreditNotePostingService
{
    public function __construct(
        private PostingEngine $engine,
        private ArAllocationService $arAllocation,
    ) {}

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
     * Post a per-application FX adjustment when an approved credit note settles an
     * invoice booked at a different exchange rate than the credit note itself.
     *
     * The credit note relieves AR at its OWN rate when approved, so matching it to
     * an invoice booked at another rate leaves a residue in AR control of
     * applied × (invoiceRate − cnRate). This clears that residue to the FX
     * gain/loss accounts — the same realized FX that receipts recognise on
     * settlement. No-op when the rates match (the common case, e.g. a credit note
     * raised from the invoice), and a safe no-op until the credit note is posted.
     */
    public function postApplicationFx(ArCreditNote $cn, ArCreditNoteApplication $application, int $userId): void
    {
        if (!$cn->isApproved()) {
            return; // the AR relief this corrects only exists once the CN is posted
        }

        $invoice = $this->arAllocation->resolveInvoice($application->invoice_type, (int) $application->invoice_id);
        $invRate = $this->arAllocation->getExchangeRate($invoice, $application->invoice_type);
        $cnRate  = (float) ($cn->exchange_rate ?: 1);

        $residue = round((float) $application->applied_amount * ($invRate - $cnRate), 2);
        if (abs($residue) < 0.01) {
            return;
        }

        $arAccount = $this->resolveArAccount();
        if (!$arAccount) {
            throw new \RuntimeException('No AR control account mapped. Configure Account Mappings → AR/AP Controls.');
        }
        [$gain, $loss] = $this->resolveFxAccounts();

        if ($residue > 0) {
            // AR under-relieved (leftover debit) → clear it, recognise an FX loss.
            if (!$loss) {
                throw new \RuntimeException('No Foreign Exchange Loss account found (expected code 7002). Add it to the Chart of Accounts.');
            }
            $lines = [
                ['account_id' => $arAccount->id, 'debit' => 0, 'credit' => $residue, 'narration' => 'AR FX adjustment — credit note application'],
                ['account_id' => $loss->id, 'debit' => $residue, 'credit' => 0, 'narration' => 'Exchange loss on credit note application'],
            ];
        } else {
            // AR over-relieved (leftover credit) → clear it, recognise an FX gain.
            $mag = -$residue;
            if (!$gain) {
                throw new \RuntimeException('No Foreign Exchange Gain account found (expected code 4102). Add it to the Chart of Accounts.');
            }
            $lines = [
                ['account_id' => $arAccount->id, 'debit' => $mag, 'credit' => 0, 'narration' => 'AR FX adjustment — credit note application'],
                ['account_id' => $gain->id, 'debit' => 0, 'credit' => $mag, 'narration' => 'Exchange gain on credit note application'],
            ];
        }

        $journal = $this->engine->createJournal([
            'journal_date'   => now()->toDateString(),
            'journal_type'   => 'credit_note',
            'reference_type' => ArCreditNoteApplication::class,
            'reference_id'   => $application->id,
            'narration'      => "FX on credit note {$cn->credit_note_no} applied to {$application->invoice_type}#{$application->invoice_id}",
        ], $lines);

        $this->engine->postJournal($journal, $userId);
    }

    /** Void the FX adjustment journal (if any) for a removed/cancelled application. */
    public function voidApplicationFx(ArCreditNoteApplication $application, int $userId, string $reason = ''): void
    {
        GlJournal::where('reference_type', ArCreditNoteApplication::class)
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
