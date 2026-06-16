<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Receipt;
use App\Services\Finance\ReceiptPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    public function __construct(private ReceiptPostingService $postingService) {}

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

        return view('finance.receipts.show', compact('receipt'));
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

        try {
            $this->postingService->voidReceipt($receipt, auth()->id(), $reason);
            return back()->with('success', "Receipt {$receipt->receipt_no} has been voided.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function storeAllocation(Request $request, Receipt $receipt)
    {
        $this->authorize('finance.receipts.create');

        $validated = $request->validate([
            'invoice_type'     => ['required', 'in:storage,storage-handling,reefer,repair'],
            'invoice_id'       => ['required', 'integer', 'min:1'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
            'notes'            => ['nullable', 'string', 'max:255'],
        ]);

        $receipt->allocations()->create($validated);

        return back()->with('success', 'Allocation added.');
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
