<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\GlJournal;
use App\Models\Receipt;
use App\Models\PaymentVoucher;
use Illuminate\Support\Facades\DB;

class ReceiptPostingService
{
    public function __construct(private PostingEngine $engine) {}

    /**
     * Confirm a receipt: create and post a GL journal.
     * Journal: DR Bank/Cash, CR Trade Debtors (AR Control)
     */
    public function confirmReceipt(Receipt $receipt, int $userId): Receipt
    {
        if (!$receipt->isDraft()) {
            throw new \RuntimeException('Only draft receipts can be confirmed.');
        }

        return DB::transaction(function () use ($receipt, $userId) {
            $arAccount   = $this->resolveArAccount();
            $bankAccount = $receipt->bankAccount?->glAccount;

            // Cash receipts: look up cash/petty cash account (is_cash_bank=true, code starts with 1011)
            if (!$bankAccount && $receipt->payment_method === 'cash') {
                $bankAccount = Account::where('code', '1011')->where('is_active', true)->first();
            }

            if (!$bankAccount) {
                throw new \RuntimeException(
                    'No GL account linked to the selected bank account. Edit the bank account and assign a GL account.'
                );
            }

            if (!$arAccount) {
                throw new \RuntimeException(
                    'No AR control account mapped. Configure Account Mappings → AR/AP Controls.'
                );
            }

            $customerName = $receipt->customer?->name ?? 'Unknown';

            // GL is maintained in the base/reporting currency. Convert the
            // receipt's foreign-currency amount before posting (both legs use
            // the same converted value, so the journal stays balanced).
            $baseAmount = $this->toBaseAmount($receipt->amount, $receipt->exchange_rate);
            $fxNote     = $this->fxNote($receipt->amount, $receipt->currency, $receipt->exchange_rate);

            $journal = $this->engine->createJournal([
                'journal_date'   => $receipt->receipt_date->toDateString(),
                'journal_type'   => 'receipt',
                'reference_type' => Receipt::class,
                'reference_id'   => $receipt->id,
                'narration'      => "Receipt {$receipt->receipt_no} — {$customerName}",
            ], [
                [
                    'account_id' => $bankAccount->id,
                    'debit'      => $baseAmount,
                    'credit'     => 0,
                    'narration'  => "Receipt from {$customerName}{$fxNote}",
                ],
                [
                    'account_id' => $arAccount->id,
                    'debit'      => 0,
                    'credit'     => $baseAmount,
                    'narration'  => "Customer payment",
                ],
            ]);

            $this->engine->postJournal($journal, $userId);

            $receipt->update([
                'journal_id' => $journal->id,
                'status'     => 'confirmed',
            ]);

            return $receipt->fresh(['journal', 'customer']);
        });
    }

    /**
     * Void a confirmed receipt: void the GL journal, mark receipt voided.
     */
    public function voidReceipt(Receipt $receipt, int $userId, string $reason = ''): void
    {
        if (!$receipt->isConfirmed()) {
            throw new \RuntimeException('Only confirmed receipts can be voided.');
        }

        DB::transaction(function () use ($receipt, $userId, $reason) {
            if ($receipt->journal) {
                $this->engine->voidJournal($receipt->journal->load('entries'), $userId, $reason);
            }
            $receipt->update([
                'status'    => 'voided',
                'voided_by' => $userId,
                'voided_at' => now(),
            ]);
        });
    }

    /**
     * Confirm a payment voucher: create and post a GL journal.
     * Journal: DR Expense/AP account, CR Bank/Cash
     */
    public function confirmVoucher(PaymentVoucher $voucher, int $userId): PaymentVoucher
    {
        if (!$voucher->isDraft()) {
            throw new \RuntimeException('Only draft vouchers can be confirmed.');
        }

        return DB::transaction(function () use ($voucher, $userId) {
            $expenseAccount = $voucher->expenseAccount;
            $bankAccount    = $voucher->bankAccount?->glAccount;

            if (!$expenseAccount && $voucher->payment_method !== 'cash') {
                throw new \RuntimeException(
                    'No expense/AP account selected for this voucher.'
                );
            }

            if (!$bankAccount && $voucher->payment_method === 'cash') {
                $bankAccount = Account::where('code', '1011')->where('is_active', true)->first();
            }

            if (!$bankAccount) {
                throw new \RuntimeException(
                    'No GL account linked to the selected bank account.'
                );
            }

            // If no expense account chosen, default to Trade Creditors AP
            if (!$expenseAccount) {
                $expenseAccount = $this->resolveApAccount();
            }

            if (!$expenseAccount) {
                throw new \RuntimeException(
                    'No expense or AP account resolved. Configure Account Mappings → AR/AP Controls or select an account on the voucher.'
                );
            }

            // Convert the voucher's foreign-currency amount into base currency
            // for the GL (both legs use the same value → balanced journal).
            $baseAmount = $this->toBaseAmount($voucher->amount, $voucher->exchange_rate);
            $fxNote     = $this->fxNote($voucher->amount, $voucher->currency, $voucher->exchange_rate);

            $journal = $this->engine->createJournal([
                'journal_date'   => $voucher->voucher_date->toDateString(),
                'journal_type'   => 'payment',
                'reference_type' => PaymentVoucher::class,
                'reference_id'   => $voucher->id,
                'narration'      => "Voucher {$voucher->voucher_no} — {$voucher->payee_name}",
            ], [
                [
                    'account_id' => $expenseAccount->id,
                    'debit'      => $baseAmount,
                    'credit'     => 0,
                    'narration'  => "Payment to {$voucher->payee_name}{$fxNote}",
                ],
                [
                    'account_id' => $bankAccount->id,
                    'debit'      => 0,
                    'credit'     => $baseAmount,
                    'narration'  => "Bank payment",
                ],
            ]);

            $this->engine->postJournal($journal, $userId);

            $voucher->update([
                'journal_id' => $journal->id,
                'status'     => 'confirmed',
            ]);

            return $voucher->fresh(['journal']);
        });
    }

    /**
     * Void a confirmed payment voucher.
     */
    public function voidVoucher(PaymentVoucher $voucher, int $userId, string $reason = ''): void
    {
        if (!$voucher->isConfirmed()) {
            throw new \RuntimeException('Only confirmed vouchers can be voided.');
        }

        DB::transaction(function () use ($voucher, $userId, $reason) {
            if ($voucher->journal) {
                $this->engine->voidJournal($voucher->journal->load('entries'), $userId, $reason);
            }
            $voucher->update([
                'status'    => 'voided',
                'voided_by' => $userId,
                'voided_at' => now(),
            ]);
        });
    }

    /**
     * Convert a transaction amount into base/reporting currency for the GL.
     * exchange_rate is "base units per 1 transaction-currency unit"
     * (defaults to 1.0 for same-currency transactions).
     */
    private function toBaseAmount($amount, $exchangeRate): float
    {
        $rate = (float) ($exchangeRate ?: 1);

        return round((float) $amount * $rate, 2);
    }

    /**
     * Short traceability suffix shown on the GL line when the transaction was
     * in a foreign currency. Empty for base-currency (rate 1) transactions.
     */
    private function fxNote($amount, ?string $currency, $exchangeRate): string
    {
        $rate = (float) ($exchangeRate ?: 1);
        if ($rate == 1.0) {
            return '';
        }

        return ' (' . number_format((float) $amount, 2) . ' ' . ($currency ?? '') . ' @ ' . rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.') . ')';
    }

    private function resolveArAccount(): ?Account
    {
        $mapping = AccountMapping::where('mapping_type', 'customer_ar')
            ->whereNull('source_type')
            ->whereNull('source_id')
            ->where('is_active', true)
            ->first();

        return $mapping?->account
            ?? Account::where('code', '1101')->where('is_active', true)->first();
    }

    private function resolveApAccount(): ?Account
    {
        $mapping = AccountMapping::where('mapping_type', 'supplier_ap')
            ->whereNull('source_type')
            ->whereNull('source_id')
            ->where('is_active', true)
            ->first();

        return $mapping?->account
            ?? Account::where('code', '2011')->where('is_active', true)->first();
    }
}
