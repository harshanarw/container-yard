<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\PaymentAllocation;
use App\Models\PaymentVoucher;
use App\Services\Finance\ApAllocationService;
use App\Services\Finance\ReceiptPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentVoucherController extends Controller
{
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

        return view('finance.vouchers.create', compact('bankAccounts', 'expenseAccounts', 'suppliers'));
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
            'currency'          => ['required', 'string', 'max:10'],
            'exchange_rate'     => ['required', 'numeric', 'min:0.000001'],
            'payment_method'    => ['required', 'in:cash,cheque,bank_transfer,online'],
            'cheque_no'         => ['nullable', 'string', 'max:50'],
            'reference_no'      => ['nullable', 'string', 'max:100'],
            'narration'         => ['required', 'string', 'max:255'],
            'expense_account_id' => ['nullable', 'exists:accounts,id'],
        ]);

        $validated['voucher_no']  = $this->nextVoucherNo();
        $validated['created_by']  = auth()->id();
        $validated['status']      = 'draft';

        $voucher = PaymentVoucher::create($validated);

        return redirect()->route('finance.vouchers.show', $voucher)
            ->with('success', "Voucher {$voucher->voucher_no} created successfully.");
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
        $this->authorize('finance.vouchers.create');

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

        if (strtoupper((string) $invoice->currency) !== strtoupper((string) $voucher->currency)) {
            return back()->with('error',
                "Currency mismatch: the voucher is in {$voucher->currency} but the invoice is in {$invoice->currency}.");
        }

        $outstanding = $this->allocationService->getOutstanding($invoice);
        if ((float) $validated['allocated_amount'] > round($outstanding + 0.005, 2)) {
            return back()->with('error',
                'Allocated amount exceeds the invoice outstanding balance of ' . number_format($outstanding, 2) . '.');
        }

        $totalAllocated = (float) $voucher->allocations()->sum('allocated_amount');
        $remaining      = (float) $voucher->amount - $totalAllocated;
        if ((float) $validated['allocated_amount'] > round($remaining + 0.005, 2)) {
            return back()->with('error',
                "Allocated amount exceeds the voucher's unallocated balance of " . number_format($remaining, 2) . '.');
        }

        $voucher->allocations()->create($validated);
        $this->allocationService->syncInvoiceStatus($invoice);

        return back()->with('success', 'Allocation added.');
    }

    public function deleteAllocation(PaymentVoucher $voucher, PaymentAllocation $allocation)
    {
        $this->authorize('finance.vouchers.create');

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
            $this->postingService->voidVoucher($voucher, auth()->id(), $reason);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        foreach ($invoiceIds as $invId) {
            try {
                $this->allocationService->syncInvoiceStatus(
                    $this->allocationService->resolveInvoice((int) $invId)
                );
            } catch (\Throwable) {
                // best-effort: invoice may have been removed
            }
        }

        return back()->with('success', "Voucher {$voucher->voucher_no} has been voided.");
    }

    private function nextVoucherNo(): string
    {
        return DB::transaction(function () {
            $prefix = \App\Models\CompanySetting::current()->prefix_voucher ?? 'PV';
            // Order by the numeric suffix (not the string) so PV-1000 sorts after
            // PV-999 and the next sequence is always correct.
            $last   = PaymentVoucher::where('voucher_no', 'like', "{$prefix}-%")
                ->orderByRaw('CAST(SUBSTRING(voucher_no, ' . (strlen($prefix) + 2) . ') AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->value('voucher_no');
            $seq = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;

            return $prefix . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        });
    }
}
