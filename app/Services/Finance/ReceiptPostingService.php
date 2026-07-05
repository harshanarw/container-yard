<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\GlJournal;
use App\Models\Receipt;
use App\Models\PaymentVoucher;
use App\Services\CurrencyService;
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

            // Bank and AR legs are in the receipt's transaction currency; the FX leg
            // (below) is in base currency and uses the engine's base defaults.
            $rcCcy = strtoupper((string) ($receipt->currency ?? CurrencyService::defaultCurrency()));
            $rcAmt = (float) $receipt->amount;

            // WHT the customer withheld and remitted to the IRD on our behalf. The AR
            // is relieved at the gross; the bank receives the net; the withheld portion
            // is a WHT Receivable we can claim. wht_amount is in the receipt currency.
            $whtTxn  = round((float) $receipt->wht_amount, 2);
            $whtBase = round($whtTxn * $receiptRate, 2);
            $whtAccount = null;
            if ($whtBase > 0.005) {
                $whtAccount = $receipt->wht_account_id
                    ? Account::where('id', $receipt->wht_account_id)->where('is_active', true)->first()
                    : $this->resolveWhtReceivable();
                if (!$whtAccount) {
                    throw new \RuntimeException(
                        'No WHT Receivable account resolved (expected code ' . config('wht.receivable_account_code', '1103')
                        . '). Add it to the Chart of Accounts or map it under Account Mappings.'
                    );
                }
            }

            // AR is relieved at the invoices' booked rates (cashBase uses the receipt
            // rate); the difference is the FX leg. So the AR line's effective rate is
            // crArTotal / amount, keeping base = txn × rate consistent on every line.
            $arRate = $rcAmt > 0 ? round($crArTotal / $rcAmt, 6) : $receiptRate;
            $lines = [
                ['account_id' => $bankAccount->id, 'debit' => round($cashBase - $whtBase, 2), 'credit' => 0, 'narration' => "Receipt from {$customerName}{$fxNote}",
                 'currency' => $rcCcy, 'exchange_rate' => $receiptRate, 'txn_debit' => round($rcAmt - $whtTxn, 2), 'txn_credit' => 0],
                ['account_id' => $arAccount->id,   'debit' => 0, 'credit' => $crArTotal, 'narration' => 'Customer payment',
                 'currency' => $rcCcy, 'exchange_rate' => $arRate, 'txn_debit' => 0, 'txn_credit' => $rcAmt],
            ];

            // Withholding tax the customer deducted → WHT Receivable (base currency).
            if ($whtBase > 0.005 && $whtAccount) {
                $lines[] = ['account_id' => $whtAccount->id, 'debit' => $whtBase, 'credit' => 0, 'narration' => 'WHT withheld by customer — receivable'];
            }

            // AR: gross settlement minus AR relieved → positive = exchange gain.
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

            // Now that the receipt counts as a confirmed settlement, re-sync each
            // invoice's status (issued → paid, etc.). At draft-creation time the
            // allocation did not yet count, so this is the point it takes effect.
            foreach ($receipt->allocations as $alloc) {
                $invoice = $this->arAllocation->resolveInvoice($alloc->invoice_type, (int) $alloc->invoice_id);
                $this->arAllocation->syncInvoiceStatus($invoice, $alloc->invoice_type);
            }

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

            // Bank and AP/expense legs are in the voucher's transaction currency; any
            // FX leg is base currency (engine base defaults).
            $vrCcy = strtoupper((string) ($voucher->currency ?? CurrencyService::defaultCurrency()));
            $vrAmt = (float) $voucher->amount;

            // WHT withheld from the supplier and remitted to the IRD. The AP/expense
            // is booked at the gross; the bank pays the net; the withheld portion is
            // credited to WHT Payable. wht_amount is in the voucher (txn) currency.
            $whtTxn  = round((float) $voucher->wht_amount, 2);
            $whtBase = round($whtTxn * $voucherRate, 2);
            $whtAccount = null;
            if ($whtBase > 0.005) {
                $whtAccount = $voucher->wht_account_id
                    ? Account::where('id', $voucher->wht_account_id)->where('is_active', true)->first()
                    : $this->resolveWhtPayable();
                if (!$whtAccount) {
                    throw new \RuntimeException(
                        'No WHT Payable account resolved (expected code ' . config('wht.payable_account_code', '2103')
                        . '). Add it to the Chart of Accounts or map it under Account Mappings.'
                    );
                }
            }

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

                // AP is relieved at the bills' booked rates; the bank pays at the
                // voucher rate; the FX leg is the difference. Use the effective rate on
                // the AP line so base = txn × rate holds.
                $apRate = $vrAmt > 0 ? round($drApTotal / $vrAmt, 6) : $voucherRate;
                $lines[] = ['account_id' => $expenseAccount->id, 'debit' => $drApTotal, 'credit' => 0, 'narration' => "Payment to {$voucher->payee_name}{$fxNote}",
                            'currency' => $vrCcy, 'exchange_rate' => $apRate, 'txn_debit' => $vrAmt, 'txn_credit' => 0];
                $lines[] = ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => round($cashBase - $whtBase, 2), 'narration' => 'Bank payment',
                            'currency' => $vrCcy, 'exchange_rate' => $voucherRate, 'txn_debit' => 0, 'txn_credit' => round($vrAmt - $whtTxn, 2)];

                // AP: gross settlement minus AP relieved → positive = exchange loss.
                $fx = round($cashBase - $drApTotal, 2);
                if (abs($fx) >= 0.01) {
                    $lines[] = $this->fxLine($fx < 0, abs($fx));
                }
            } else {
                // Direct expense voucher — no AP relief, no FX gain/loss.
                $lines[] = ['account_id' => $expenseAccount->id, 'debit' => $cashBase, 'credit' => 0, 'narration' => "Payment to {$voucher->payee_name}{$fxNote}",
                            'currency' => $vrCcy, 'exchange_rate' => $voucherRate, 'txn_debit' => $vrAmt, 'txn_credit' => 0];
                $lines[] = ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => round($cashBase - $whtBase, 2), 'narration' => 'Bank payment',
                            'currency' => $vrCcy, 'exchange_rate' => $voucherRate, 'txn_debit' => 0, 'txn_credit' => round($vrAmt - $whtTxn, 2)];
            }

            // Withholding tax credited to WHT Payable (base currency — remitted in LKR).
            if ($whtBase > 0.005 && $whtAccount) {
                $lines[] = ['account_id' => $whtAccount->id, 'debit' => 0, 'credit' => $whtBase, 'narration' => 'WHT withheld — payable to IRD'];
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

            // Re-sync each settled bill's status now the voucher counts as a
            // confirmed settlement (direct-expense vouchers have no allocations).
            foreach ($voucher->allocations as $alloc) {
                $invoice = $this->apAllocation->resolveInvoice((int) $alloc->supplier_invoice_id);
                $this->apAllocation->syncInvoiceStatus($invoice);
            }

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

    /** WHT Payable (liability) — mapping override, else config/COA code (2103). */
    private function resolveWhtPayable(): ?Account
    {
        return AccountMapping::where('mapping_type', 'wht_payable')
                ->whereNull('source_type')->whereNull('source_id')->where('is_active', true)->first()?->account
            ?? Account::where('code', config('wht.payable_account_code', '2103'))->where('is_active', true)->first();
    }

    /** WHT Receivable (asset) — mapping override, else config/COA code (1103). */
    private function resolveWhtReceivable(): ?Account
    {
        return AccountMapping::where('mapping_type', 'wht_receivable')
                ->whereNull('source_type')->whereNull('source_id')->where('is_active', true)->first()?->account
            ?? Account::where('code', config('wht.receivable_account_code', '1103'))->where('is_active', true)->first();
    }
}
