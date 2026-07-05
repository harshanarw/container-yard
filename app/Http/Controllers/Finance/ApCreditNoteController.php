<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Mail\ApCreditNoteMail;
use App\Models\Account;
use App\Models\ApCreditNote;
use App\Models\ChargeCode;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TaxCode;
use App\Services\ConfiguredMailer;
use App\Services\Finance\ApAllocationService;
use App\Services\Finance\ApCreditNotePostingService;
use App\Services\NotificationService;
use App\Services\NumberSequenceService;
use App\Support\HandlesMailErrors;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApCreditNoteController extends Controller
{
    use HandlesMailErrors;

    public function __construct(
        private ApCreditNotePostingService $postingService,
        private ApAllocationService $allocationService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('finance.ap-credit-notes.view');

        $query = ApCreditNote::with(['supplier', 'journal', 'applications'])
            ->orderByDesc('credit_date')->orderByDesc('id');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $creditNotes = $query->paginate(25)->withQueryString();
        $suppliers   = Customer::apContacts()->get(['id', 'name']);

        return view('finance.ap-credit-notes.index', compact('creditNotes', 'suppliers'));
    }

    public function create(Request $request)
    {
        $this->authorize('finance.ap-credit-notes.create');

        $options = $this->formOptions();
        $prefill = null;

        // "Credit note against bill X" — pre-fill from an existing supplier invoice,
        // mirroring each bill line (net + SSCL/VAT tax code) so the reversal matches
        // exactly what the bill booked.
        if ($request->filled('supplier_invoice_id')) {
            try {
                $invoice = $this->allocationService->resolveInvoice((int) $request->supplier_invoice_id);
                $invoice->loadMissing('lines');

                $lines = $invoice->lines->map(fn ($l) => [
                    'description'        => 'Reversal — ' . $l->description,
                    'expense_account_id' => $l->expense_account_id,
                    'charge_code_id'     => $l->charge_code_id,
                    'tax_code_id'        => $l->tax_code_id,
                    'tax1_rate'          => (float) $l->tax1_rate,
                    'tax2_rate'          => (float) $l->tax2_rate,
                    'amount'             => round((float) $l->amount, 2),
                ])->values()->all();

                $prefill = [
                    'supplier_invoice_id' => $invoice->id,
                    'invoice_no'          => $invoice->invoice_no,
                    'customer_id'         => $invoice->customer_id,
                    'currency'            => strtoupper((string) ($invoice->currency ?? $options['baseCurrency'])),
                    'exchange_rate'       => $this->allocationService->getExchangeRate($invoice),
                    'lines'               => $lines,
                ];
            } catch (\Throwable) {
                $prefill = null;
            }
        }

        return view('finance.ap-credit-notes.create', array_merge($options, ['prefill' => $prefill]));
    }

    /**
     * After approval, if raised against a specific bill, apply it automatically
     * (capped at that bill's outstanding). Best-effort.
     */
    private function autoApplyToReference(ApCreditNote $cn): void
    {
        if (!$cn->reference_supplier_invoice_id || $cn->applications()->exists()) {
            return;
        }

        try {
            $invoice = $this->allocationService->resolveInvoice((int) $cn->reference_supplier_invoice_id);

            $base   = CompanySetting::baseCurrency();
            $invCcy = strtoupper((string) $invoice->currency) ?: $base;
            $cnCcy  = strtoupper((string) $cn->currency) ?: $base;
            if ($invCcy !== $cnCcy) {
                return;
            }

            $outstanding = $this->allocationService->getOutstanding($invoice);
            $amount      = round(min((float) $cn->total_amount, $outstanding), 2);
            if ($amount <= 0) {
                return;
            }

            $application = $cn->applications()->create([
                'supplier_invoice_id' => $invoice->id,
                'applied_amount'      => $amount,
                'base_amount'         => round($amount * (float) ($cn->exchange_rate ?: 1), 4),
            ]);
            $this->allocationService->syncInvoiceStatus($invoice);
            // Recognise FX if the bill was booked at a different rate (no-op here
            // since the CN inherits the bill rate, but kept for correctness).
            $this->postingService->postApplicationFx($cn, $application, auth()->id());
        } catch (\Throwable) {
            // leave for manual application
        }
    }

    public function store(Request $request)
    {
        $this->authorize('finance.ap-credit-notes.create');

        $validated = $this->validateData($request);

        $cn = DB::transaction(function () use ($validated) {
            $rate = (float) $validated['exchange_rate'];

            // Compute per-line SSCL/VAT the same way the supplier invoice does:
            // SSCL on net, VAT on (net + SSCL).
            $rows       = [];
            $subtotal   = 0.0;
            $ssclTotal  = 0.0;
            $vatTotal   = 0.0;
            foreach ($validated['lines'] as $l) {
                $net  = round((float) $l['amount'], 2);
                $t1   = (float) ($l['tax1_rate'] ?? 0);
                $t2   = (float) ($l['tax2_rate'] ?? 0);
                $sscl = round($net * $t1 / 100, 2);
                $vat  = round(($net + $sscl) * $t2 / 100, 2);

                $subtotal  += $net;
                $ssclTotal += $sscl;
                $vatTotal  += $vat;

                $rows[] = [
                    'description'        => $l['description'],
                    'expense_account_id' => $l['expense_account_id'] ?? null,
                    'charge_code_id'     => $l['charge_code_id'] ?? null,
                    'tax_code_id'        => $l['tax_code_id'] ?? null,
                    'amount'             => $net,
                    'tax1_rate'          => $t1,
                    'tax2_rate'          => $t2,
                    'tax1_amount'        => $sscl,
                    'tax2_amount'        => $vat,
                    'gross_amount'       => round($net + $sscl + $vat, 2),
                ];
            }

            $subtotal  = round($subtotal, 2);
            $ssclTotal = round($ssclTotal, 2);
            $vatTotal  = round($vatTotal, 2);
            $total     = round($subtotal + $ssclTotal + $vatTotal, 2);

            $cn = ApCreditNote::create([
                'credit_note_no'                => app(NumberSequenceService::class)->generate('ap_credit_note'),
                'supplier_credit_no'            => $validated['supplier_credit_no'] ?? null,
                'customer_id'                   => $validated['customer_id'],
                'credit_date'                   => $validated['credit_date'],
                'currency'                      => strtoupper($validated['currency']),
                'exchange_rate'                 => $rate,
                'reference_supplier_invoice_id' => $validated['reference_supplier_invoice_id'] ?? null,
                'subtotal'                      => $subtotal,
                'sscl_amount'                   => $ssclTotal,
                'tax_amount'                    => $vatTotal, // VAT only — keeps the "Input VAT" meaning
                'total_amount'                  => $total,
                'base_amount'                   => round($total * $rate, 4),
                'reason'                        => $validated['reason'] ?? null,
                'notes'                         => $validated['notes'] ?? null,
                'status'                        => 'draft',
                'created_by'                    => auth()->id(),
            ]);

            foreach ($rows as $r) {
                $cn->lines()->create($r);
            }

            return $cn;
        });

        return redirect()->route('finance.ap-credit-notes.show', $cn)
            ->with('success', "Credit note {$cn->credit_note_no} created as draft.");
    }

    public function show(ApCreditNote $apCreditNote)
    {
        $this->authorize('finance.ap-credit-notes.view');

        $apCreditNote->load(['supplier', 'lines.expenseAccount', 'applications.invoice', 'journal', 'createdBy', 'approvedBy']);

        $pendingInvoices = $apCreditNote->isApproved() && $apCreditNote->unapplied > 0
            ? $this->allocationService->pendingForSupplier((int) $apCreditNote->customer_id)
            : collect();

        return view('finance.ap-credit-notes.show', compact('apCreditNote', 'pendingInvoices'));
    }

    public function approve(ApCreditNote $apCreditNote)
    {
        $this->authorize('finance.ap-credit-notes.approve');

        try {
            $this->postingService->approve($apCreditNote, auth()->id());
            $this->autoApplyToReference($apCreditNote->fresh());
            return back()->with('success', "Credit note {$apCreditNote->credit_note_no} approved and posted to GL.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, ApCreditNote $apCreditNote)
    {
        $this->authorize('finance.ap-credit-notes.approve');

        $affected = $apCreditNote->applications()->get(['supplier_invoice_id'])
            ->pluck('supplier_invoice_id')->unique();

        try {
            DB::transaction(function () use ($apCreditNote, $request, $affected) {
                $this->postingService->cancel($apCreditNote, auth()->id(), $request->input('reason', ''));
                foreach ($affected as $invoiceId) {
                    $invoice = $this->allocationService->resolveInvoice((int) $invoiceId);
                    $this->allocationService->syncInvoiceStatus($invoice);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Credit note {$apCreditNote->credit_note_no} cancelled.");
    }

    public function pdf(ApCreditNote $apCreditNote, Request $request)
    {
        $this->authorize('finance.ap-credit-notes.pdf');

        $apCreditNote->loadMissing(['supplier', 'lines', 'createdBy']);
        $size  = $request->query('size') === 'half' ? 'half' : 'a4';
        $paper = $size === 'half' ? 'a5' : 'a4';

        $pdf = Pdf::loadView('finance.credit-notes.pdf', [
                'cn'            => $apCreditNote,
                'title'         => 'CREDIT NOTE',
                'partyLabel'    => 'Received From',
                'partyName'     => $apCreditNote->supplier->name ?? '—',
                'taxLabel'      => 'Input VAT',
                'size'          => $size,
                'showSignature' => true,
                'verifyUrl'     => \Illuminate\Support\Facades\URL::signedRoute('documents.verify', ['type' => 'ap-credit-note', 'id' => $apCreditNote->id]),
            ])
            ->setPaper($paper, 'portrait')
            ->set_option('isHtml5ParserEnabled', true)
            ->set_option('isRemoteEnabled', false);

        $filename = 'APCreditNote-' . $apCreditNote->credit_note_no . ($size === 'half' ? '-slip' : '') . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    public function email(Request $request, ApCreditNote $apCreditNote)
    {
        $this->authorize('finance.ap-credit-notes.email');

        $validated = $request->validate([
            'to_email' => ['required', 'email'],
            'cc_email' => ['nullable', 'email'],
            'message'  => ['nullable', 'string', 'max:1000'],
            'format'   => ['nullable', 'in:a4,half'],
        ]);

        $error = $this->sendMailWithRetry(function () use ($validated, $apCreditNote) {
            $pending = ConfiguredMailer::forCategory('credit_note')->to($validated['to_email']);
            if (!empty($validated['cc_email'])) {
                $pending->cc($validated['cc_email']);
            }
            $pending->send(new ApCreditNoteMail($apCreditNote, $validated['message'] ?? null, $validated['format'] ?? 'a4'));
        });

        if ($error) {
            $msg = $this->friendlyMailError($error);
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        }

        $msg = "Credit note {$apCreditNote->credit_note_no} emailed to {$validated['to_email']}.";
        NotificationService::notify(auth()->user(), 'Credit note emailed', $msg, 'success', route('finance.ap-credit-notes.show', $apCreditNote));

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $msg])
            : back()->with('success', $msg);
    }

    public function destroy(ApCreditNote $apCreditNote)
    {
        $this->authorize('finance.ap-credit-notes.delete');

        if (!$apCreditNote->isDraft()) {
            return back()->with('error', 'Only draft credit notes can be deleted.');
        }

        $apCreditNote->delete();

        return redirect()->route('finance.ap-credit-notes.index')
            ->with('success', "Credit note {$apCreditNote->credit_note_no} deleted.");
    }

    public function storeApplication(Request $request, ApCreditNote $apCreditNote)
    {
        $this->authorize('finance.ap-credit-notes.edit');

        if (!$apCreditNote->isApproved()) {
            return back()->with('error', 'Only approved credit notes can be applied to bills.');
        }

        $validated = $request->validate([
            'supplier_invoice_id' => ['required', 'integer', 'min:1'],
            'applied_amount'      => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $invoice = $this->allocationService->resolveInvoice((int) $validated['supplier_invoice_id']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ((int) $invoice->customer_id !== (int) $apCreditNote->customer_id) {
            return back()->with('error', 'That bill belongs to a different supplier.');
        }

        $base   = CompanySetting::baseCurrency();
        $invCcy = strtoupper((string) $invoice->currency) ?: $base;
        $cnCcy  = strtoupper((string) $apCreditNote->currency) ?: $base;
        if ($invCcy !== $cnCcy) {
            return back()->with('error', "Currency mismatch: credit note is {$cnCcy} but the bill is {$invCcy}.");
        }

        try {
            DB::transaction(function () use ($apCreditNote, $invoice, $validated) {
                $locked = ApCreditNote::lockForUpdate()->find($apCreditNote->id);
                $amount = round((float) $validated['applied_amount'], 2);

                $outstanding = $this->allocationService->getOutstanding($invoice);
                if ($amount > round($outstanding + 0.005, 2)) {
                    throw new \RuntimeException('Applied amount exceeds the bill outstanding of ' . number_format($outstanding, 2) . '.');
                }

                $remaining = (float) $locked->total_amount - (float) $locked->applications()->sum('applied_amount');
                if ($amount > round($remaining + 0.005, 2)) {
                    throw new \RuntimeException('Applied amount exceeds the credit note unapplied balance of ' . number_format($remaining, 2) . '.');
                }

                $application = $locked->applications()->create([
                    'supplier_invoice_id' => $invoice->id,
                    'applied_amount'      => $amount,
                    'base_amount'         => round($amount * (float) ($locked->exchange_rate ?: 1), 4),
                ]);

                $this->allocationService->syncInvoiceStatus($invoice);
                // Recognise realized FX when the credit note's rate differs from the
                // bill's booked rate (clears the AP-control residue to gain/loss).
                $this->postingService->postApplicationFx($locked, $application, auth()->id());
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Credit note applied to bill.');
    }

    public function deleteApplication(ApCreditNote $apCreditNote, \App\Models\ApCreditNoteApplication $application)
    {
        $this->authorize('finance.ap-credit-notes.edit');

        if ($application->ap_credit_note_id !== $apCreditNote->id) {
            abort(404);
        }

        $invoiceId = $application->supplier_invoice_id;

        try {
            DB::transaction(function () use ($application) {
                // Reverse the application's FX adjustment (if any) before removing it.
                $this->postingService->voidApplicationFx($application, auth()->id(), 'Application removed');
                $application->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        try {
            $invoice = $this->allocationService->resolveInvoice((int) $invoiceId);
            $this->allocationService->syncInvoiceStatus($invoice);
        } catch (\Throwable) {
            // best-effort
        }

        return back()->with('success', 'Application removed.');
    }

    private function formOptions(): array
    {
        $suppliers       = Customer::apContacts()->get(['id', 'name', 'currency']);
        $expenseAccounts = Account::where('classification', 'expense')->where('is_posting', true)
            ->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $chargeCodes     = ChargeCode::where('is_active', true)->orderBy('code')->get(['id', 'code', 'description']);
        $taxCodes        = TaxCode::where('is_active', true)->orderBy('sort_order')->orderBy('code')
            ->get(['id', 'code', 'description', 'tax1_rate', 'tax2_rate']);
        $currencies      = Currency::where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();
        $baseCurrency    = CompanySetting::baseCurrency();

        return compact('suppliers', 'expenseAccounts', 'chargeCodes', 'taxCodes', 'currencies', 'baseCurrency');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id'                   => ['required', 'exists:customers,id'],
            'supplier_credit_no'            => ['nullable', 'string', 'max:50'],
            'credit_date'                   => ['required', 'date'],
            'currency'                      => ['required', 'string', 'max:10', 'exists:currencies,code'],
            'exchange_rate'                 => ['required', 'numeric', 'min:0.000001'],
            'reference_supplier_invoice_id' => ['nullable', 'integer'],
            'reason'                        => ['nullable', 'string', 'max:255'],
            'notes'                         => ['nullable', 'string', 'max:1000'],
            'lines'                         => ['required', 'array', 'min:1'],
            'lines.*.description'           => ['required', 'string', 'max:255'],
            'lines.*.expense_account_id'    => ['nullable', 'exists:accounts,id'],
            'lines.*.charge_code_id'        => ['nullable', 'exists:charge_codes,id'],
            'lines.*.tax_code_id'           => ['nullable', 'exists:tax_codes,id'],
            'lines.*.tax1_rate'             => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax2_rate'             => ['nullable', 'numeric', 'min:0'],
            'lines.*.amount'                => ['required', 'numeric', 'min:0.01'],
        ]);
    }
}
