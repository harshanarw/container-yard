<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Mail\ReceiptMail;
use App\Models\BankAccount;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Services\Finance\ArAllocationService;
use App\Services\Finance\ReceiptPostingService;
use App\Support\HandlesMailErrors;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReceiptController extends Controller
{
    use HandlesMailErrors;

    public function __construct(
        private ReceiptPostingService $postingService,
        private ArAllocationService   $allocationService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('finance.receipts.view');

        $query = Receipt::with(['customer', 'journal'])
            ->orderByDesc('receipt_date')
            ->orderByDesc('id');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('receipt_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('receipt_date', '<=', $request->date_to);
        }

        $receipts  = $query->paginate(25)->withQueryString();
        $customers = Customer::orderBy('name')->get(['id', 'name']);

        return view('finance.receipts.index', compact('receipts', 'customers'));
    }

    public function create()
    {
        $this->authorize('finance.receipts.create');

        $customers    = Customer::where('status', 'active')->orderBy('name')->get(['id', 'name', 'currency']);
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('bank_name')->get();
        $currencies   = Currency::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        $baseCurrency = CompanySetting::baseCurrency();

        return view('finance.receipts.create', compact('customers', 'bankAccounts', 'currencies', 'baseCurrency'));
    }

    public function store(Request $request)
    {
        $this->authorize('finance.receipts.create');

        $validated = $request->validate([
            'receipt_date'    => ['required', 'date'],
            'customer_id'     => ['required', 'exists:customers,id'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'amount'          => ['required', 'numeric', 'min:0.0001'],
            'currency'        => ['required', 'string', 'max:10', 'exists:currencies,code'],
            'exchange_rate'   => ['required', 'numeric', 'min:0.000001'],
            'payment_method'  => ['required', 'in:cash,cheque,bank_transfer,online'],
            'cheque_no'       => ['nullable', 'string', 'max:50'],
            'reference_no'    => ['nullable', 'string', 'max:100'],
            'narration'       => ['required', 'string', 'max:255'],
        ]);

        $validated['created_by']  = auth()->id();
        $validated['status']      = 'draft';
        // Snapshot the base/reporting-currency (LKR) value at entry time.
        $validated['base_amount'] = round((float) $validated['amount'] * (float) $validated['exchange_rate'], 4);

        $receipt = DB::transaction(function () use ($validated) {
            $validated['receipt_no'] = $this->nextReceiptNo();
            return Receipt::create($validated);
        });

        return redirect()->route('finance.receipts.show', $receipt)
            ->with('success', "Receipt {$receipt->receipt_no} created successfully.");
    }

    /**
     * Invoice-first "Receive Payment" screen: pick a customer, see their open AR
     * invoices, tick the ones being paid, then create (and optionally post) the
     * receipt + allocations in one step.
     */
    public function receive(Request $request)
    {
        $this->authorize('finance.receipts.create');

        $customers    = Customer::where('status', 'active')->orderBy('name')->get(['id', 'name', 'currency']);
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('bank_name')->get();
        $currencies   = Currency::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        $baseCurrency = CompanySetting::baseCurrency();

        $customer        = null;
        $pendingInvoices = collect();
        if ($request->filled('customer_id')) {
            $customer        = Customer::find($request->customer_id);
            $pendingInvoices = $customer
                ? $this->allocationService->pendingForCustomer((int) $customer->id)
                : collect();
        }

        $canPost = auth()->user()->can('finance.receipts.confirm');

        return view('finance.receipts.receive', compact(
            'customers', 'bankAccounts', 'currencies', 'baseCurrency',
            'customer', 'pendingInvoices', 'canPost'
        ));
    }

    public function storeReceivePayment(Request $request)
    {
        $this->authorize('finance.receipts.create');

        $validated = $request->validate([
            'customer_id'           => ['required', 'exists:customers,id'],
            'receipt_date'          => ['required', 'date'],
            'bank_account_id'       => ['nullable', 'exists:bank_accounts,id'],
            'currency'              => ['required', 'string', 'max:10', 'exists:currencies,code'],
            'exchange_rate'         => ['required', 'numeric', 'min:0.000001'],
            'payment_method'        => ['required', 'in:cash,cheque,bank_transfer,online'],
            'cheque_no'             => ['nullable', 'string', 'max:50'],
            'reference_no'          => ['nullable', 'string', 'max:100'],
            'narration'             => ['required', 'string', 'max:255'],
            'action'                => ['nullable', 'in:draft,post'],
            'allocations'           => ['required', 'array', 'min:1'],
            'allocations.*.type'    => ['required', 'in:storage,storage-handling,reefer,repair'],
            'allocations.*.id'      => ['required', 'integer', 'min:1'],
            'allocations.*.amount'  => ['nullable', 'numeric', 'min:0'],
            'allocations.*.selected' => ['nullable'],
        ]);

        // Keep only ticked rows with a positive amount.
        $selected = collect($validated['allocations'])
            ->filter(fn ($a) => !empty($a['selected']) && (float) ($a['amount'] ?? 0) > 0)
            ->values();

        if ($selected->isEmpty()) {
            return back()->withInput()->with('error', 'Select at least one invoice and enter an amount to receive.');
        }

        $receiptCurrency = strtoupper($validated['currency']);
        $base            = CompanySetting::baseCurrency();
        $rate            = (float) $validated['exchange_rate'];

        try {
            $receipt = DB::transaction(function () use ($validated, $selected, $receiptCurrency, $base, $rate) {
                // Validate each selected invoice up front (ownership, currency, outstanding).
                $rows = [];
                foreach ($selected as $sel) {
                    $invoice = $this->allocationService->resolveInvoice($sel['type'], (int) $sel['id']);

                    $invCustomerId = $this->allocationService->getCustomerId($invoice, $sel['type']);
                    if ($invCustomerId && (int) $invCustomerId !== (int) $validated['customer_id']) {
                        throw new \RuntimeException("Invoice {$invoice->invoice_no} does not belong to the selected customer.");
                    }

                    $invCurrency = strtoupper((string) ($invoice->invoice_currency ?? $invoice->currency ?? '')) ?: $base;
                    if ($invCurrency !== $receiptCurrency) {
                        throw new \RuntimeException(
                            "Invoice {$invoice->invoice_no} is in {$invCurrency} but the receipt is in {$receiptCurrency}. "
                            . "Settle invoices of one currency per receipt."
                        );
                    }

                    $outstanding = $this->allocationService->getOutstanding($invoice, $sel['type']);
                    $amount      = round((float) $sel['amount'], 2);
                    if ($amount > round($outstanding + 0.005, 2)) {
                        throw new \RuntimeException(
                            "Amount for {$invoice->invoice_no} exceeds its outstanding balance of " . number_format($outstanding, 2) . '.'
                        );
                    }

                    $rows[] = ['invoice' => $invoice, 'type' => $sel['type'], 'amount' => $amount];
                }

                $totalAmount = round(collect($rows)->sum('amount'), 2);

                $receipt = Receipt::create([
                    'receipt_no'      => $this->nextReceiptNo(),
                    'receipt_date'    => $validated['receipt_date'],
                    'customer_id'     => $validated['customer_id'],
                    'bank_account_id' => $validated['bank_account_id'] ?? null,
                    'amount'          => $totalAmount,
                    'currency'        => $receiptCurrency,
                    'exchange_rate'   => $rate,
                    'base_amount'     => round($totalAmount * $rate, 4),
                    'payment_method'  => $validated['payment_method'],
                    'cheque_no'       => $validated['cheque_no'] ?? null,
                    'reference_no'    => $validated['reference_no'] ?? null,
                    'narration'       => $validated['narration'],
                    'created_by'      => auth()->id(),
                    'status'          => 'draft',
                ]);

                foreach ($rows as $r) {
                    $receipt->allocations()->create([
                        'invoice_type'     => $r['type'],
                        'invoice_id'       => $r['invoice']->id,
                        'allocated_amount' => $r['amount'],
                        'base_amount'      => round($r['amount'] * $rate, 4),
                    ]);
                    $this->allocationService->syncInvoiceStatus($r['invoice'], $r['type']);
                }

                return $receipt;
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Optionally post immediately if requested and permitted.
        if (($validated['action'] ?? 'draft') === 'post' && auth()->user()->can('finance.receipts.confirm')) {
            try {
                $this->postingService->confirmReceipt($receipt, auth()->id());
                return redirect()->route('finance.receipts.show', $receipt)
                    ->with('success', "Receipt {$receipt->receipt_no} created and posted to GL.");
            } catch (\Throwable $e) {
                return redirect()->route('finance.receipts.show', $receipt)
                    ->with('error', 'Receipt saved as draft, but posting failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('finance.receipts.show', $receipt)
            ->with('success', "Receipt {$receipt->receipt_no} created as draft.");
    }

    public function show(Receipt $receipt)
    {
        $this->authorize('finance.receipts.view');

        $receipt->load(['customer', 'bankAccount.glAccount', 'journal', 'allocations', 'createdBy', 'voidedBy']);

        // Outstanding invoices for this customer — used to populate allocation dropdown
        $pendingInvoices = $receipt->customer_id
            ? $this->allocationService->pendingForCustomer($receipt->customer_id)
            : collect();

        // Amounts: total receipt, already allocated across all allocations, unallocated remainder
        $totalAllocated   = $receipt->allocations->sum('allocated_amount');
        $unallocatedAmount = max(0, (float) $receipt->amount - $totalAllocated);

        return view('finance.receipts.show', compact(
            'receipt', 'pendingInvoices', 'totalAllocated', 'unallocatedAmount'
        ));
    }

    public function pdf(Receipt $receipt, Request $request)
    {
        $this->authorize('finance.receipts.pdf');

        $receipt->loadMissing(['customer', 'bankAccount', 'allocations', 'createdBy']);

        $size  = $request->query('size') === 'half' ? 'half' : 'a4';
        $paper = $size === 'half' ? 'a5' : 'a4';

        $pdf = Pdf::loadView('finance.receipts.pdf', ['receipt' => $receipt, 'size' => $size])
            ->setPaper($paper, 'portrait')
            ->set_option('defaultFont', 'sans-serif')
            ->set_option('isHtml5ParserEnabled', true)
            ->set_option('isRemoteEnabled', false);

        $filename = 'Receipt-' . $receipt->receipt_no . ($size === 'half' ? '-slip' : '') . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    public function email(Request $request, Receipt $receipt)
    {
        $this->authorize('finance.receipts.email');

        $validated = $request->validate([
            'to_email' => ['required', 'email'],
            'cc_email' => ['nullable', 'email'],
            'message'  => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $mail = Mail::to($validated['to_email']);
            if (!empty($validated['cc_email'])) {
                $mail->cc($validated['cc_email']);
            }
            $mail->send(new ReceiptMail($receipt, $validated['message'] ?? null));
        } catch (\Throwable $e) {
            return back()->with('error', $this->friendlyMailError($e));
        }

        return back()->with('success', "Receipt {$receipt->receipt_no} emailed to {$validated['to_email']}.");
    }

    public function confirm(Receipt $receipt)
    {
        $this->authorize('finance.receipts.confirm');

        try {
            $this->postingService->confirmReceipt($receipt, auth()->id());
            return back()->with('success', "Receipt {$receipt->receipt_no} confirmed and posted to GL.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(Request $request, Receipt $receipt)
    {
        $this->authorize('finance.receipts.void');

        $reason = $request->input('reason', '');

        // Capture the invoices this receipt settled BEFORE voiding — a voided
        // receipt's allocations stop counting, so any invoice it had marked
        // paid/partially_paid must be re-synced or it keeps a stale status.
        $affected = $receipt->allocations()->get(['invoice_type', 'invoice_id'])
            ->unique(fn ($a) => $a->invoice_type . '#' . $a->invoice_id);

        try {
            DB::transaction(function () use ($receipt, $reason, $affected) {
                $this->postingService->voidReceipt($receipt, auth()->id(), $reason);
                foreach ($affected as $a) {
                    $invoice = $this->allocationService->resolveInvoice($a->invoice_type, (int) $a->invoice_id);
                    $this->allocationService->syncInvoiceStatus($invoice, $a->invoice_type);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Receipt {$receipt->receipt_no} has been voided.");
    }

    public function storeAllocation(Request $request, Receipt $receipt)
    {
        $this->authorize('finance.receipts.edit');

        if (!$receipt->isDraft()) {
            return back()->with('error', 'Allocations can only be added to draft receipts.');
        }

        $validated = $request->validate([
            'invoice_type'     => ['required', 'in:storage,storage-handling,reefer,repair'],
            'invoice_id'       => ['required', 'integer', 'min:1'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
            'notes'            => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $invoice = $this->allocationService->resolveInvoice($validated['invoice_type'], (int) $validated['invoice_id']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $invoiceCustomerId = $this->allocationService->getCustomerId($invoice, $validated['invoice_type']);
        if ($invoiceCustomerId && $receipt->customer_id && $invoiceCustomerId !== $receipt->customer_id) {
            return back()->with('error', 'This invoice does not belong to the receipt\'s customer.');
        }

        // A blank currency on either side is treated as the base currency, so a
        // foreign-currency receipt can never be silently allocated to a base-currency
        // invoice (the previous skip-when-empty check allowed exactly that).
        $base            = CompanySetting::baseCurrency();
        $invoiceCurrency = strtoupper((string) ($invoice->invoice_currency ?? $invoice->currency ?? '')) ?: $base;
        $receiptCurrency = strtoupper((string) ($receipt->currency ?? '')) ?: $base;
        if ($invoiceCurrency !== $receiptCurrency) {
            return back()->with('error',
                "Currency mismatch: the receipt is in {$receiptCurrency} but the invoice is in {$invoiceCurrency}. "
                . "Cross-currency settlement isn't supported yet."
            );
        }

        try {
            DB::transaction(function () use ($receipt, $invoice, $validated) {
                $locked = Receipt::lockForUpdate()->find($receipt->id);

                $outstanding = $this->allocationService->getOutstanding($invoice, $validated['invoice_type']);
                if ((float) $validated['allocated_amount'] > round($outstanding + 0.005, 2)) {
                    throw new \RuntimeException(
                        'Allocated amount exceeds the invoice outstanding balance of ' . number_format($outstanding, 2) . '.'
                    );
                }

                $totalAllocated = (float) $locked->allocations()->sum('allocated_amount');
                $remaining      = (float) $locked->amount - $totalAllocated;
                if ((float) $validated['allocated_amount'] > round($remaining + 0.005, 2)) {
                    throw new \RuntimeException(
                        "Allocated amount exceeds the receipt's unallocated balance of " . number_format($remaining, 2) . '.'
                    );
                }

                // Base-currency value applied from this receipt (allocated × receipt rate).
                $validated['base_amount'] = round(
                    (float) $validated['allocated_amount'] * (float) ($locked->exchange_rate ?: 1), 4
                );

                $locked->allocations()->create($validated);
                $this->allocationService->syncInvoiceStatus($invoice, $validated['invoice_type']);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Allocation added.');
    }

    public function deleteAllocation(Request $request, Receipt $receipt, ReceiptAllocation $allocation)
    {
        $this->authorize('finance.receipts.edit');

        if (!$receipt->isDraft()) {
            return back()->with('error', 'Allocations can only be removed from draft receipts.');
        }

        if ($allocation->receipt_id !== $receipt->id) {
            abort(404);
        }

        $invoiceType = $allocation->invoice_type;
        $invoiceId   = $allocation->invoice_id;

        $allocation->delete();

        // Re-sync invoice status after removal
        try {
            $invoice = $this->allocationService->resolveInvoice($invoiceType, $invoiceId);
            $this->allocationService->syncInvoiceStatus($invoice, $invoiceType);
        } catch (\Throwable) {
            // Invoice may no longer exist; status sync is best-effort
        }

        return back()->with('success', 'Allocation removed.');
    }

    private function nextReceiptNo(): string
    {
        return app(\App\Services\NumberSequenceService::class)->generate('receipt');
    }
}
