<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\StorageInvoice;
use App\Models\StorageInvoiceDetail;
use App\Models\StorageMasterHeader;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\YardStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorageBillingController extends Controller
{
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
        $customers = Customer::where('status', 'active')->orderBy('name')->get();
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
        $periodFrom      = now()->parse($validated['period_from'])->startOfDay();
        $periodTo        = now()->parse($validated['period_to'])->startOfDay();
        // exchange_rate is always "1 USD = X LKR" — LKR is the base/reporting currency
        $invoiceCurrency = strtoupper($validated['invoice_currency'] ?? 'LKR');
        $exchangeRate    = (float) ($validated['exchange_rate'] ?? 1.0);
        $ssclPct         = (float) ($validated['sscl_pct'] ?? 0);
        $vatPct          = (float) ($validated['vat_pct'] ?? 0);

        // All active yard storage records for this customer whose gate-in is on or before period end
        $storageRecords = YardStorage::with(['container.equipmentType'])
            ->where('customer_id', $customer->id)
            ->whereNull('gate_out_date')
            ->where('gate_in_date', '<=', $periodTo)
            ->orderBy('gate_in_date')
            ->get();

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
        $tariffHeader = StorageMasterHeader::with('details.equipmentType')
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
            $eqtId     = $container->equipment_type_id;
            $freeDays  = $tariffHeader?->default_free_days ?? $storage->free_days ?? 0;
            $dailyRate = 0.0;
            $currency  = 'LKR';

            if ($tariffHeader) {
                $detail = $tariffHeader->details->firstWhere('equipment_type_id', $eqtId);
                if ($detail) {
                    $dailyRate = (float) $detail->storage_rate;
                    $currency  = $detail->currency;
                }
            } else {
                $dailyRate = (float) $storage->daily_rate;
                $freeDays  = (int) ($storage->free_days ?? 0);
            }

            // Free days still available at the start of this billing period
            $freeDaysRemaining = max(0, $freeDays - $daysBeforePeriod);
            $freeDaysInPeriod  = min($totalDays, $freeDaysRemaining);
            $chargeableDays    = max(0, $totalDays - $freeDaysInPeriod);

            // Convert USD tariff rate to LKR (base currency) using exchange_rate (1 USD = X LKR)
            $dailyRateConverted = round($dailyRate * $exchangeRate, 2);
            $lineSubtotal       = round($chargeableDays * $dailyRateConverted, 2);

            $lineSscl  = round($lineSubtotal * $ssclPct / 100, 2);
            $lineVat   = round(($lineSubtotal + $lineSscl) * $vatPct / 100, 2);
            $lineTotal = round($lineSubtotal + $lineSscl + $lineVat, 2);

            $eqtLabel = $container->equipmentType
                ? $container->equipmentType->eqt_code . ' — ' . $container->equipmentType->description
                : ($container->size . "' " . $container->type_code);

            $lines[] = [
                'container_id'    => $container->id,
                'container_no'    => $container->container_no,
                'equipment_type'  => $eqtLabel,
                'gate_in_date'    => $gateIn->toDateString(),
                'from_date'       => $fromDate->toDateString(),
                'to_date'         => $toDate->toDateString(),
                'total_days'      => $totalDays,
                'free_days'       => $freeDaysInPeriod,
                'chargeable_days' => $chargeableDays,
                'daily_rate'      => $dailyRateConverted,
                'currency'        => 'LKR',   // amounts always stored in LKR
                'subtotal'        => $lineSubtotal,
                'line_sscl'       => $lineSscl,
                'line_vat'        => $lineVat,
                'line_total'      => $lineTotal,
                'tariff_found'    => (bool) $tariffHeader,
            ];
        }

        $subtotal    = round(array_sum(array_column($lines, 'subtotal')), 2);
        $ssclAmount  = round(array_sum(array_column($lines, 'line_sscl')), 2);
        $vatAmount   = round(array_sum(array_column($lines, 'line_vat')), 2);
        $totalAmount = round(array_sum(array_column($lines, 'line_total')), 2);

        return response()->json([
            'customer'         => $customer->name,
            'lines'            => $lines,
            'invoice_currency' => $invoiceCurrency,
            'exchange_rate'    => $exchangeRate,
            'subtotal'         => $subtotal,
            'sscl_percentage'  => $ssclPct,
            'sscl_amount'      => $ssclAmount,
            'vat_percentage'   => $vatPct,
            'vat_amount'       => $vatAmount,
            'total_amount'     => $totalAmount,
            'tariff_found'     => (bool) $tariffHeader,
            'no_containers'    => false,
        ]);
    }

    // ── Save invoice ──────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'              => ['required', 'exists:customers,id'],
            'invoice_date'             => ['required', 'date'],
            'invoice_currency'         => ['nullable', 'string', 'size:3'],
            'exchange_rate'            => ['nullable', 'numeric', 'min:0.0001'],
            'period_from'              => ['required', 'date'],
            'period_to'                => ['required', 'date', 'after_or_equal:period_from'],
            'sscl_percentage'          => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vat_percentage'           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
            'lines'                    => ['required', 'array', 'min:1'],
            'lines.*.container_id'     => ['required', 'integer'],
            'lines.*.container_no'     => ['required', 'string'],
            'lines.*.equipment_type'   => ['required', 'string'],
            'lines.*.gate_in_date'     => ['required', 'date'],
            'lines.*.from_date'        => ['required', 'date'],
            'lines.*.to_date'          => ['required', 'date'],
            'lines.*.total_days'       => ['required', 'integer', 'min:0'],
            'lines.*.free_days'        => ['required', 'integer', 'min:0'],
            'lines.*.chargeable_days'  => ['required', 'integer', 'min:0'],
            'lines.*.daily_rate'       => ['required', 'numeric', 'min:0'],
            'lines.*.currency'         => ['required', 'string', 'max:3'],
            'lines.*.subtotal'         => ['required', 'numeric', 'min:0'],
            'lines.*.line_sscl'        => ['required', 'numeric', 'min:0'],
            'lines.*.line_vat'         => ['required', 'numeric', 'min:0'],
            'lines.*.line_total'       => ['required', 'numeric', 'min:0'],
        ]);

        // exchange_rate always = "1 USD = X LKR"; all stored amounts are in LKR
        $invoiceCurrency = strtoupper($validated['invoice_currency'] ?? 'LKR');
        $exchangeRate    = (float) ($validated['exchange_rate'] ?? 1.0);
        $ssclPct         = (float) ($validated['sscl_percentage'] ?? 0);
        $vatPct          = (float) ($validated['vat_percentage'] ?? 0);
        $subtotal        = round(array_sum(array_column($validated['lines'], 'subtotal')), 2);
        $ssclAmount      = round(array_sum(array_column($validated['lines'], 'line_sscl')), 2);
        $vatAmount       = round(array_sum(array_column($validated['lines'], 'line_vat')), 2);
        $totalAmount     = round(array_sum(array_column($validated['lines'], 'line_total')), 2);

        // Generate sequential invoice number: SBI-YYYYMM-XXXX
        $prefix    = 'SBI-' . now()->format('Ym') . '-';
        $lastNo    = StorageInvoice::where('invoice_no', 'like', $prefix . '%')
                        ->lockForUpdate()
                        ->count();
        $invoiceNo = $prefix . str_pad($lastNo + 1, 4, '0', STR_PAD_LEFT);

        $invoice = null;

        DB::transaction(function () use ($validated, $invoiceNo, $invoiceCurrency, $exchangeRate, $ssclPct, $vatPct, $subtotal, $ssclAmount, $vatAmount, $totalAmount, &$invoice) {
            $invoice = StorageInvoice::create([
                'invoice_no'          => $invoiceNo,
                'customer_id'         => $validated['customer_id'],
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
                'status'              => 'draft',
                'notes'               => $validated['notes'] ?? null,
                'created_by'          => auth()->id(),
            ]);

            foreach ($validated['lines'] as $line) {
                StorageInvoiceDetail::create([
                    'storage_invoice_id' => $invoice->id,
                    'container_id'       => $line['container_id'],
                    'container_no'       => $line['container_no'],
                    'equipment_type'     => $line['equipment_type'],
                    'gate_in_date'       => $line['gate_in_date'],
                    'from_date'          => $line['from_date'],
                    'to_date'            => $line['to_date'],
                    'total_days'         => $line['total_days'],
                    'free_days'          => $line['free_days'],
                    'chargeable_days'    => $line['chargeable_days'],
                    'daily_rate'         => $line['daily_rate'],
                    'currency'           => $line['currency'],
                    'subtotal'           => $line['subtotal'],
                    'line_sscl'          => $line['line_sscl'],
                    'line_vat'           => $line['line_vat'],
                    'line_total'         => $line['line_total'],
                ]);
            }
        });

        return redirect()->route('billing.show', $invoice)
            ->with('success', "Storage invoice {$invoiceNo} saved successfully.");
    }

    // ── View invoice ──────────────────────────────────────────────────────────

    public function show(StorageInvoice $invoice)
    {
        $invoice->load(['customer', 'details', 'createdBy']);
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
