<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Mail\PaymentVoucherMail;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\PaymentAllocation;
use App\Models\PaymentVoucher;
use App\Services\ConfiguredMailer;
use App\Services\NotificationService;
use App\Services\Finance\ApAllocationService;
use App\Services\Finance\ReceiptPostingService;
use App\Support\HandlesMailErrors;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentVoucherController extends Controller
{
    use HandlesMailErrors;

    public function __construct(
        private ReceiptPostingService $postingService,
        private ApAllocationService $allocationService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('finance.vouchers.view');

        $query = PaymentVoucher::with('journal')
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        if ($request->filled('payee')) {
            $query->where('payee_name', 'like', '%' . $request->payee . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('voucher_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('voucher_date', '<=', $request->date_to);
        }

        $vouchers = $query->paginate(25)->withQueryString();

        return view('finance.vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        $this->authorize('finance.vouchers.create');

        $bankAccounts    = BankAccount::where('is_active', true)->orderBy('bank_name')->get();
        $expenseAccounts = Account::where('is_posting', true)
            ->where('is_active', true)
            ->orderBy('classification')
            ->orderBy('code')
            ->get();
        $suppliers = Customer::apContacts()->get(['id', 'code', 'name']);
        $currencies   = Currency::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        $baseCurrency = CompanySetting::baseCurrency();

        return view('finance.vouchers.create', compact('bankAccounts', 'expenseAccounts', 'suppliers', 'currencies', 'baseCurrency'));
    }

    public function store(Request $request)
    {
        $this->authorize('finance.vouchers.create');

        $validated = $request->validate([
            'voucher_date'      => ['required', 'date'],
            'customer_id'       => ['nullable', 'exists:customers,id'],
            'payee_name'        => ['required', 'string', 'max:150'],
            'bank_account_id'   => ['nullable', 'exists:bank_accounts,id'],
            'amount'            => ['required', 'numeric', 'min:0.0001'],
            'currency'          => ['required', 'string', 'max:10', 'exists:currencies,code'],
            'exchange_rate'     => ['required', 'numeric', 'min:0.000001'],
            'payment_method'    => ['required', 'in:cash,cheque,bank_transfer,online'],
            'cheque_no'         => ['nullable', 'string', 'max:50'],
            'reference_no'      => ['nullable', 'string', 'max:100'],
            'narration'         => ['required', 'string', 'max:255'],
            'expense_account_id' => ['nullable', 'exists:accounts,id'],
        ]);

        $validated['created_by']  = auth()->id();
        $validated['status']      = 'draft';
        // Snapshot the base/reporting-currency (LKR) value at entry time.
        $validated['base_amount'] = round((float) $validated['amount'] * (float) $validated['exchange_rate'], 4);

        $voucher = DB::transaction(function () use ($validated) {
            $validated['voucher_no'] = $this->nextVoucherNo();
            return PaymentVoucher::create($validated);
        });

        return redirect()->route('finance.vouchers.show', $voucher)
            ->with('success', "Voucher {$voucher->voucher_no} created successfully.");
    }

    /**
     * Invoice-first "Pay Bills" screen: pick a supplier/contact, see their open
     * (posted) supplier invoices, tick the ones being paid, then create (and
     * optionally post) the voucher + allocations in one step.
     */
    public function payBills(Request $request)
    {
        $this->authorize('finance.vouchers.create');

        $suppliers    = Customer::apContacts()->get(['id', 'code', 'name', 'currency']);
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('bank_name')->get();
        $currencies   = Currency::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        $baseCurrency = CompanySetting::baseCurrency();

        $supplier        = null;
        $pendingInvoices = collect();
        if ($request->filled('customer_id')) {
            $supplier        = Customer::find($request->customer_id);
            $pendingInvoices = $supplier
                ? $this->allocationService->pendingForSupplier((int) $supplier->id)
                : collect();
        }

        $canPost = auth()->user()->can('finance.vouchers.confirm');

        return view('finance.vouchers.pay', compact(
            'suppliers', 'bankAccounts', 'currencies', 'baseCurrency',
            'supplier', 'pendingInvoices', 'canPost'
        ));
    }

    public function storePayBills(Request $request)
    {
        $this->authorize('finance.vouchers.create');

        $validated = $request->validate([
            'customer_id'           => ['required', 'exists:customers,id'],
            'voucher_date'          => ['required', 'date'],
            'bank_account_id'       => ['nullable', 'exists:bank_accounts,id'],
            'currency'              => ['required', 'string', 'max:10', 'exists:currencies,code'],
            'exchange_rate'         => ['required', 'numeric', 'min:0.000001'],
            'payment_method'        => ['required', 'in:cash,cheque,bank_transfer,online'],
            'cheque_no'             => ['nullable', 'string', 'max:50'],
            'reference_no'          => ['nullable', 'string', 'max:100'],
            'narration'             => ['required', 'string', 'max:255'],
            'wht_type'              => ['nullable', 'string', 'max:50'],
            'wht_rate'              => ['nullable', 'numeric', 'min:0', 'max:100'],
            'wht_amount'            => ['nullable', 'numeric', 'min:0'],
            'action'                => ['nullable', 'in:draft,post'],
            'allocations'           => ['required', 'array', 'min:1'],
            'allocations.*.id'      => ['required', 'integer', 'min:1'],
            'allocations.*.amount'  => ['nullable', 'numeric', 'min:0'],
            'allocations.*.selected' => ['nullable'],
        ]);

        $selected = collect($validated['allocations'])
            ->filter(fn ($a) => !empty($a['selected']) && (float) ($a['amount'] ?? 0) > 0)
            ->values();

        if ($selected->isEmpty()) {
            return back()->withInput()->with('error', 'Select at least one bill and enter an amount to pay.');
        }

        $supplier = Customer::find($validated['customer_id']);
        $voucherCurrency = strtoupper($validated['currency']);
        $base = CompanySetting::baseCurrency();
        $rate = (float) $validated['exchange_rate'];

        try {
            $voucher = DB::transaction(function () use ($validated, $selected, $supplier, $voucherCurrency, $base, $rate) {
                $rows = [];
                foreach ($selected as $sel) {
                    $invoice = $this->allocationService->resolveInvoice((int) $sel['id']);

                    if ((int) $invoice->customer_id !== (int) $validated['customer_id']) {
                        throw new \RuntimeException("Bill {$invoice->invoice_no} does not belong to the selected supplier.");
                    }
                    if (!$invoice->isPosted()) {
                        throw new \RuntimeException("Bill {$invoice->invoice_no} is not posted to the GL yet.");
                    }

                    $invCurrency = strtoupper((string) $invoice->currency) ?: $base;
                    if ($invCurrency !== $voucherCurrency) {
                        throw new \RuntimeException(
                            "Bill {$invoice->invoice_no} is in {$invCurrency} but the voucher is in {$voucherCurrency}. "
                            . "Settle bills of one currency per voucher."
                        );
                    }

                    $outstanding = $this->allocationService->getOutstanding($invoice);
                    $amount      = round((float) $sel['amount'], 2);
                    if ($amount > round($outstanding + 0.005, 2)) {
                        throw new \RuntimeException(
                            "Amount for {$invoice->invoice_no} exceeds its outstanding balance of " . number_format($outstanding, 2) . '.'
                        );
                    }

                    $rows[] = ['invoice' => $invoice, 'amount' => $amount];
                }

                $totalAmount = round(collect($rows)->sum('amount'), 2);

                // WHT withheld from this payment (in the voucher currency). Cannot
                // exceed the gross being settled.
                $whtAmount = round((float) ($validated['wht_amount'] ?? 0), 2);
                if ($whtAmount > $totalAmount + 0.005) {
                    throw new \RuntimeException('Withholding tax cannot exceed the payment total.');
                }

                $voucher = PaymentVoucher::create([
                    'voucher_no'      => $this->nextVoucherNo(),
                    'voucher_date'    => $validated['voucher_date'],
                    'customer_id'     => $validated['customer_id'],
                    'payee_name'      => $supplier?->name ?? 'Supplier',
                    'bank_account_id' => $validated['bank_account_id'] ?? null,
                    'amount'          => $totalAmount,
                    'currency'        => $voucherCurrency,
                    'exchange_rate'   => $rate,
                    'base_amount'     => round($totalAmount * $rate, 4),
                    'wht_type'        => $validated['wht_type'] ?? null,
                    'wht_rate'        => round((float) ($validated['wht_rate'] ?? 0), 4),
                    'wht_amount'      => $whtAmount,
                    'payment_method'  => $validated['payment_method'],
                    'cheque_no'       => $validated['cheque_no'] ?? null,
                    'reference_no'    => $validated['reference_no'] ?? null,
                    'narration'       => $validated['narration'],
                    'created_by'      => auth()->id(),
                    'status'          => 'draft',
                ]);

                foreach ($rows as $r) {
                    $voucher->allocations()->create([
                        'supplier_invoice_id' => $r['invoice']->id,
                        'allocated_amount'    => $r['amount'],
                        'base_amount'         => round($r['amount'] * $rate, 4),
                    ]);
                    $this->allocationService->syncInvoiceStatus($r['invoice']);
                }

                return $voucher;
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if (($validated['action'] ?? 'draft') === 'post' && auth()->user()->can('finance.vouchers.confirm')) {
            try {
                $this->postingService->confirmVoucher($voucher, auth()->id());
                return redirect()->route('finance.vouchers.show', $voucher)
                    ->with('success', "Voucher {$voucher->voucher_no} created and posted to GL.");
            } catch (\Throwable $e) {
                return redirect()->route('finance.vouchers.show', $voucher)
                    ->with('error', 'Voucher saved as draft, but posting failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('finance.vouchers.show', $voucher)
            ->with('success', "Voucher {$voucher->voucher_no} created as draft.");
    }

    public function show(PaymentVoucher $voucher)
    {
        $this->authorize('finance.vouchers.view');

        $voucher->load(['bankAccount.glAccount', 'expenseAccount', 'journal', 'createdBy', 'voidedBy',
            'supplier', 'allocations.invoice']);

        // AP allocation context — only relevant when the voucher is tied to a contact.
        $pendingInvoices   = $voucher->customer_id
            ? $this->allocationService->pendingForSupplier($voucher->customer_id)
            : collect();
        $totalAllocated    = (float) $voucher->allocations->sum('allocated_amount');
        $unallocatedAmount = round((float) $voucher->amount - $totalAllocated, 2);

        return view('finance.vouchers.show', compact(
            'voucher', 'pendingInvoices', 'totalAllocated', 'unallocatedAmount'
        ));
    }

    /**
     * Allocate part of a draft voucher against a supplier invoice (AP sub-ledger).
     */
    public function storeAllocation(Request $request, PaymentVoucher $voucher)
    {
        $this->authorize('finance.vouchers.edit');

        if (!$voucher->isDraft()) {
            return back()->with('error', 'Allocations can only be added to draft vouchers.');
        }

        if (!$voucher->customer_id) {
            return back()->with('error', 'Link this voucher to a contact before allocating it to invoices.');
        }

        $validated = $request->validate([
            'supplier_invoice_id' => ['required', 'integer', 'min:1'],
            'allocated_amount'    => ['required', 'numeric', 'min:0.01'],
            'notes'               => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $invoice = $this->allocationService->resolveInvoice((int) $validated['supplier_invoice_id']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ((int) $invoice->customer_id !== (int) $voucher->customer_id) {
            return back()->with('error', 'That invoice belongs to a different contact.');
        }

        if (!$invoice->isPosted()) {
            return back()->with('error',
                'That invoice is not yet posted to the GL — post it before allocating a payment.');
        }

        // Treat a blank currency on either side as the base currency so the check
        // is consistent (base-vs-base passes; foreign-vs-base is blocked).
        $base            = CompanySetting::baseCurrency();
        $invoiceCurrency = strtoupper((string) $invoice->currency) ?: $base;
        $voucherCurrency = strtoupper((string) $voucher->currency) ?: $base;
        if ($invoiceCurrency !== $voucherCurrency) {
            return back()->with('error',
                "Currency mismatch: the voucher is in {$voucherCurrency} but the invoice is in {$invoiceCurrency}. "
                . "Cross-currency settlement isn't supported yet.");
        }

        try {
            DB::transaction(function () use ($voucher, $invoice, $validated) {
                $locked = PaymentVoucher::lockForUpdate()->find($voucher->id);

                $outstanding = $this->allocationService->getOutstanding($invoice);
                if ((float) $validated['allocated_amount'] > round($outstanding + 0.005, 2)) {
                    throw new \RuntimeException(
                        'Allocated amount exceeds the invoice outstanding balance of ' . number_format($outstanding, 2) . '.'
                    );
                }

                $totalAllocated = (float) $locked->allocations()->sum('allocated_amount');
                $remaining      = (float) $locked->amount - $totalAllocated;
                if ((float) $validated['allocated_amount'] > round($remaining + 0.005, 2)) {
                    throw new \RuntimeException(
                        "Allocated amount exceeds the voucher's unallocated balance of " . number_format($remaining, 2) . '.'
                    );
                }

                // Base-currency value applied from this voucher (allocated × voucher rate).
                $validated['base_amount'] = round(
                    (float) $validated['allocated_amount'] * (float) ($locked->exchange_rate ?: 1), 4
                );

                $locked->allocations()->create($validated);
                $this->allocationService->syncInvoiceStatus($invoice);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Allocation added.');
    }

    public function deleteAllocation(PaymentVoucher $voucher, PaymentAllocation $allocation)
    {
        $this->authorize('finance.vouchers.edit');

        if (!$voucher->isDraft()) {
            return back()->with('error', 'Allocations can only be removed from draft vouchers.');
        }

        if ($allocation->payment_voucher_id !== $voucher->id) {
            abort(404);
        }

        $invoiceId = $allocation->supplier_invoice_id;
        $allocation->delete();

        try {
            $invoice = $this->allocationService->resolveInvoice($invoiceId);
            $this->allocationService->syncInvoiceStatus($invoice);
        } catch (\Throwable) {
            // best-effort
        }

        return back()->with('success', 'Allocation removed.');
    }

    public function pdf(PaymentVoucher $voucher, Request $request)
    {
        $this->authorize('finance.vouchers.pdf');

        $voucher->loadMissing(['supplier', 'bankAccount', 'allocations.invoice', 'createdBy']);

        $size  = $request->query('size') === 'half' ? 'half' : 'a4';
        $paper = $size === 'half' ? 'a5' : 'a4';

        $pdf = Pdf::loadView('finance.vouchers.pdf', ['voucher' => $voucher, 'size' => $size])
            ->setPaper($paper, 'portrait')
            ->set_option('defaultFont', 'sans-serif')
            ->set_option('isHtml5ParserEnabled', true)
            ->set_option('isRemoteEnabled', false);

        $filename = 'Voucher-' . $voucher->voucher_no . ($size === 'half' ? '-slip' : '') . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    public function email(Request $request, PaymentVoucher $voucher)
    {
        $this->authorize('finance.vouchers.email');

        $validated = $request->validate([
            'to_email' => ['required', 'email'],
            'cc_email' => ['nullable', 'email'],
            'message'  => ['nullable', 'string', 'max:1000'],
            'format'   => ['nullable', 'in:a4,half'],
        ]);

        // Send via the configured mailer (DB Email Config) — not the raw .env
        // mailer — rebuilt fresh each attempt; retry once on a transient DNS error.
        $error = $this->sendMailWithRetry(function () use ($validated, $voucher) {
            $pending = ConfiguredMailer::forCategory('voucher')->to($validated['to_email']);
            if (!empty($validated['cc_email'])) {
                $pending->cc($validated['cc_email']);
            }
            $pending->send(new PaymentVoucherMail($voucher, $validated['message'] ?? null, $validated['format'] ?? 'a4'));
        });

        if ($error) {
            $msg = $this->friendlyMailError($error);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        $msg = "Voucher {$voucher->voucher_no} emailed to {$validated['to_email']}.";

        NotificationService::notify(
            auth()->user(),
            'Voucher emailed',
            $msg,
            'success',
            route('finance.vouchers.show', $voucher),
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return back()->with('success', $msg);
    }

    public function confirm(PaymentVoucher $voucher)
    {
        $this->authorize('finance.vouchers.confirm');

        try {
            $this->postingService->confirmVoucher($voucher, auth()->id());
            return back()->with('success', "Voucher {$voucher->voucher_no} confirmed and posted to GL.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(Request $request, PaymentVoucher $voucher)
    {
        $this->authorize('finance.vouchers.void');

        $reason = $request->input('reason', '');

        // Capture the invoices this voucher settled BEFORE voiding — once the
        // voucher flips to 'voided' its allocations stop counting toward the
        // allocated total, so any invoice it had marked paid/partially_paid must
        // be re-synced or it is left with a stale (wrongly-paid) status.
        $invoiceIds = $voucher->allocations()->pluck('supplier_invoice_id')->unique();

        try {
            DB::transaction(function () use ($voucher, $reason, $invoiceIds) {
                $this->postingService->voidVoucher($voucher, auth()->id(), $reason);
                foreach ($invoiceIds as $invId) {
                    $invoice = $this->allocationService->resolveInvoice((int) $invId);
                    $this->allocationService->syncInvoiceStatus($invoice);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Voucher {$voucher->voucher_no} has been voided.");
    }

    private function nextVoucherNo(): string
    {
        return app(\App\Services\NumberSequenceService::class)->generate('payment_voucher');
    }
}
