<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ChargeCode;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\SupplierInvoice;
use App\Services\Finance\ApAllocationService;
use App\Services\Finance\PaymentTermsHelper;
use App\Services\Finance\SupplierInvoicePostingService;
use Illuminate\Http\JsonResponse;
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

        $accounts = Account::where('is_posting', true)->where('is_active', true)
            ->whereIn('classification', ['expense', 'asset'])
            ->orderBy('code')->get(['id', 'code', 'name']);

        $chargeCodes = ChargeCode::where('is_active', true)
            ->orderBy('category')->orderBy('sort_order')->orderBy('code')
            ->get(['id', 'code', 'description', 'category']);

        return view('finance.supplier-invoices.create', compact('suppliers', 'accounts', 'chargeCodes'));
    }

    public function store(Request $request)
    {
        $this->authorize('finance.ap.create');

        $validated = $request->validate([
            'customer_id'                  => ['required', 'exists:customers,id'],
            'supplier_invoice_no'          => ['nullable', 'string', 'max:50'],
            'invoice_date'                 => ['required', 'date'],
            'due_date'                     => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency'                     => ['required', 'string', 'max:10'],
            'exchange_rate'                => ['required', 'numeric', 'min:0.000001'],
            'notes'                        => ['nullable', 'string', 'max:1000'],
            'lines'                        => ['required', 'array', 'min:1'],
            'lines.*.description'          => ['required', 'string', 'max:255'],
            'lines.*.expense_account_id'   => ['required', 'exists:accounts,id'],
            'lines.*.amount'               => ['required', 'numeric', 'gt:0'],
            'lines.*.charge_code_id'       => ['nullable', 'exists:charge_codes,id'],
            'lines.*.tax_code_id'          => ['nullable', 'exists:tax_codes,id'],
            'lines.*.tax1_rate'            => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax2_rate'            => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice = DB::transaction(function () use ($validated) {
            $dueDate = $validated['due_date'] ?? null;
            if (!$dueDate) {
                $contact = Customer::find($validated['customer_id']);
                if ($contact && $contact->ap_payment_terms) {
                    $dueDate = PaymentTermsHelper::dueDate(
                        $contact->ap_payment_terms,
                        \Carbon\Carbon::parse($validated['invoice_date'])
                    )->toDateString();
                }
            }

            $subtotal    = 0.0;
            $ssclTotal   = 0.0;
            $vatTotal    = 0.0;
            $lineRecords = [];

            foreach ($validated['lines'] as $line) {
                $net   = (float) $line['amount'];
                $t1    = (float) ($line['tax1_rate'] ?? 0);
                $t2    = (float) ($line['tax2_rate'] ?? 0);
                $sscl  = round($net * $t1 / 100, 2);
                $vat   = round(($net + $sscl) * $t2 / 100, 2);
                $gross = round($net + $sscl + $vat, 2);

                $subtotal  += $net;
                $ssclTotal += $sscl;
                $vatTotal  += $vat;

                $lineRecords[] = [
                    'description'        => $line['description'],
                    'charge_code_id'     => ($line['charge_code_id'] ?? null) ?: null,
                    'tax_code_id'        => ($line['tax_code_id'] ?? null) ?: null,
                    'expense_account_id' => $line['expense_account_id'],
                    'amount'             => $net,
                    'tax1_rate'          => $t1,
                    'tax2_rate'          => $t2,
                    'tax1_amount'        => $sscl,
                    'tax2_amount'        => $vat,
                    'gross_amount'       => $gross,
                ];
            }

            $subtotal  = round($subtotal,  2);
            $ssclTotal = round($ssclTotal, 2);
            $vatTotal  = round($vatTotal,  2);
            $taxAmount = round($ssclTotal + $vatTotal, 2);
            $total     = round($subtotal + $taxAmount, 2);

            $invoice = SupplierInvoice::create([
                'invoice_no'          => $this->nextInvoiceNo(),
                'supplier_invoice_no' => $validated['supplier_invoice_no'] ?? null,
                'customer_id'         => $validated['customer_id'],
                'invoice_date'        => $validated['invoice_date'],
                'due_date'            => $dueDate,
                'currency'            => $validated['currency'],
                'exchange_rate'       => $validated['exchange_rate'],
                'subtotal'            => $subtotal,
                'sscl_amount'         => $ssclTotal,
                'vat_amount'          => $vatTotal,
                'tax_amount'          => $taxAmount,
                'total_amount'        => $total,
                'status'              => 'draft',
                'notes'               => $validated['notes'] ?? null,
                'created_by'          => auth()->id(),
            ]);

            foreach ($lineRecords as $lr) {
                $invoice->lines()->create($lr);
            }

            return $invoice;
        });

        return redirect()->route('finance.ap.invoices.show', $invoice)
            ->with('success', "Supplier invoice {$invoice->invoice_no} created as draft.");
    }

    public function show(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('finance.ap.view');

        $supplierInvoice->load(['supplier', 'lines.expenseAccount', 'lines.chargeCode', 'lines.taxCode', 'journal']);
        $settlements = $this->allocationService->settlementsFor($supplierInvoice->id);
        $outstanding = $this->allocationService->getOutstanding($supplierInvoice);
        $allocated   = $this->allocationService->getAllocatedTotal($supplierInvoice->id);

        return view('finance.supplier-invoices.show', compact(
            'supplierInvoice', 'settlements', 'outstanding', 'allocated'
        ));
    }

    public function destroy(SupplierInvoice $supplierInvoice)
    {
        $this->authorize('finance.ap.delete');

        if (!$supplierInvoice->isDraft()) {
            return back()->with('error', 'Only draft invoices can be deleted.');
        }

        $supplierInvoice->delete();

        return redirect()->route('finance.ap.invoices.index')
            ->with('success', 'Draft supplier invoice deleted.');
    }

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

    /**
     * AJAX: return charge code details for the supplier invoice line auto-fill.
     * Called when the user picks a charge code from the Select2 dropdown.
     * Returns: description, tax_code_id, tax1_rate, tax2_rate, expense_account_id.
     */
    public function chargeCodeDetails(ChargeCode $chargeCode): JsonResponse
    {
        $this->authorize('finance.ap.create');

        $expenseMapping = AccountMapping::where('mapping_type', 'charge_expense')
            ->where('source_type', ChargeCode::class)
            ->where('source_id', $chargeCode->id)
            ->where('is_active', true)
            ->with('account:id,code,name')
            ->first();

        $taxCode = $chargeCode->taxCode;

        return response()->json([
            'description'        => $chargeCode->description,
            'tax_code_id'        => $chargeCode->tax_code_id,
            'tax_code_code'      => $taxCode?->code,
            'tax_code_desc'      => $taxCode?->description,
            'tax1_rate'          => (float) ($taxCode?->tax1_rate ?? 0),
            'tax2_rate'          => (float) ($taxCode?->tax2_rate ?? 0),
            'expense_account_id' => $expenseMapping?->account_id,
            'expense_account'    => $expenseMapping?->account
                ? $expenseMapping->account->code . ' — ' . $expenseMapping->account->name
                : null,
        ]);
    }

    private function nextInvoiceNo(): string
    {
        return app(\App\Services\NumberSequenceService::class)->generate('supplier_invoice');
    }
}
