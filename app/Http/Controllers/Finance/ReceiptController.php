<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Services\Finance\ArAllocationService;
use App\Services\Finance\ReceiptPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
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

        return view('finance.receipts.create', compact('customers', 'bankAccounts'));
    }

    public function store(Request $request)
    {
        $this->authorize('finance.receipts.create');

        $validated = $request->validate([
            'receipt_date'    => ['required', 'date'],
            'customer_id'     => ['required', 'exists:customers,id'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'amount'          => ['required', 'numeric', 'min:0.0001'],
            'currency'        => ['required', 'string', 'max:10'],
            'exchange_rate'   => ['required', 'numeric', 'min:0.000001'],
            'payment_method'  => ['required', 'in:cash,cheque,bank_transfer,online'],
            'cheque_no'       => ['nullable', 'string', 'max:50'],
            'reference_no'    => ['nullable', 'string', 'max:100'],
            'narration'       => ['required', 'string', 'max:255'],
        ]);

        $validated['receipt_no'] = $this->nextReceiptNo();
        $validated['created_by'] = auth()->id();
        $validated['status']     = 'draft';

        $receipt = Receipt::create($validated);

        return redirect()->route('finance.receipts.show', $receipt)
            ->with('success', "Receipt {$receipt->receipt_no} created successfully.");
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

        $invoiceCurrency = strtoupper((string) ($invoice->invoice_currency ?? $invoice->currency ?? ''));
        $receiptCurrency = strtoupper((string) ($receipt->currency ?? ''));
        if ($invoiceCurrency && $receiptCurrency && $invoiceCurrency !== $receiptCurrency) {
            return back()->with('error',
                "Currency mismatch: the receipt is in {$receipt->currency} but the invoice is in {$invoiceCurrency}."
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
        return DB::transaction(function () {
            $prefix = \App\Models\CompanySetting::current()->prefix_receipt ?? 'RCP';
            $last   = Receipt::where('receipt_no', 'like', "{$prefix}-%")
                ->orderByDesc('receipt_no')
                ->lockForUpdate()
                ->value('receipt_no');
            $seq = 1;
            if ($last) {
                $parts = explode('-', $last);
                $seq   = ((int) end($parts)) + 1;
            }
            return $prefix . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
        });
    }
}
