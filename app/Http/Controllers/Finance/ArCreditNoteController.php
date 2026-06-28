<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ArCreditNote;
use App\Models\ChargeCode;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Customer;
use App\Services\Finance\ArAllocationService;
use App\Services\Finance\ArCreditNotePostingService;
use App\Services\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArCreditNoteController extends Controller
{
    public function __construct(
        private ArCreditNotePostingService $postingService,
        private ArAllocationService $allocationService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('finance.ar-credit-notes.view');

        $query = ArCreditNote::with(['customer', 'journal'])
            ->orderByDesc('credit_date')->orderByDesc('id');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $creditNotes = $query->paginate(25)->withQueryString();
        $customers   = Customer::orderBy('name')->get(['id', 'name']);

        return view('finance.ar-credit-notes.index', compact('creditNotes', 'customers'));
    }

    public function create()
    {
        $this->authorize('finance.ar-credit-notes.create');

        return view('finance.ar-credit-notes.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $this->authorize('finance.ar-credit-notes.create');

        $validated = $this->validateData($request);

        $cn = DB::transaction(function () use ($validated, $request) {
            $subtotal = collect($validated['lines'])->sum(fn ($l) => round((float) $l['amount'], 2));
            $tax      = round((float) ($validated['tax_amount'] ?? 0), 2);
            $total    = round($subtotal + $tax, 2);
            $rate     = (float) $validated['exchange_rate'];

            $cn = ArCreditNote::create([
                'credit_note_no'         => app(NumberSequenceService::class)->generate('ar_credit_note'),
                'customer_id'            => $validated['customer_id'],
                'credit_date'            => $validated['credit_date'],
                'currency'               => strtoupper($validated['currency']),
                'exchange_rate'          => $rate,
                'reference_invoice_type' => $validated['reference_invoice_type'] ?? null,
                'reference_invoice_id'   => $validated['reference_invoice_id'] ?? null,
                'subtotal'               => $subtotal,
                'tax_amount'             => $tax,
                'total_amount'           => $total,
                'base_amount'            => round($total * $rate, 4),
                'reason'                 => $validated['reason'] ?? null,
                'notes'                  => $validated['notes'] ?? null,
                'status'                 => 'draft',
                'created_by'             => auth()->id(),
            ]);

            foreach ($validated['lines'] as $l) {
                $cn->lines()->create([
                    'description'        => $l['description'],
                    'revenue_account_id' => $l['revenue_account_id'] ?? null,
                    'charge_code_id'     => $l['charge_code_id'] ?? null,
                    'amount'             => round((float) $l['amount'], 2),
                ]);
            }

            return $cn;
        });

        return redirect()->route('finance.ar-credit-notes.show', $cn)
            ->with('success', "Credit note {$cn->credit_note_no} created as draft.");
    }

    public function show(ArCreditNote $arCreditNote)
    {
        $this->authorize('finance.ar-credit-notes.view');

        $arCreditNote->load(['customer', 'lines.revenueAccount', 'applications', 'journal', 'createdBy', 'approvedBy']);

        // Open invoices this credit note can be applied against (same customer).
        $pendingInvoices = $arCreditNote->isApproved() && $arCreditNote->unapplied > 0
            ? $this->allocationService->pendingForCustomer((int) $arCreditNote->customer_id)
            : collect();

        return view('finance.ar-credit-notes.show', compact('arCreditNote', 'pendingInvoices'));
    }

    public function approve(ArCreditNote $arCreditNote)
    {
        $this->authorize('finance.ar-credit-notes.approve');

        try {
            $this->postingService->approve($arCreditNote, auth()->id());
            return back()->with('success', "Credit note {$arCreditNote->credit_note_no} approved and posted to GL.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, ArCreditNote $arCreditNote)
    {
        $this->authorize('finance.ar-credit-notes.approve');

        // Capture invoices this CN had settled so their status can be re-synced.
        $affected = $arCreditNote->applications()->get(['invoice_type', 'invoice_id'])
            ->unique(fn ($a) => $a->invoice_type . '#' . $a->invoice_id);

        try {
            DB::transaction(function () use ($arCreditNote, $request, $affected) {
                $this->postingService->cancel($arCreditNote, auth()->id(), $request->input('reason', ''));
                foreach ($affected as $a) {
                    $invoice = $this->allocationService->resolveInvoice($a->invoice_type, (int) $a->invoice_id);
                    $this->allocationService->syncInvoiceStatus($invoice, $a->invoice_type);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Credit note {$arCreditNote->credit_note_no} cancelled.");
    }

    public function storeApplication(Request $request, ArCreditNote $arCreditNote)
    {
        $this->authorize('finance.ar-credit-notes.edit');

        if (!$arCreditNote->isApproved()) {
            return back()->with('error', 'Only approved credit notes can be applied to invoices.');
        }

        $validated = $request->validate([
            'invoice_type'  => ['required', 'in:storage,storage-handling,reefer,repair'],
            'invoice_id'    => ['required', 'integer', 'min:1'],
            'applied_amount'=> ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $invoice = $this->allocationService->resolveInvoice($validated['invoice_type'], (int) $validated['invoice_id']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $invCustomerId = $this->allocationService->getCustomerId($invoice, $validated['invoice_type']);
        if ($invCustomerId && (int) $invCustomerId !== (int) $arCreditNote->customer_id) {
            return back()->with('error', "This invoice does not belong to the credit note's customer.");
        }

        $base    = CompanySetting::baseCurrency();
        $invCcy  = strtoupper((string) ($invoice->invoice_currency ?? $invoice->currency ?? '')) ?: $base;
        $cnCcy   = strtoupper((string) $arCreditNote->currency) ?: $base;
        if ($invCcy !== $cnCcy) {
            return back()->with('error', "Currency mismatch: credit note is {$cnCcy} but the invoice is {$invCcy}.");
        }

        try {
            DB::transaction(function () use ($arCreditNote, $invoice, $validated) {
                $locked = ArCreditNote::lockForUpdate()->find($arCreditNote->id);
                $amount = round((float) $validated['applied_amount'], 2);

                $outstanding = $this->allocationService->getOutstanding($invoice, $validated['invoice_type']);
                if ($amount > round($outstanding + 0.005, 2)) {
                    throw new \RuntimeException('Applied amount exceeds the invoice outstanding of ' . number_format($outstanding, 2) . '.');
                }

                $remaining = (float) $locked->total_amount - (float) $locked->applications()->sum('applied_amount');
                if ($amount > round($remaining + 0.005, 2)) {
                    throw new \RuntimeException('Applied amount exceeds the credit note unapplied balance of ' . number_format($remaining, 2) . '.');
                }

                $locked->applications()->create([
                    'invoice_type'   => $validated['invoice_type'],
                    'invoice_id'     => $invoice->id,
                    'applied_amount' => $amount,
                    'base_amount'    => round($amount * (float) ($locked->exchange_rate ?: 1), 4),
                ]);

                $this->allocationService->syncInvoiceStatus($invoice, $validated['invoice_type']);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Credit note applied to invoice.');
    }

    public function deleteApplication(ArCreditNote $arCreditNote, \App\Models\ArCreditNoteApplication $application)
    {
        $this->authorize('finance.ar-credit-notes.edit');

        if ($application->ar_credit_note_id !== $arCreditNote->id) {
            abort(404);
        }

        $type = $application->invoice_type;
        $id   = $application->invoice_id;
        $application->delete();

        try {
            $invoice = $this->allocationService->resolveInvoice($type, (int) $id);
            $this->allocationService->syncInvoiceStatus($invoice, $type);
        } catch (\Throwable) {
            // best-effort
        }

        return back()->with('success', 'Application removed.');
    }

    private function formOptions(): array
    {
        $customers       = Customer::where('status', 'active')->orderBy('name')->get(['id', 'name', 'currency']);
        $revenueAccounts = Account::where('classification', 'income')->where('is_posting', true)
            ->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $chargeCodes     = ChargeCode::where('is_active', true)->orderBy('code')->get(['id', 'code', 'description']);
        $currencies      = Currency::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        $baseCurrency    = CompanySetting::baseCurrency();

        return compact('customers', 'revenueAccounts', 'chargeCodes', 'currencies', 'baseCurrency');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id'             => ['required', 'exists:customers,id'],
            'credit_date'             => ['required', 'date'],
            'currency'                => ['required', 'string', 'max:10', 'exists:currencies,code'],
            'exchange_rate'           => ['required', 'numeric', 'min:0.000001'],
            'reference_invoice_type'  => ['nullable', 'in:storage,storage-handling,reefer,repair'],
            'reference_invoice_id'    => ['nullable', 'integer'],
            'tax_amount'              => ['nullable', 'numeric', 'min:0'],
            'reason'                  => ['nullable', 'string', 'max:255'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
            'lines'                   => ['required', 'array', 'min:1'],
            'lines.*.description'     => ['required', 'string', 'max:255'],
            'lines.*.revenue_account_id' => ['nullable', 'exists:accounts,id'],
            'lines.*.charge_code_id'  => ['nullable', 'exists:charge_codes,id'],
            'lines.*.amount'          => ['required', 'numeric', 'min:0.01'],
        ]);
    }
}
