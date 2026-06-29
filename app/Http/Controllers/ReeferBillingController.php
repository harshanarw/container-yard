<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\ReeferElectricityInvoice;
use App\Models\ReeferPlugSession;
use App\Services\CurrencyService;
use App\Services\IrdInvoiceNumberService;
use App\Services\NotificationService;
use App\Services\ReeferBillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReeferBillingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:billing.reefer.view')->only(['index', 'show', 'exchangeRateLookup', 'preview']);
        $this->middleware('can:billing.reefer.create')->only(['create', 'store']);
        $this->middleware('can:billing.reefer.delete')->only(['destroy', 'cancel']);
        $this->middleware('can:billing.reefer.approve')->only(['markIssued', 'markPaid']);
        $this->middleware('can:billing.reefer.pdf')->only(['pdf', 'irdPrint']);
    }

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
        $customers       = Customer::with('billingParty')
            ->where('status', 'active')->orderBy('name')->get();
        $allCustomers    = Customer::where('status', 'active')->orderBy('name')->get();
        $defaultCurrency = CurrencyService::defaultCurrency();
        $exchangeRate    = CurrencyService::usdToDefault() ?? 1.0;

        return view('billing.reefer.create', compact('customers', 'allCustomers', 'defaultCurrency', 'exchangeRate'));
    }

    // ── AJAX preview ─────────────────────────────────────────────────────────

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'customer_id'      => 'required|exists:customers,id',
            'service_type'     => 'required|in:pti,long_term',
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
            $validated['service_type'] ?? 'long_term',
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
            'billing_party_id' => 'nullable|exists:customers,id',
            'invoice_type'     => 'nullable|in:tax_invoice,invoice,debit_note',
            'service_type'     => 'required|in:pti,long_term',
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
            $validated['service_type'] ?? 'long_term',
            $validated['period_from'],
            $validated['period_to'],
            $validated['invoice_currency'],
            (float) $validated['exchange_rate'],
            (float) ($validated['sscl_pct'] ?? 0),
            (float) ($validated['vat_pct'] ?? 0)
        );

        // Authoritative tariff guard: block if any session has no usable rate.
        if (!empty($preview['missing_rates'])) {
            return back()
                ->withInput()
                ->with('tariff_block', $preview['missing_rates'])
                ->with('error', 'Invoice not saved — ' . count($preview['missing_rates'])
                    . ' reefer session group(s) have no usable tariff rate. Please set up the reefer tariff and preview again.');
        }

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
            $validated['notes'] ?? null,
            ($validated['billing_party_id'] ?? null) ?: null,
            $validated['invoice_type'] ?? 'invoice'
        );

        return redirect()->route('billing.reefer.show', $invoice)
            ->with('success', "Reefer electricity invoice {$invoice->invoice_no} created as Draft.");
    }

    // ── Show invoice ──────────────────────────────────────────────────────────

    public function show(ReeferElectricityInvoice $reeferInvoice)
    {
        $reeferInvoice->load(['customer', 'billingParty', 'lines.plugSession', 'lines.chargeCode.taxCode', 'createdBy']);
        return view('billing.reefer.show', compact('reeferInvoice'));
    }

    // ── Status transitions ────────────────────────────────────────────────────

    public function markIssued(ReeferElectricityInvoice $reeferInvoice, \App\Services\Finance\CreditService $credit)
    {
        if (!$reeferInvoice->isDraft()) {
            return back()->with('error', 'Only draft invoices can be issued.');
        }

        $irdNo = $reeferInvoice->ird_invoice_no
            ?? app(IrdInvoiceNumberService::class)->generate('reefer', $reeferInvoice->invoice_date);

        $reeferInvoice->update(['status' => 'issued', 'sent_at' => now(), 'ird_invoice_no' => $irdNo]);

        NotificationService::notifyAll(
            'Reefer Invoice Issued — ' . $reeferInvoice->invoice_no,
            ($reeferInvoice->customer->name ?? 'Unknown') . ' · ' . $reeferInvoice->invoice_currency . ' ' . number_format($reeferInvoice->total_amount, 2),
            'success',
            route('billing.reefer.show', $reeferInvoice)
        );

        $redirect = back()->with('success', 'Invoice marked as Issued.');
        if ($reeferInvoice->customer && ($warning = $credit->arOverLimitWarning($reeferInvoice->customer))) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function markPaid(ReeferElectricityInvoice $reeferInvoice)
    {
        if ($reeferInvoice->status !== 'issued') {
            return back()->with('error', 'Only issued invoices can be marked as paid.');
        }
        $reeferInvoice->update(['status' => 'paid']);

        NotificationService::notifyAll(
            'Reefer Invoice Paid — ' . $reeferInvoice->invoice_no,
            ($reeferInvoice->customer->name ?? 'Unknown') . ' · ' . $reeferInvoice->invoice_currency . ' ' . number_format($reeferInvoice->total_amount, 2),
            'success',
            route('billing.reefer.show', $reeferInvoice)
        );

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
        $reeferInvoice->load(['customer', 'billingParty', 'lines.plugSession', 'lines.chargeCode', 'createdBy']);

        $companySetting = CompanySetting::current();

        $pdf = Pdf::loadView('billing.reefer.pdf', compact('reeferInvoice', 'companySetting'));
        $pdf->setPaper('a4', 'portrait');

        $filename = str_replace('/', '-', $reeferInvoice->invoice_no) . '.pdf';
        return $pdf->stream($filename);
    }

    // ── IRD Tax Invoice print ─────────────────────────────────────────────────

    public function irdPrint(ReeferElectricityInvoice $reeferInvoice)
    {
        $reeferInvoice->load(['customer', 'lines.plugSession', 'lines.chargeCode', 'createdBy']);
        $company = CompanySetting::current();

        $lines = $reeferInvoice->lines->map(fn ($l) => [
            'reference'       => optional($l->plugSession)->container_no,
            'description'     => 'Reefer Electricity — ' . (optional($l->chargeCode)->name ?? 'Electricity Charge'),
            'quantity'        => $l->hours ?? $l->quantity ?? 1,
            'unit_price'      => $l->rate ?? 0,
            'amount_excl_vat' => $l->subtotal ?? $l->line_amount ?? 0,
        ]);

        $from = $reeferInvoice->billing_period_from?->format('d M Y');
        $to   = $reeferInvoice->billing_period_to?->format('d M Y');

        $ssclRates = $reeferInvoice->lines->map(fn ($l) => ($l->tax1_rate ?? 0) > 0 ? round((float) $l->tax1_rate, 4) : null)
            ->filter()->unique()->sort()->values();
        $vatRates  = $reeferInvoice->lines->map(fn ($l) => ($l->tax2_rate ?? 0) > 0 ? round((float) $l->tax2_rate, 4) : null)
            ->filter()->unique()->sort()->values();

        $ssclLabel = $ssclRates->count() > 1
            ? $ssclRates->map(fn ($r) => number_format($r, 2) . '%')->implode(' / ')
            : null;
        $vatLabel  = $vatRates->count() > 1
            ? $vatRates->map(fn ($r) => number_format($r, 2) . '%')->implode(' / ')
            : null;

        $data = [
            'ird_invoice_no'        => $reeferInvoice->ird_invoice_no ?? '—',
            'invoice_date'          => $reeferInvoice->invoice_date,
            'company'               => $company,
            'verifyUrl'             => \Illuminate\Support\Facades\URL::signedRoute('documents.verify', ['type' => 'reefer', 'id' => $reeferInvoice->id]),
            'customer'              => $reeferInvoice->customer,
            'lines'                 => $lines,
            'subtotal'              => $reeferInvoice->subtotal,
            'sscl_amount'           => $reeferInvoice->sscl_amount ?? 0,
            'sscl_percentage'       => (float) ($ssclRates->first() ?? $reeferInvoice->sscl_percentage ?? 0),
            'sscl_percentage_label' => $ssclLabel,
            'vat_amount'            => $reeferInvoice->vat_amount ?? 0,
            'vat_percentage'        => (float) ($vatRates->first() ?? $reeferInvoice->vat_percentage ?? 0),
            'vat_percentage_label'  => $vatLabel,
            'total_incl_vat'        => $reeferInvoice->total_amount,
            'invoice_currency'      => $reeferInvoice->invoice_currency,
            'exchange_rate'         => $reeferInvoice->exchange_rate,
            'invoice_no'            => $reeferInvoice->invoice_no,
            'category_info'         => array_filter([
                'Category'          => 'Reefer Electricity',
                'Payment Due'       => $reeferInvoice->due_date?->format('d M Y'),
                'Billing Period'    => $from && $to ? "{$from} to {$to}" : null,
                'No. of Sessions'   => $reeferInvoice->lines->count() . ' session(s)',
            ]),
        ];

        $filename = 'TAX_INVOICE_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $data['ird_invoice_no']) . '.pdf';

        return Pdf::loadView('billing.ird-tax-invoice-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->set_option('defaultFont', 'Courier')
            ->set_option('isHtml5ParserEnabled', true)
            ->set_option('isRemoteEnabled', false)
            ->stream($filename);
    }

    // ── AJAX exchange rate ────────────────────────────────────────────────────

    public function exchangeRateLookup(Request $request)
    {
        $currency = strtoupper($request->get('currency', 'USD'));
        $date     = $request->get('date', today()->toDateString());
        $default  = CurrencyService::defaultCurrency();

        if ($currency === $default) {
            return response()->json(['rate' => 1.0, 'found' => true, 'currency' => $currency, 'default' => $default]);
        }

        $rate = \App\Models\ExchangeRate::getRate($currency, $default, $date);

        return response()->json([
            'rate'     => $rate,
            'found'    => $rate !== null,
            'currency' => $currency,
            'default'  => $default,
        ]);
    }
}
