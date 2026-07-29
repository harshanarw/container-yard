<?php

namespace App\Services\Overtime;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\OtReceipt;
use App\Services\CurrencyService;
use App\Services\Finance\PostingEngine;

/**
 * Posts an overtime receipt straight to the GL on payment: DR Bank/Cash, CR
 * Overtime (OT) Revenue (4009). The OT charge is tax-exempt, so there is no VAT
 * leg. Mirrors ReceiptPostingService (direct PostingEngine, not an AR invoice).
 */
class OtReceiptPostingService
{
    public function __construct(private PostingEngine $engine)
    {
    }

    public function confirm(OtReceipt $receipt, ?int $bankAccountId, string $paymentMethod, int $userId): OtReceipt
    {
        if ($receipt->status !== 'generated') {
            throw new \RuntimeException('Only a generated OT receipt can be confirmed.');
        }

        $amount = round((float) $receipt->total_amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('OT receipt amount must be greater than zero.');
        }

        $currency = $receipt->currency ?: CurrencyService::defaultCurrency();

        $bankGl = $this->receivedAccount($bankAccountId, $paymentMethod);
        $income = $this->otIncomeAccount();

        $lines = [
            [
                'account_id' => $bankGl->id, 'debit' => $amount, 'credit' => 0,
                'narration'  => "OT Receipt {$receipt->receipt_no} — " . ($receipt->customer->name ?? ''),
                'currency'   => $currency, 'exchange_rate' => 1, 'txn_debit' => $amount, 'txn_credit' => 0,
            ],
            [
                'account_id' => $income->id, 'debit' => 0, 'credit' => $amount,
                'narration'  => "Overtime income — BL {$receipt->bl_number}",
                'currency'   => $currency, 'exchange_rate' => 1, 'txn_debit' => 0, 'txn_credit' => $amount,
            ],
        ];

        $journal = $this->engine->createJournal([
            'journal_date'   => now()->toDateString(),
            'journal_type'   => 'receipt',
            'reference_type' => OtReceipt::class,
            'reference_id'   => $receipt->id,
            'narration'      => "OT Receipt {$receipt->receipt_no} — " . ($receipt->customer->name ?? ''),
        ], $lines);

        $this->engine->postJournal($journal, $userId);

        $receipt->update([
            'status'          => 'paid',
            'journal_id'      => $journal->id,
            'bank_account_id' => $bankAccountId,
            'payment_method'  => $paymentMethod,
            'paid_at'         => now(),
        ]);

        return $receipt->refresh();
    }

    /** Reverse the posted journal and void the receipt. */
    public function void(OtReceipt $receipt, string $reason, int $userId): void
    {
        if ($receipt->journal_id && $receipt->journal) {
            $this->engine->voidJournal($receipt->journal, $userId, $reason);
        }
        $receipt->update(['status' => 'void', 'remarks' => trim(($receipt->remarks ? $receipt->remarks . ' | ' : '') . 'VOID: ' . $reason)]);
    }

    /** DR side — the received bank/cash GL account (cash falls back to 1011). */
    private function receivedAccount(?int $bankAccountId, string $paymentMethod): Account
    {
        $bank = $bankAccountId ? BankAccount::find($bankAccountId) : null;
        $gl   = $bank?->glAccount;

        if (! $gl && $paymentMethod === 'cash') {
            $gl = Account::where('code', '1011')->where('is_active', true)->first()
                ?? Account::where('is_cash_bank', true)->where('is_active', true)->orderBy('code')->first();
        }

        if (! $gl) {
            throw new \RuntimeException('No received (bank/cash) account resolved for the OT receipt.');
        }

        return $gl;
    }

    /** CR side — Overtime (OT) Revenue (4009). */
    private function otIncomeAccount(): Account
    {
        $account = Account::where('code', '4009')->where('is_active', true)->first();
        if (! $account) {
            throw new \RuntimeException('OT revenue account 4009 is not configured.');
        }

        return $account;
    }
}
