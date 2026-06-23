<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\SupplierInvoice;
use App\Services\Finance\ApAllocationService;
use App\Services\Finance\SupplierInvoicePostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierInvoiceController extends Controller
{
    public function __construct(
        private SupplierInvoicePostingService $postingService,
        private ApAllocationService $allocationService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('finance.ap.view');

        $invoices = SupplierInvoice::query()
            ->with('supplier')
            ->when($request->customer_id, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($qb) => $qb->where('invoice_no', 'like', "%{$s}%")
                    ->orWhere('supplier_invoice_no', 'like', "%{$s}%"))
            )
            ->latest('invoice_date')
            ->paginate(20)
            ->withQueryString();

        $suppliers = Customer::apContacts()->get(['id', 'code', 'name']);

        return view('finance.supplier-invoices.index', compact('invoices', 'suppliers'));
    }

    public function create()
    {
        $this->authorize('finance.ap.create');

        $suppliers = Customer::apContacts()->get(['id', 'code', 'name', 'currency']);
        $accounts  = Account::where('is_posting', true)->where('is_active', true)
            ->whereIn('classification', ['expense', 'asset'])
            ->orderBy('code')->get(['id', 'code', 'name']);

        return view('finance.supplier-invoices.create', compact('suppliers', 'accounts'));
    }

    public function store(Request $request)
    {
        $this->authorize('finance.ap.create');

        $validated = $request->validate([
            'customer_id'          => ['required', 'exists:customers,id'],
            'supplier_invoice_no'  => ['nullable', 'string', 'max:50'],
            'invoice_date'         => ['required', 'date'],
            'due_date'             => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency'             => ['required', 'string', 'max:10'],
            'exchange_rate'        => ['required', 'numeric', 'min:0.000001'],
            'tax_amount'           => ['nullable', 'numeric', 'min:0'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'lines'                => ['required', 'array', 'min:1'],
            'lines.*.description'  => ['required', 'string', 'max:255'],
            'lines.*.expense_account_id' => ['required', 'exists:accounts,id'],
            'lines.*.amount'       => ['required', 'numeric', 'gt:0'],
        ]);

        $subtotal = collect($validated['lines'])->sum(fn ($l) => (float) $l['amount']);
        $tax      = (float) ($validated['tax_amount'] ?? 0);
        $total    = round($subtotal + $tax, 4);

        $invoice = DB::transaction(function () use ($validated, $subtotal, $tax, $total) {
            $invoice = SupplierInvoice::create([
                'invoice_no'          => $this->nextInvoiceNo(),
                'supplier_invoice_no' => $validated['supplier_invoice_no'] ?? null,
                'customer_id'         => $validated['customer_id'],
                'invoice_date'        => $validated['invoice_date'],
                'due_date'            => $validated['due_date'] ?? null,
                'currency'            => $validated['currency'],
                'exchange_rate'       => $validated['exchange_rate'],
                'subtotal'            => $subtotal,
                'tax_amount'          => $tax,
                'total_amount'        => $total,
                'status'              => 'draft',
                'notes'               => $validated['notes'] ?? null,
                'created_by'          => auth()->id(),
            ]);

            foreach ($validated['lines'] as $line) {
                $invoice->lines()->create([
                    'description'        => $line['description'],
                    'expense_account_id' => $line['expense_account_id'],
                    'amount'             => $line['amount'],
                ]);
            }

            return $invoice;
        });

        return redirect()->route('finance.ap.invoices.show', $invoice)
            ->with('success', "Supplier invoice {$invoice->invoice_no} created as draft.");
    }

    public function show(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('finance.ap.view');

        $supplierInvoice->load(['supplier', 'lines.expenseAccount', 'journal']);
        $settlements = $this->allocationService->settlementsFor($supplierInvoice->id);
        $outstanding = $this->allocationService->getOutstanding($supplierInvoice);
        $allocated   = $this->allocationService->getAllocatedTotal($supplierInvoice->id);

        return view('finance.supplier-invoices.show', compact(
            'supplierInvoice', 'settlements', 'outstanding', 'allocated'
        ));
    }

    public function destroy(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('finance.ap.create');

        if (!$supplierInvoice->isDraft()) {
            return back()->with('error', 'Only draft invoices can be deleted.');
        }

        $supplierInvoice->delete();

        return redirect()->route('finance.ap.invoices.index')
            ->with('success', 'Draft supplier invoice deleted.');
    }

    /**
     * Approve a draft invoice and auto-post it to the GL.
     * A posting failure is recorded (posting_error) but does not block the
     * approval — the user can fix the mapping and retry.
     */
    public function approve(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('finance.ap.post');

        if (!$supplierInvoice->isDraft()) {
            return back()->with('error', 'Only draft invoices can be approved.');
        }

        $supplierInvoice->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        try {
            $this->postingService->post($supplierInvoice, auth()->id());
        } catch (\Throwable $e) {
            $supplierInvoice->update(['posting_error' => $e->getMessage()]);
            Log::error("Auto-post failed for supplier invoice {$supplierInvoice->invoice_no}: {$e->getMessage()}");

            return back()->with('error',
                'Invoice approved but GL posting failed: ' . $e->getMessage() . ' — fix the mapping and use Retry Post.');
        }

        return back()->with('success',
            "Supplier invoice {$supplierInvoice->invoice_no} approved and posted to the GL.");
    }

    /**
     * Retry posting for an approved invoice whose auto-post failed.
     */
    public function retryPost(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('finance.ap.post');

        if (!$supplierInvoice->isApproved() || $supplierInvoice->isPosted()) {
            return back()->with('error', 'Only an approved, not-yet-posted invoice can be retried.');
        }

        try {
            $posting = $this->postingService->post($supplierInvoice, auth()->id());
            return back()->with('success',
                "Supplier invoice posted to GL journal {$posting->journal->journal_no}.");
        } catch (\Throwable $e) {
            $supplierInvoice->update(['posting_error' => $e->getMessage()]);
            return back()->with('error', 'Posting failed: ' . $e->getMessage());
        }
    }

    /**
     * Cancel an invoice and reverse its GL posting (if any).
     */
    public function cancel(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('finance.ap.void');

        if ($supplierInvoice->isCancelled()) {
            return back()->with('error', 'Invoice is already cancelled.');
        }

        if ($supplierInvoice->allocations()
            ->whereHas('voucher', fn ($q) => $q->whereIn('status', ['draft', 'confirmed']))
            ->exists()) {
            return back()->with('error',
                'Cannot cancel — remove payment allocations against this invoice first.');
        }

        try {
            $this->postingService->void($supplierInvoice, auth()->id(), "Invoice {$supplierInvoice->invoice_no} cancelled");
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reverse the GL posting: ' . $e->getMessage());
        }

        $supplierInvoice->update(['status' => 'cancelled']);

        return back()->with('success', "Supplier invoice {$supplierInvoice->invoice_no} cancelled.");
    }

    private function nextInvoiceNo(): string
    {
        return DB::transaction(function () {
            $prefix = CompanySetting::current()->prefix_supplier_invoice ?? 'SINV';
            $last   = SupplierInvoice::where('invoice_no', 'like', "{$prefix}-%")
                ->orderByRaw('CAST(SUBSTRING(invoice_no, ' . (strlen($prefix) + 2) . ') AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->value('invoice_no');

            $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;

            return "{$prefix}-" . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
        });
    }
}
