<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\ReeferElectricityInvoice;
use App\Models\ReeferPlugSession;
use App\Services\CurrencyService;
use App\Services\ReeferBillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReeferBillingController extends Controller
{
    // ── Invoice list ──────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $invoices = ReeferElectricityInvoice::with('customer')
            ->withCount('lines')
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->status,      fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $s) =>
                $q->where('invoice_no', 'like', "%{$s}%")
                  ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$s}%"))
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        $stats = [
            'total'    => ReeferElectricityInvoice::count(),
            'draft'    => ReeferElectricityInvoice::where('status', 'draft')->count(),
            'issued'   => ReeferElectricityInvoice::where('status', 'issued')->count(),
            'paid'     => ReeferElectricityInvoice::where('status', 'paid')->count(),
        ];

        return view('billing.reefer.index', compact('invoices', 'customers', 'stats'));
    }

    // ── Create form ───────────────────────────────────────────────────────────

    public function create()
    {
        $customers       = Customer::where('status', 'active')->orderBy('name')->get();
        $defaultCurrency = CurrencyService::defaultCurrency();
        $exchangeRate    = CurrencyService::usdToDefault() ?? 1.0;

        return view('billing.reefer.create', compact('customers', 'defaultCurrency', 'exchangeRate'));
    }

    // ── AJAX preview ─────────────────────────────────────────────────────────

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'customer_id'      => 'required|exists:customers,id',
            'period_from'      => 'required|date',
            'period_to'        => 'required|date|after_or_equal:period_from',
            'invoice_currency' => 'nullable|string|size:3',
            'exchange_rate'    => 'nullable|numeric|min:0.0001',
            'sscl_pct'         => 'nullable|numeric|min:0|max:100',
            'vat_pct'          => 'nullable|numeric|min:0|max:100',
        ]);

        $invoiceCurrency = strtoupper($validated['invoice_currency'] ?? CurrencyService::defaultCurrency());
        $exchangeRate    = (float) ($validated['exchange_rate'] ?? 1.0);
        $ssclPct         = (float) ($validated['sscl_pct'] ?? 0);
        $vatPct          = (float) ($validated['vat_pct'] ?? 0);

        $preview = ReeferBillingService::preview(
            (int) $validated['customer_id'],
            $validated['period_from'],
            $validated['period_to'],
            $invoiceCurrency,
            $exchangeRate,
            $ssclPct,
            $vatPct
        );

        return response()->json($preview);
    }

    // ── Store invoice ─────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'      => 'required|exists:customers,id',
            'invoice_date'     => 'required|date',
            'period_from'      => 'required|date',
            'period_to'        => 'required|date|after_or_equal:period_from',
            'invoice_currency' => 'required|string|size:3',
            'exchange_rate'    => 'required|numeric|min:0.0001',
            'sscl_pct'         => 'nullable|numeric|min:0|max:100',
            'vat_pct'          => 'nullable|numeric|min:0|max:100',
            'notes'            => 'nullable|string',
        ]);

        $preview = ReeferBillingService::preview(
            (int) $validated['customer_id'],
            $validated['period_from'],
            $validated['period_to'],
            $validated['invoice_currency'],
            (float) $validated['exchange_rate'],
            (float) ($validated['sscl_pct'] ?? 0),
            (float) ($validated['vat_pct'] ?? 0)
        );

        if (empty($preview['lines'])) {
            return back()
                ->withInput()
                ->with('error', 'No completed reefer sessions found for this customer and period.');
        }

        $invoice = ReeferBillingService::createInvoice(
            $preview,
            $validated['invoice_date'],
            $validated['period_from'],
            $validated['period_to'],
            $validated['notes'] ?? null
        );

        return redirect()->route('billing.reefer.show', $invoice)
            ->with('success', "Reefer electricity invoice {$invoice->invoice_no} created as Draft.");
    }

    // ── Show invoice ──────────────────────────────────────────────────────────

    public function show(ReeferElectricityInvoice $reeferInvoice)
    {
        $reeferInvoice->load(['customer', 'lines.plugSession', 'lines.chargeCode', 'createdBy']);
        return view('billing.reefer.show', compact('reeferInvoice'));
    }

    // ── Status transitions ────────────────────────────────────────────────────

    public function markIssued(ReeferElectricityInvoice $reeferInvoice)
    {
        if (!$reeferInvoice->isDraft()) {
            return back()->with('error', 'Only draft invoices can be issued.');
        }
        $reeferInvoice->update(['status' => 'issued', 'sent_at' => now()]);
        return back()->with('success', 'Invoice marked as Issued.');
    }

    public function markPaid(ReeferElectricityInvoice $reeferInvoice)
    {
        if ($reeferInvoice->status !== 'issued') {
            return back()->with('error', 'Only issued invoices can be marked as paid.');
        }
        $reeferInvoice->update(['status' => 'paid']);
        return back()->with('success', 'Invoice marked as Paid.');
    }

    public function cancel(ReeferElectricityInvoice $reeferInvoice)
    {
        if ($reeferInvoice->status === 'paid') {
            return back()->with('error', 'Paid invoices cannot be cancelled.');
        }

        // Re-open plug sessions so they can be re-billed
        if ($reeferInvoice->isDraft() || $reeferInvoice->status === 'issued') {
            $sessionIds = $reeferInvoice->lines()->pluck('plug_session_id')->filter();
            ReeferPlugSession::whereIn('id', $sessionIds)
                ->where('status', 'billed')
                ->update(['status' => 'completed']);
        }

        $reeferInvoice->update(['status' => 'cancelled']);
        return back()->with('success', 'Invoice cancelled. Linked sessions are available for re-billing.');
    }

    public function destroy(ReeferElectricityInvoice $reeferInvoice)
    {
        if (!$reeferInvoice->isDraft()) {
            return back()->with('error', 'Only draft invoices can be deleted.');
        }

        // Re-open sessions
        $sessionIds = $reeferInvoice->lines()->pluck('plug_session_id')->filter();
        ReeferPlugSession::whereIn('id', $sessionIds)
            ->where('status', 'billed')
            ->update(['status' => 'completed']);

        $reeferInvoice->delete();
        return redirect()->route('billing.reefer.index')
            ->with('success', 'Draft invoice deleted.');
    }

    // ── PDF ───────────────────────────────────────────────────────────────────

    public function pdf(ReeferElectricityInvoice $reeferInvoice)
    {
        $reeferInvoice->load(['customer', 'lines.plugSession', 'lines.chargeCode', 'createdBy']);

        $companySetting = CompanySetting::current();

        $pdf = Pdf::loadView('billing.reefer.pdf', compact('reeferInvoice', 'companySetting'));
        $pdf->setPaper('a4', 'portrait');

        $filename = str_replace('/', '-', $reeferInvoice->invoice_no) . '.pdf';
        return $pdf->stream($filename);
    }

    // ── AJAX exchange rate ────────────────────────────────────────────────────

    public function exchangeRateLookup()
    {
        $rate = CurrencyService::usdToDefault();
        return response()->json(['rate' => $rate]);
    }
}
