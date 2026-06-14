<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\StorageInvoice;
use App\Models\StorageInvoiceDetail;
use App\Models\StorageMasterHeader;
use App\Services\CurrencyService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\YardStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorageBillingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:billing.storage.view')->only(['index', 'show', 'exchangeRateLookup']);
        $this->middleware('can:billing.storage.create')->only(['create', 'preview', 'store']);
        $this->middleware('can:billing.storage.delete')->only(['destroy', 'cancel']);
        $this->middleware('can:billing.storage.approve')->only(['markIssued', 'markPaid']);
        $this->middleware('can:billing.storage.pdf')->only(['pdf']);
        $this->middleware('can:billing.storage.email')->only(['sendEmail']);
    }

    // ── Invoice list ──────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $invoices = StorageInvoice::with('customer')
            ->withCount('details')
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
            'total'    => StorageInvoice::count(),
            'draft'    => StorageInvoice::where('status', 'draft')->count(),
            'issued'   => StorageInvoice::where('status', 'issued')->count(),
            'paid'     => StorageInvoice::where('status', 'paid')->count(),
        ];

        return view('billing.index', compact('invoices', 'customers', 'stats'));
    }

    // ── Generate new invoice ──────────────────────────────────────────────────

    public function create()
    {
        $customers = Customer::with('billingParty')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        return view('billing.create', compact('customers'));
    }

    // ── AJAX: preview charges for a customer + period ────────────────────────

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'customer_id'      => ['required', 'exists:customers,id'],
            'period_from'      => ['required', 'date'],
            'period_to'        => ['required', 'date', 'after_or_equal:period_from'],
            'invoice_currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate'    => ['nullable', 'numeric', 'min:0.0001'],
            'sscl_pct'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vat_pct'          => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $customer        = Customer::findOrFail($validated['customer_id']);
        $taxExempt       = (bool) $customer->tax_exempt;
        $periodFrom      = now()->parse($validated['period_from'])->startOfDay();
        $periodTo        = now()->parse($validated['period_to'])->startOfDay();
        $invoiceCurrency = strtoupper($validated['invoice_currency'] ?? 'LKR');
        $exchangeRate    = (float) ($validated['exchange_rate'] ?? 1.0);
        $defaultCurrency = CurrencyService::defaultCurrency();
        $ssclPct         = (float) ($validated['sscl_pct'] ?? 0);  // fallback rate
        $vatPct          = (float) ($validated['vat_pct'] ?? 0);    // fallback rate

        // All active yard storage records for this customer whose gate-in is on or before period end
        $storageRecords = YardStorage::with(['container.equipmentType'])
            ->where('customer_id', $customer->id)
            ->whereNull('gate_out_date')
            ->where('gate_in_date', '<=', $periodTo)
            ->orderBy('gate_in_date')
            ->get();

        // Load cargo_status from gate-in movement for each container
        $containerIds = $storageRecords->pluck('container_id')->filter()->unique()->values();
        $gateInCargoStatus = \App\Models\GateMovement::whereIn('container_id', $containerIds)
            ->where('movement_type', 'in')
            ->orderByDesc('gate_in_time')
            ->get()
            ->keyBy('container_id')
            ->map(fn ($m) => $m->cargo_status);

        if ($storageRecords->isEmpty()) {
            return response()->json([
                'lines'            => [],
                'invoice_currency' => $invoiceCurrency,
                'exchange_rate'    => $exchangeRate,
                'subtotal'         => 0,
                'sscl_percentage'  => $ssclPct,
                'sscl_amount'      => 0,
                'vat_percentage'   => $vatPct,
                'vat_amount'       => 0,
                'total_amount'     => 0,
                'tariff_found'     => false,
                'no_containers'    => true,
            ]);
        }

        // Find active tariff valid during the billing period
        $tariffHeader = StorageMasterHeader::with('details.equipmentType', 'details.chargeCode.taxCode')
            ->where('customer_id', $customer->id)
            ->where('is_active', true)
            ->where('valid_from', '<=', $periodTo)
            ->where(function ($q) use ($periodFrom) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $periodFrom);
            })
            ->latest('valid_from')
            ->first();

        $lines = [];

        foreach ($storageRecords as $storage) {
            $container = $storage->container;
            if (! $container) continue;

            $gateIn = $storage->gate_in_date;

            // Effective period start for this container: later of gate-in and billing period start
            $fromDate = $gateIn->gt($periodFrom) ? $gateIn->copy() : $periodFrom->copy();
            $toDate   = $periodTo->copy();

            // Days in this billing period for this container
            $totalDays = max(1, (int) $fromDate->diffInDays($toDate) + 1);

            // Days already elapsed in yard before the billing period started
            // (used to determine how many free days have already been consumed)
            $daysBeforePeriod = max(0, (int) $gateIn->diffInDays($fromDate));

            // Resolve rate from tariff, fall back to stored rate at gate-in
            $eqtId         = $container->equipment_type_id;
            $cargoStatus   = $gateInCargoStatus[$container->id] ?? 'empty';
            $freeDays      = $tariffHeader?->default_free_days ?? $storage->free_days ?? 0;
            $dailyRate     = 0.0;
            $currency      = 'LKR';
            $chargeCodeId  = null;
            $tax1Rate      = $taxExempt ? 0.0 : $ssclPct;  // fallback
            $tax2Rate      = $taxExempt ? 0.0 : $vatPct;   // fallback

            if ($tariffHeader) {
                $detail = $tariffHeader->details
                    ->where('equipment_type_id', $eqtId)
                    ->where('cargo_status', $cargoStatus)
                    ->first();
                if ($detail) {
                    $dailyRate    = (float) $detail->storage_rate;
                    $currency     = $detail->currency;
                    $chargeCodeId = $detail->charge_code_id;

                    if (! $taxExempt && $detail->chargeCode?->taxCode) {
                        $tax1Rate = (float) $detail->chargeCode->taxCode->tax1_rate;
                        $tax2Rate = (float) $detail->chargeCode->taxCode->tax2_rate;
                    }
                }
            } else {
                $dailyRate = (float) $storage->daily_rate;
                $freeDays  = (int) ($storage->free_days ?? 0);
            }

            // Free days still available at the start of this billing period
            $freeDaysRemaining = max(0, $freeDays - $daysBeforePeriod);
            $freeDaysInPeriod  = min($totalDays, $freeDaysRemaining);
            $chargeableDays    = max(0, $totalDays - $freeDaysInPeriod);

            // Convert tariff rate to default currency: only multiply by exchangeRate when tariff is USD
            $tariffMult         = CurrencyService::tariffMultiplier($currency, $exchangeRate);
            $dailyRateConverted = round($dailyRate * $tariffMult, 2);
            $lineSubtotal       = round($chargeableDays * $dailyRateConverted, 2);

            $lineSscl  = round($lineSubtotal * $tax1Rate / 100, 2);
            $lineVat   = round(($lineSubtotal + $lineSscl) * $tax2Rate / 100, 2);
            $lineTotal = round($lineSubtotal + $lineSscl + $lineVat, 2);
            // Value = amount in default currency (LKR); Amount = amount in invoice currency
            $lineValue  = $lineTotal;  // stored in default currency (LKR)
            $dispFactor = CurrencyService::invoiceDisplayFactor($invoiceCurrency, $exchangeRate);
            $lineAmount = round($lineTotal * $dispFactor, 2);

            $eqt      = $container->equipmentType;
            $eqtCode  = $eqt ? $eqt->eqt_code  : ($container->size . ($container->type_code ?? ''));
            $isoCode  = $eqt?->iso_code ?? null;
            $eqtLabel = $eqt
                ? $eqt->eqt_code . ' — ' . $eqt->description
                : ($container->size . "' " . $container->type_code);

            $lines[] = [
                'container_id'       => $container->id,
                'container_no'       => $container->container_no,
                'equipment_type_id'  => $eqtId ?: null,
                'equipment_type'     => $eqtLabel,
                'eqt_code'           => $eqtCode,
                'iso_code'           => $isoCode,
                'type_code'          => $eqt ? $eqt->type_code : $container->type_code,
                'cargo_status'    => $cargoStatus,
                'gate_in_date'    => $gateIn->toDateString(),
                'from_date'       => $fromDate->toDateString(),
                'to_date'         => $toDate->toDateString(),
                'total_days'      => $totalDays,
                'free_days'       => $freeDaysInPeriod,
                'chargeable_days' => $chargeableDays,
                'daily_rate'      => $dailyRateConverted,
                'currency'        => $defaultCurrency,
                'subtotal'        => $lineSubtotal,
                'charge_code_id'  => $chargeCodeId,
                'tax1_rate'       => $tax1Rate,
                'tax2_rate'       => $tax2Rate,
                'line_sscl'       => $lineSscl,
                'line_vat'        => $lineVat,
                'line_total'      => $lineTotal,
                'line_value'      => $lineValue,   // default-currency (LKR) amount
                'line_amount'     => $lineAmount,  // invoice-currency amount (for display)
                'tariff_currency' => $currency,    // original tariff rate currency
                'tariff_found'    => (bool) $tariffHeader,
            ];
        }

        $subtotal     = round(array_sum(array_column($lines, 'subtotal')), 2);
        $ssclAmount   = round(array_sum(array_column($lines, 'line_sscl')), 2);
        $vatAmount    = round(array_sum(array_column($lines, 'line_vat')), 2);
        $totalAmount  = round(array_sum(array_column($lines, 'line_total')), 2);
        $totalValue   = $totalAmount;  // default-currency total (LKR)
        $dispFactor   = CurrencyService::invoiceDisplayFactor($invoiceCurrency, $exchangeRate);
        $totalDisplay = round($totalAmount * $dispFactor, 2);  // invoice-currency total

        return response()->json([
            'customer'         => $customer->name,
            'tax_exempt'       => $taxExempt,
            'lines'            => $lines,
            'invoice_currency' => $invoiceCurrency,
            'default_currency' => $defaultCurrency,
            'exchange_rate'    => $exchangeRate,
            'subtotal'         => $subtotal,
            'sscl_percentage'  => $ssclPct,
            'sscl_amount'      => $ssclAmount,
            'vat_percentage'   => $vatPct,
            'vat_amount'       => $vatAmount,
            'total_amount'     => $totalAmount,
            'total_value'      => $totalValue,
            'total_display'    => $totalDisplay,
            'tariff_found'     => (bool) $tariffHeader,
            'no_containers'    => false,
        ]);
    }

    // ── Save invoice ──────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'              => ['required', 'exists:customers,id'],
            'billing_party_id'         => ['nullable', 'exists:customers,id'],
            'invoice_type'             => ['nullable', 'string', 'in:tax_invoice,invoice,debit_note'],
            'invoice_date'             => ['required', 'date'],
            'invoice_currency'         => ['nullable', 'string', 'size:3'],
            'exchange_rate'            => ['nullable', 'numeric', 'min:0.0001'],
            'period_from'              => ['required', 'date'],
            'period_to'                => ['required', 'date', 'after_or_equal:period_from'],
            'sscl_percentage'          => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vat_percentage'           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
            'lines'                    => ['required', 'array', 'min:1'],
            'lines.*.container_id'       => ['required', 'integer'],
            'lines.*.container_no'       => ['required', 'string'],
            'lines.*.equipment_type_id'  => ['nullable', 'integer'],
            'lines.*.equipment_type'     => ['required', 'string'],
            'lines.*.cargo_status'     => ['nullable', 'in:laden,empty'],
            'lines.*.gate_in_date'     => ['required', 'date'],
            'lines.*.from_date'        => ['required', 'date'],
            'lines.*.to_date'          => ['required', 'date'],
            'lines.*.total_days'       => ['required', 'integer', 'min:0'],
            'lines.*.free_days'        => ['required', 'integer', 'min:0'],
            'lines.*.chargeable_days'  => ['required', 'integer', 'min:0'],
            'lines.*.daily_rate'       => ['required', 'numeric', 'min:0'],
            'lines.*.currency'         => ['required', 'string', 'max:3'],
            'lines.*.subtotal'         => ['required', 'numeric', 'min:0'],
            'lines.*.charge_code_id'   => ['nullable', 'integer'],
            'lines.*.tax1_rate'        => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax2_rate'        => ['nullable', 'numeric', 'min:0'],
            'lines.*.line_sscl'        => ['required', 'numeric', 'min:0'],
            'lines.*.line_vat'         => ['required', 'numeric', 'min:0'],
            'lines.*.line_total'       => ['required', 'numeric', 'min:0'],
            'lines.*.line_value'       => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoiceCurrency = strtoupper($validated['invoice_currency'] ?? 'LKR');
        $exchangeRate    = (float) ($validated['exchange_rate'] ?? 1.0);
        $ssclPct         = (float) ($validated['sscl_percentage'] ?? 0);
        $vatPct          = (float) ($validated['vat_percentage'] ?? 0);
        $subtotal        = round(array_sum(array_column($validated['lines'], 'subtotal')), 2);
        $ssclAmount      = round(array_sum(array_column($validated['lines'], 'line_sscl')), 2);
        $vatAmount       = round(array_sum(array_column($validated['lines'], 'line_vat')), 2);
        $totalAmount     = round(array_sum(array_column($validated['lines'], 'line_total')), 2);
        $totalValue      = round(array_sum(array_column($validated['lines'], 'line_value')), 2) ?: $totalAmount;

        // Generate sequential invoice number: SBI-YYYYMM-XXXX
        $prefix    = 'SBI-' . now()->format('Ym') . '-';
        $lastNo    = StorageInvoice::where('invoice_no', 'like', $prefix . '%')
                        ->lockForUpdate()
                        ->count();
        $invoiceNo = $prefix . str_pad($lastNo + 1, 4, '0', STR_PAD_LEFT);

        $invoice = null;

        DB::transaction(function () use ($validated, $invoiceNo, $invoiceCurrency, $exchangeRate, $ssclPct, $vatPct, $subtotal, $ssclAmount, $vatAmount, $totalAmount, $totalValue, &$invoice) {
            $invoice = StorageInvoice::create([
                'invoice_no'          => $invoiceNo,
                'invoice_type'        => $validated['invoice_type'] ?? 'invoice',
                'customer_id'         => $validated['customer_id'],
                'billing_party_id'    => $validated['billing_party_id'] ?? $validated['customer_id'],
                'invoice_date'        => $validated['invoice_date'],
                'invoice_currency'    => $invoiceCurrency,
                'exchange_rate'       => $exchangeRate,
                'billing_period_from' => $validated['period_from'],
                'billing_period_to'   => $validated['period_to'],
                'subtotal'            => $subtotal,
                'sscl_percentage'     => $ssclPct,
                'sscl_amount'         => $ssclAmount,
                'vat_percentage'      => $vatPct,
                'vat_amount'          => $vatAmount,
                'total_amount'        => $totalAmount,
                'total_value'         => $totalValue,
                'status'              => 'draft',
                'notes'               => $validated['notes'] ?? null,
                'created_by'          => auth()->id(),
            ]);

            foreach ($validated['lines'] as $line) {
                StorageInvoiceDetail::create([
                    'storage_invoice_id' => $invoice->id,
                    'container_id'       => $line['container_id'],
                    'container_no'       => $line['container_no'],
                    'equipment_type_id'  => ($line['equipment_type_id'] ?? null) ?: null,
                    'equipment_type'     => $line['equipment_type'],
                    'cargo_status'       => $line['cargo_status'] ?? null,
                    'gate_in_date'       => $line['gate_in_date'],
                    'from_date'          => $line['from_date'],
                    'to_date'            => $line['to_date'],
                    'total_days'         => $line['total_days'],
                    'free_days'          => $line['free_days'],
                    'chargeable_days'    => $line['chargeable_days'],
                    'daily_rate'         => $line['daily_rate'],
                    'currency'           => $line['currency'],
                    'subtotal'           => $line['subtotal'],
                    'charge_code_id'     => ($line['charge_code_id'] ?? null) ?: null,
                    'tax1_rate'          => $line['tax1_rate'] ?? 0,
                    'tax2_rate'          => $line['tax2_rate'] ?? 0,
                    'line_sscl'          => $line['line_sscl'],
                    'line_vat'           => $line['line_vat'],
                    'line_total'         => $line['line_total'],
                    'line_value'         => $line['line_value'] ?? $line['line_total'],
                ]);
            }
        });

        return redirect()->route('billing.show', $invoice)
            ->with('success', "Storage invoice {$invoiceNo} saved successfully.");
    }

    // ── View invoice ──────────────────────────────────────────────────────────

    public function show(StorageInvoice $invoice)
    {
        $invoice->load(['customer', 'billingParty', 'details.equipmentType', 'details.chargeCode.taxCode', 'createdBy']);
        return view('billing.show', compact('invoice'));
    }

    // ── Delete draft ──────────────────────────────────────────────────────────

    public function destroy(StorageInvoice $invoice)
    {
        if (! $invoice->isDraft()) {
            return back()->with('error', 'Only draft invoices can be deleted.');
        }

        DB::transaction(function () use ($invoice) {
            $invoice->details()->delete();
            $invoice->delete();
        });

        return redirect()->route('billing.index')
            ->with('success', 'Draft invoice deleted.');
    }

    // ── Status transitions ────────────────────────────────────────────────────

    public function markIssued(StorageInvoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be issued.');
        }

        $invoice->update(['status' => 'issued', 'sent_at' => now()]);

        return back()->with('success', "Invoice {$invoice->invoice_no} marked as issued.");
    }

    public function markPaid(StorageInvoice $invoice)
    {
        if (! in_array($invoice->status, ['issued', 'draft'])) {
            return back()->with('error', 'Invoice cannot be marked as paid from its current status.');
        }

        $invoice->update(['status' => 'paid']);

        return back()->with('success', "Invoice {$invoice->invoice_no} marked as paid.");
    }

    public function cancel(StorageInvoice $invoice)
    {
        if ($invoice->status === 'paid') {
            return back()->with('error', 'Paid invoices cannot be cancelled.');
        }

        $invoice->update(['status' => 'cancelled']);

        return back()->with('success', "Invoice {$invoice->invoice_no} cancelled.");
    }

    // ── Exchange rate lookup (AJAX) ───────────────────────────────────────────

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

    // ── Printable / PDF ───────────────────────────────────────────────────────

    public function pdf(StorageInvoice $invoice)
    {
        $invoice->load(['customer', 'details', 'createdBy']);

        $pdf = Pdf::loadView('billing.pdf', compact('invoice'))
            ->setPaper('a4', 'landscape')
            ->set_option('defaultFont', 'sans-serif')
            ->set_option('isHtml5ParserEnabled', true)
            ->set_option('isRemoteEnabled', false);

        $filename = 'Invoice-' . $invoice->invoice_no . '.pdf';

        return $pdf->stream($filename);
    }

    // ── Send by email ─────────────────────────────────────────────────────────

    public function sendEmail(Request $request, StorageInvoice $invoice)
    {
        $validated = $request->validate([
            'to_email' => ['required', 'email'],
            'cc_email' => ['nullable', 'email'],
            'message'  => ['nullable', 'string', 'max:1000'],
        ]);

        // Mark as issued if still draft
        if ($invoice->isDraft()) {
            $invoice->update(['status' => 'issued', 'sent_at' => now()]);
        }

        // TODO: Implement actual email delivery once mail is configured.
        // Example:
        // Mail::send('billing.email', compact('invoice', 'validated'), function ($m) use ($invoice, $validated) {
        //     $m->to($validated['to_email'])->subject("Storage Invoice {$invoice->invoice_no}");
        //     if ($validated['cc_email']) $m->cc($validated['cc_email']);
        // });

        return back()->with('success', "Invoice {$invoice->invoice_no} sent to {$validated['to_email']}. (Configure mail settings to enable delivery.)");
    }
}
