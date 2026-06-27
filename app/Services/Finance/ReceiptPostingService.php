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
    public function __construct(
        private PostingEngine $engine,
        private ArAllocationService $arAllocation,
        private ApAllocationService $apAllocation,
    ) {}

    /**
     * Confirm a receipt: create and post a GL journal.
     * Journal: DR Bank/Cash, CR Trade Debtors (AR Control)
     */
    public function confirmReceipt(Receipt $receipt, int $userId): Receipt
    {
        if (!$receipt->isDraft()) {
            throw new \RuntimeException('Only draft receipts can be confirmed.');
        }

        if ($receipt->allocations()->doesntExist()) {
            throw new \RuntimeException(
                'Add at least one invoice allocation before confirming this receipt.'
            );
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
            $receipt->loadMissing('allocations');

            // The GL is kept in base currency. Cash actually received = receipt's
            // base value. AR is relieved per allocation at the rate each invoice was
            // booked at, so the difference vs the receipt rate is a realized FX
            // gain/loss. The unallocated remainder (on-account) uses the receipt rate.
            $receiptRate = (float) ($receipt->exchange_rate ?: 1);
            $cashBase    = round((float) ($receipt->base_amount ?? ($receipt->amount * $receiptRate)), 2);

            $arRelievedBase = 0.0;
            $allocatedAmt   = 0.0;
            foreach ($receipt->allocations as $alloc) {
                $invoice = $this->arAllocation->resolveInvoice($alloc->invoice_type, (int) $alloc->invoice_id);
                $invRate = $this->arAllocation->getExchangeRate($invoice, $alloc->invoice_type);
                $arRelievedBase += round((float) $alloc->allocated_amount * $invRate, 2);
                $allocatedAmt   += (float) $alloc->allocated_amount;
            }
            $unallocBase = round(max(0.0, (float) $receipt->amount - $allocatedAmt) * $receiptRate, 2);
            $crArTotal   = round($arRelievedBase + $unallocBase, 2);

            $fxNote = $this->fxNote($receipt->amount, $receipt->currency, $receipt->exchange_rate);

            $lines = [
                ['account_id' => $bankAccount->id, 'debit' => $cashBase, 'credit' => 0, 'narration' => "Receipt from {$customerName}{$fxNote}"],
                ['account_id' => $arAccount->id,   'debit' => 0, 'credit' => $crArTotal, 'narration' => 'Customer payment'],
            ];

            // AR: cash received minus AR relieved → positive = exchange gain.
            $fx = round($cashBase - $crArTotal, 2);
            if (abs($fx) >= 0.01) {
                $lines[] = $this->fxLine($fx > 0, abs($fx));
            }

            $journal = $this->engine->createJournal([
                'journal_date'   => $receipt->receipt_date->toDateString(),
                'journal_type'   => 'receipt',
                'reference_type' => Receipt::class,
                'reference_id'   => $receipt->id,
                'narration'      => "Receipt {$receipt->receipt_no} — {$customerName}",
            ], $lines);

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

            // A contact-linked voucher settles Accounts Payable — it must debit the
            // AP control account that the supplier invoice posting credited, NOT an
            // expense account (otherwise the cost is booked twice: once at invoice
            // approval, again here). The expense_account_id is ignored in this case.
            if ($voucher->customer_id) {
                $expenseAccount = $this->resolveApAccount();
                if (!$expenseAccount) {
                    throw new \RuntimeException(
                        'No AP control account mapped. Configure Account Mappings → AR/AP Controls.'
                    );
                }
            }

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

            $voucher->loadMissing('allocations');
            $voucherRate = (float) ($voucher->exchange_rate ?: 1);
            $cashBase    = round((float) ($voucher->base_amount ?? ($voucher->amount * $voucherRate)), 2);
            $fxNote      = $this->fxNote($voucher->amount, $voucher->currency, $voucher->exchange_rate);

            $lines = [];

            if ($voucher->customer_id) {
                // AP settlement: relieve AP per bill at its booked rate; the rate
                // difference vs the voucher rate is a realized FX gain/loss. The
                // unallocated remainder (on-account) uses the voucher rate.
                $apRelievedBase = 0.0;
                $allocatedAmt   = 0.0;
                foreach ($voucher->allocations as $alloc) {
                    $invoice = $this->apAllocation->resolveInvoice((int) $alloc->supplier_invoice_id);
                    $invRate = $this->apAllocation->getExchangeRate($invoice);
                    $apRelievedBase += round((float) $alloc->allocated_amount * $invRate, 2);
                    $allocatedAmt   += (float) $alloc->allocated_amount;
                }
                $unallocBase = round(max(0.0, (float) $voucher->amount - $allocatedAmt) * $voucherRate, 2);
                $drApTotal   = round($apRelievedBase + $unallocBase, 2);

                $lines[] = ['account_id' => $expenseAccount->id, 'debit' => $drApTotal, 'credit' => 0, 'narration' => "Payment to {$voucher->payee_name}{$fxNote}"];
                $lines[] = ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => $cashBase, 'narration' => 'Bank payment'];

                // AP: cash paid minus AP relieved → positive = exchange loss.
                $fx = round($cashBase - $drApTotal, 2);
                if (abs($fx) >= 0.01) {
                    $lines[] = $this->fxLine($fx < 0, abs($fx));
                }
            } else {
                // Direct expense voucher — no AP relief, no FX gain/loss.
                $lines[] = ['account_id' => $expenseAccount->id, 'debit' => $cashBase, 'credit' => 0, 'narration' => "Payment to {$voucher->payee_name}{$fxNote}"];
                $lines[] = ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => $cashBase, 'narration' => 'Bank payment'];
            }

            $journal = $this->engine->createJournal([
                'journal_date'   => $voucher->voucher_date->toDateString(),
                'journal_type'   => 'payment',
                'reference_type' => PaymentVoucher::class,
                'reference_id'   => $voucher->id,
                'narration'      => "Voucher {$voucher->voucher_no} — {$voucher->payee_name}",
            ], $lines);

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

    /**
     * Build the FX gain/loss journal line for the rounding/rate difference.
     * Gain → credit FX Gain (4102); Loss → debit FX Loss (7002).
     */
    private function fxLine(bool $isGain, float $magnitude): array
    {
        [$gain, $loss] = $this->resolveFxAccounts();

        if ($isGain) {
            if (!$gain) {
                throw new \RuntimeException('No Foreign Exchange Gain account found (expected code 4102). Add it to the Chart of Accounts.');
            }
            return ['account_id' => $gain->id, 'debit' => 0, 'credit' => $magnitude, 'narration' => 'Exchange gain on settlement'];
        }

        if (!$loss) {
            throw new \RuntimeException('No Foreign Exchange Loss account found (expected code 7002). Add it to the Chart of Accounts.');
        }
        return ['account_id' => $loss->id, 'debit' => $magnitude, 'credit' => 0, 'narration' => 'Exchange loss on settlement'];
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
