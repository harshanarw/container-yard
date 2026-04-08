<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\HandlingTariff;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageHandlingInvoiceLine;
use App\Models\StorageMasterHeader;
use App\Models\YardStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorageHandlingController extends Controller
{
    // ── Invoice list ──────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $invoices = StorageHandlingInvoice::with('shippingLine')
            ->withCount('lines')
            ->when($request->shipping_line_id, fn ($q, $v) => $q->where('shipping_line_id', $v))
            ->when($request->status,           fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($sub) =>
                    $sub->where('invoice_no', 'like', "%{$s}%")
                        ->orWhereHas('shippingLine', fn ($c) => $c->where('name', 'like', "%{$s}%"))
                )
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $shippingLines = Customer::where('type', 'shipping_line')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $stats = [
            'total'    => StorageHandlingInvoice::count(),
            'draft'    => StorageHandlingInvoice::where('status', 'draft')->count(),
            'issued'   => StorageHandlingInvoice::where('status', 'issued')->count(),
            'paid'     => StorageHandlingInvoice::where('status', 'paid')->count(),
        ];

        return view('billing.storage-handling.index', compact('invoices', 'shippingLines', 'stats'));
    }

    // ── Generate form ─────────────────────────────────────────────────────────

    public function create()
    {
        $shippingLines = Customer::where('type', 'shipping_line')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('billing.storage-handling.create', compact('shippingLines'));
    }

    // ── AJAX preview ──────────────────────────────────────────────────────────

    public function preview(Request $request)
    {
        $v = $request->validate([
            'shipping_line_id' => 'required|exists:customers,id',
            'period_from'      => 'required|date',
            'period_to'        => 'required|date|after_or_equal:period_from',
            'invoice_currency' => 'nullable|string|size:3',
            'exchange_rate'    => 'nullable|numeric|min:0.0001',
            'sscl_pct'         => 'nullable|numeric|min:0|max:100',
            'vat_pct'          => 'nullable|numeric|min:0|max:100',
        ]);

        $shippingLine    = Customer::findOrFail($v['shipping_line_id']);
        $periodFrom      = now()->parse($v['period_from'])->startOfDay();
        $periodTo        = now()->parse($v['period_to'])->startOfDay();
        $periodToEod     = now()->parse($v['period_to'])->endOfDay();   // for movement timestamps
        // exchange_rate is always "1 USD = X LKR" — LKR is the base/reporting currency
        $invoiceCurrency = strtoupper($v['invoice_currency'] ?? 'LKR');
        $exchangeRate    = (float) ($v['exchange_rate'] ?? 1.0);
        $ssclPct         = (float) ($v['sscl_pct'] ?? 0);
        $vatPct          = (float) ($v['vat_pct'] ?? 0);

        // ── Storage records active during period ─────────────────────────────
        $storageRecords = YardStorage::with(['container.equipmentType'])
            ->where('customer_id', $shippingLine->id)
            ->where('gate_in_date', '<=', $periodTo)
            ->where(fn ($q) => $q->whereNull('gate_out_date')
                                  ->orWhere('gate_out_date', '>=', $periodFrom))
            ->orderBy('gate_in_date')
            ->get();

        // ── Gate-in movements during period  → Lift Off ──────────────────────
        $liftOffByContainer = GateMovement::where('customer_id', $shippingLine->id)
            ->where('movement_type', 'in')
            ->whereBetween('gate_in_time', [$periodFrom, $periodToEod])
            ->get()
            ->keyBy('container_id');

        // ── Gate-out movements during period → Lift On ───────────────────────
        $liftOnByContainer = GateMovement::where('customer_id', $shippingLine->id)
            ->where('movement_type', 'out')
            ->whereBetween('gate_out_time', [$periodFrom, $periodToEod])
            ->get()
            ->keyBy('container_id');

        if ($storageRecords->isEmpty() && $liftOffByContainer->isEmpty() && $liftOnByContainer->isEmpty()) {
            return response()->json([
                'lines'                  => [],
                'invoice_currency'       => $invoiceCurrency,
                'exchange_rate'          => $exchangeRate,
                'storage_subtotal'       => 0,
                'handling_subtotal'      => 0,
                'subtotal'               => 0,
                'sscl_percentage'        => $ssclPct,
                'sscl_amount'            => 0,
                'vat_percentage'         => $vatPct,
                'vat_amount'             => 0,
                'total_amount'           => 0,
                'storage_tariff_found'   => false,
                'handling_tariff_found'  => false,
                'no_data'                => true,
            ]);
        }

        // ── Active storage tariff ─────────────────────────────────────────────
        $storageTariff = StorageMasterHeader::with('details.equipmentType')
            ->where('customer_id', $shippingLine->id)
            ->where('is_active', true)
            ->where('valid_from', '<=', $periodTo)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $periodFrom))
            ->latest('valid_from')
            ->first();

        // ── Active handling tariff ────────────────────────────────────────────
        $handlingTariff = HandlingTariff::with('rates')
            ->where('shipping_line_id', $shippingLine->id)
            ->where('is_active', true)
            ->where('valid_from', '<=', $periodTo)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $periodFrom))
            ->latest('valid_from')
            ->first();

        $lines = [];

        foreach ($storageRecords as $storage) {
            $container = $storage->container;
            if (! $container) continue;

            // ── Storage calculation ───────────────────────────────────────────
            $gateIn   = $storage->gate_in_date;
            $fromDate = $gateIn->gt($periodFrom) ? $gateIn->copy() : $periodFrom->copy();
            $toDate   = $periodTo->copy();

            $totalDays        = max(1, (int) $fromDate->diffInDays($toDate) + 1);
            $daysBeforePeriod = max(0, (int) $gateIn->diffInDays($fromDate));

            $eqtId         = $container->equipment_type_id;
            $freeDays      = $storageTariff?->default_free_days ?? $storage->free_days ?? 0;
            $storageRate   = 0.0;
            $storageCur    = 'USD';

            if ($storageTariff) {
                $detail = $storageTariff->details->firstWhere('equipment_type_id', $eqtId);
                if ($detail) {
                    $storageRate = (float) $detail->storage_rate;
                    $storageCur  = $detail->currency;
                }
            } else {
                $storageRate = (float) $storage->daily_rate;
                $freeDays    = (int)   ($storage->free_days ?? 0);
            }

            $freeDaysRemaining = max(0, $freeDays - $daysBeforePeriod);
            $freeDaysInPeriod  = min($totalDays, $freeDaysRemaining);
            $chargeableDays    = max(0, $totalDays - $freeDaysInPeriod);

            // Convert USD storage rate to LKR (base currency) using exchange_rate (1 USD = X LKR)
            $storageDailyConverted = round($storageRate * $exchangeRate, 2);
            $storageSubtotal       = round($chargeableDays * $storageDailyConverted, 2);

            // ── Handling calculation ──────────────────────────────────────────
            $containerSize = $this->normalizeSize($container->size ?? '');
            $hasLiftOff    = isset($liftOffByContainer[$container->id]);
            $hasLiftOn     = isset($liftOnByContainer[$container->id]);

            $liftOffRate = 0.0;
            $liftOnRate  = 0.0;

            if ($handlingTariff && $containerSize) {
                $hRate = $handlingTariff->rates->firstWhere('container_size', $containerSize);
                if ($hRate) {
                    // Convert handling rates to invoice currency
                    $liftOffRate = round((float) $hRate->lift_off_rate * $exchangeRate, 2);
                    $liftOnRate  = round((float) $hRate->lift_on_rate  * $exchangeRate, 2);
                }
            }

            $handlingSubtotal = round(
                ($hasLiftOff ? $liftOffRate : 0.0) + ($hasLiftOn ? $liftOnRate : 0.0),
                2
            );
            $lineTotal      = round($storageSubtotal + $handlingSubtotal, 2);
            $lineSscl       = round($lineTotal * $ssclPct / 100, 2);
            $lineVat        = round(($lineTotal + $lineSscl) * $vatPct / 100, 2);
            $lineGrandTotal = round($lineTotal + $lineSscl + $lineVat, 2);

            $eqtLabel = $container->equipmentType
                ? $container->equipmentType->eqt_code . ' — ' . $container->equipmentType->description
                : ($container->size . "' " . $container->type_code);

            $lines[] = [
                'container_id'             => $container->id,
                'container_no'             => $container->container_no,
                'container_size'           => $containerSize,
                'equipment_type'           => $eqtLabel,
                'gate_in_date'             => $gateIn->toDateString(),
                'gate_out_date'            => $storage->gate_out_date?->toDateString() ?? '',
                'storage_from'             => $fromDate->toDateString(),
                'storage_to'               => $toDate->toDateString(),
                'storage_total_days'       => $totalDays,
                'storage_free_days'        => $freeDaysInPeriod,
                'storage_chargeable_days'  => $chargeableDays,
                'storage_daily_rate'       => $storageDailyConverted,
                'storage_currency'         => 'LKR',   // amounts always stored in LKR
                'storage_subtotal'         => $storageSubtotal,
                'has_lift_off'             => $hasLiftOff ? 1 : 0,
                'lift_off_rate'            => $liftOffRate,
                'has_lift_on'              => $hasLiftOn ? 1 : 0,
                'lift_on_rate'             => $liftOnRate,
                'handling_currency'        => 'LKR',   // amounts always stored in LKR
                'handling_subtotal'        => $handlingSubtotal,
                'line_total'               => $lineTotal,
                'line_sscl'                => $lineSscl,
                'line_vat'                 => $lineVat,
                'line_grand_total'         => $lineGrandTotal,
            ];
        }

        $storageTotalAmt  = round(array_sum(array_column($lines, 'storage_subtotal')), 2);
        $handlingTotalAmt = round(array_sum(array_column($lines, 'handling_subtotal')), 2);
        $subtotal         = round($storageTotalAmt + $handlingTotalAmt, 2);
        $ssclAmount       = round(array_sum(array_column($lines, 'line_sscl')), 2);
        $vatAmount        = round(array_sum(array_column($lines, 'line_vat')), 2);
        $totalAmount      = round($subtotal + $ssclAmount + $vatAmount, 2);

        return response()->json([
            'shipping_line'          => $shippingLine->name,
            'lines'                  => $lines,
            'invoice_currency'       => $invoiceCurrency,
            'exchange_rate'          => $exchangeRate,
            'storage_subtotal'       => $storageTotalAmt,
            'handling_subtotal'      => $handlingTotalAmt,
            'subtotal'               => $subtotal,
            'sscl_percentage'        => $ssclPct,
            'sscl_amount'            => $ssclAmount,
            'vat_percentage'         => $vatPct,
            'vat_amount'             => $vatAmount,
            'total_amount'           => $totalAmount,
            'storage_tariff_found'   => (bool) $storageTariff,
            'handling_tariff_found'  => (bool) $handlingTariff,
            'no_data'                => false,
        ]);
    }

    // ── Save invoice ──────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $v = $request->validate([
            'shipping_line_id'                   => 'required|exists:customers,id',
            'invoice_date'                        => 'required|date',
            'invoice_currency'                    => 'nullable|string|size:3',
            'exchange_rate'                       => 'nullable|numeric|min:0.0001',
            'period_from'                         => 'required|date',
            'period_to'                           => 'required|date|after_or_equal:period_from',
            'sscl_percentage'                     => 'nullable|numeric|min:0|max:100',
            'vat_percentage'                      => 'nullable|numeric|min:0|max:100',
            'notes'                               => 'nullable|string|max:1000',
            'lines'                               => 'required|array|min:1',
            'lines.*.container_id'                => 'required|integer',
            'lines.*.container_no'                => 'required|string',
            'lines.*.container_size'              => 'required|string',
            'lines.*.equipment_type'              => 'required|string',
            'lines.*.gate_in_date'                => 'required|date',
            'lines.*.gate_out_date'               => 'nullable|date',
            'lines.*.storage_from'                => 'required|date',
            'lines.*.storage_to'                  => 'required|date',
            'lines.*.storage_total_days'          => 'required|integer|min:0',
            'lines.*.storage_free_days'           => 'required|integer|min:0',
            'lines.*.storage_chargeable_days'     => 'required|integer|min:0',
            'lines.*.storage_daily_rate'          => 'required|numeric|min:0',
            'lines.*.storage_currency'            => 'required|string|max:3',
            'lines.*.storage_subtotal'            => 'required|numeric|min:0',
            'lines.*.has_lift_off'                => 'required|boolean',
            'lines.*.lift_off_rate'               => 'required|numeric|min:0',
            'lines.*.has_lift_on'                 => 'required|boolean',
            'lines.*.lift_on_rate'                => 'required|numeric|min:0',
            'lines.*.handling_currency'           => 'required|string|max:3',
            'lines.*.handling_subtotal'           => 'required|numeric|min:0',
            'lines.*.line_total'                  => 'required|numeric|min:0',
            'lines.*.line_sscl'                   => 'required|numeric|min:0',
            'lines.*.line_vat'                    => 'required|numeric|min:0',
            'lines.*.line_grand_total'            => 'required|numeric|min:0',
        ]);

        $invoiceCurrency  = strtoupper($v['invoice_currency'] ?? 'LKR');
        $exchangeRate     = (float) ($v['exchange_rate'] ?? 1.0);
        $ssclPct          = (float) ($v['sscl_percentage'] ?? 0);
        $vatPct           = (float) ($v['vat_percentage'] ?? 0);
        $storageTotalAmt  = round(array_sum(array_column($v['lines'], 'storage_subtotal')),  2);
        $handlingTotalAmt = round(array_sum(array_column($v['lines'], 'handling_subtotal')), 2);
        $subtotal         = round($storageTotalAmt + $handlingTotalAmt, 2);
        $ssclAmount       = round(array_sum(array_column($v['lines'], 'line_sscl')), 2);
        $vatAmount        = round(array_sum(array_column($v['lines'], 'line_vat')), 2);
        $totalAmount      = round($subtotal + $ssclAmount + $vatAmount, 2);

        // Sequential invoice number: SHI-YYYYMM-XXXX
        $prefix    = 'SHI-' . now()->format('Ym') . '-';
        $lastNo    = StorageHandlingInvoice::where('invoice_no', 'like', $prefix . '%')
                        ->lockForUpdate()
                        ->count();
        $invoiceNo = $prefix . str_pad($lastNo + 1, 4, '0', STR_PAD_LEFT);

        $invoice = null;

        DB::transaction(function () use ($v, $invoiceNo, $invoiceCurrency, $exchangeRate, $ssclPct, $vatPct, $storageTotalAmt, $handlingTotalAmt, $subtotal, $ssclAmount, $vatAmount, $totalAmount, &$invoice) {
            $invoice = StorageHandlingInvoice::create([
                'invoice_no'          => $invoiceNo,
                'shipping_line_id'    => $v['shipping_line_id'],
                'invoice_date'        => $v['invoice_date'],
                'invoice_currency'    => $invoiceCurrency,
                'exchange_rate'       => $exchangeRate,
                'billing_period_from' => $v['period_from'],
                'billing_period_to'   => $v['period_to'],
                'storage_subtotal'    => $storageTotalAmt,
                'handling_subtotal'   => $handlingTotalAmt,
                'subtotal'            => $subtotal,
                'sscl_percentage'     => $ssclPct,
                'sscl_amount'         => $ssclAmount,
                'vat_percentage'      => $vatPct,
                'vat_amount'          => $vatAmount,
                'total_amount'        => $totalAmount,
                'status'              => 'draft',
                'notes'               => $v['notes'] ?? null,
                'created_by'          => auth()->id(),
            ]);

            foreach ($v['lines'] as $line) {
                StorageHandlingInvoiceLine::create([
                    'invoice_id'               => $invoice->id,
                    'container_id'             => $line['container_id'],
                    'container_no'             => $line['container_no'],
                    'container_size'           => $line['container_size'],
                    'equipment_type'           => $line['equipment_type'],
                    'gate_in_date'             => $line['gate_in_date'],
                    'gate_out_date'            => ($line['gate_out_date'] ?? '') ?: null,
                    'storage_from'             => $line['storage_from'],
                    'storage_to'               => $line['storage_to'],
                    'storage_total_days'       => $line['storage_total_days'],
                    'storage_free_days'        => $line['storage_free_days'],
                    'storage_chargeable_days'  => $line['storage_chargeable_days'],
                    'storage_daily_rate'       => $line['storage_daily_rate'],
                    'storage_currency'         => $line['storage_currency'],
                    'storage_subtotal'         => $line['storage_subtotal'],
                    'has_lift_off'             => (bool) $line['has_lift_off'],
                    'lift_off_rate'            => $line['lift_off_rate'],
                    'has_lift_on'              => (bool) $line['has_lift_on'],
                    'lift_on_rate'             => $line['lift_on_rate'],
                    'handling_currency'        => $line['handling_currency'],
                    'handling_subtotal'        => $line['handling_subtotal'],
                    'line_total'               => $line['line_total'],
                    'line_sscl'                => $line['line_sscl'],
                    'line_vat'                 => $line['line_vat'],
                    'line_grand_total'         => $line['line_grand_total'],
                ]);
            }
        });

        return redirect()
            ->route('billing.storage-handling.show', $invoice)
            ->with('success', "Storage & Handling invoice {$invoiceNo} created successfully.");
    }

    // ── View invoice ──────────────────────────────────────────────────────────

    public function show(StorageHandlingInvoice $storageHandlingInvoice)
    {
        $storageHandlingInvoice->load(['shippingLine', 'lines', 'createdBy']);
        return view('billing.storage-handling.show', ['invoice' => $storageHandlingInvoice]);
    }

    // ── Delete draft ──────────────────────────────────────────────────────────

    public function destroy(StorageHandlingInvoice $storageHandlingInvoice)
    {
        if (! $storageHandlingInvoice->isDraft()) {
            return back()->with('error', 'Only draft invoices can be deleted.');
        }

        DB::transaction(function () use ($storageHandlingInvoice) {
            $storageHandlingInvoice->lines()->delete();
            $storageHandlingInvoice->delete();
        });

        return redirect()
            ->route('billing.storage-handling.index')
            ->with('success', 'Draft invoice deleted.');
    }

    // ── Status transitions ────────────────────────────────────────────────────

    public function markIssued(StorageHandlingInvoice $storageHandlingInvoice)
    {
        if ($storageHandlingInvoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be issued.');
        }
        $storageHandlingInvoice->update(['status' => 'issued', 'sent_at' => now()]);
        return back()->with('success', "Invoice {$storageHandlingInvoice->invoice_no} marked as issued.");
    }

    public function markPaid(StorageHandlingInvoice $storageHandlingInvoice)
    {
        if (! in_array($storageHandlingInvoice->status, ['issued', 'draft'])) {
            return back()->with('error', 'Invoice cannot be marked as paid from its current status.');
        }
        $storageHandlingInvoice->update(['status' => 'paid']);
        return back()->with('success', "Invoice {$storageHandlingInvoice->invoice_no} marked as paid.");
    }

    public function cancel(StorageHandlingInvoice $storageHandlingInvoice)
    {
        if ($storageHandlingInvoice->status === 'paid') {
            return back()->with('error', 'Paid invoices cannot be cancelled.');
        }
        $storageHandlingInvoice->update(['status' => 'cancelled']);
        return back()->with('success', "Invoice {$storageHandlingInvoice->invoice_no} cancelled.");
    }

    // ── Print / PDF ───────────────────────────────────────────────────────────

    public function pdf(StorageHandlingInvoice $storageHandlingInvoice)
    {
        $storageHandlingInvoice->load(['shippingLine', 'lines', 'createdBy']);
        return view('billing.storage-handling.pdf', ['invoice' => $storageHandlingInvoice]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Normalise container size string to '20', '40', or '45'.
     * Handles values like "20", "40", "45", "20ft", etc.
     */
    private function normalizeSize(string $size): string
    {
        $num = (int) preg_replace('/\D/', '', $size);
        return in_array($num, [20, 40, 45]) ? (string) $num : '';
    }
}
