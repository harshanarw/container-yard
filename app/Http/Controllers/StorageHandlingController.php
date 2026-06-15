<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\HandlingTariff;
use App\Models\StorageHandlingInvoice;
use App\Services\CurrencyService;
use App\Services\IrdInvoiceNumberService;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\StorageHandlingInvoiceLine;
use App\Models\StorageMasterHeader;
use App\Models\YardStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorageHandlingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:billing.storage-handling.view')->only(['index', 'show', 'preview']);
        $this->middleware('can:billing.storage-handling.create')->only(['create', 'store']);
        $this->middleware('can:billing.storage-handling.delete')->only(['destroy', 'cancel']);
        $this->middleware('can:billing.storage-handling.approve')->only(['markIssued', 'markPaid']);
        $this->middleware('can:billing.storage-handling.pdf')->only(['pdf', 'irdPrint']);
    }

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

        $shippingLines = Customer::where('status', 'active')->orderBy('name')->get();

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
        $shippingLines = Customer::with('billingParty')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $allCustomers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('billing.storage-handling.create', compact('shippingLines', 'allCustomers'));
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
        $taxExempt       = (bool) $shippingLine->tax_exempt;
        $periodFrom      = now()->parse($v['period_from'])->startOfDay();
        $periodTo        = now()->parse($v['period_to'])->startOfDay();
        $periodToEod     = now()->parse($v['period_to'])->endOfDay();   // for movement timestamps
        $invoiceCurrency = strtoupper($v['invoice_currency'] ?? 'LKR');
        $exchangeRate    = (float) ($v['exchange_rate'] ?? 1.0);
        $defaultCurrency = CurrencyService::defaultCurrency();
        $ssclPct         = (float) ($v['sscl_pct'] ?? 0);  // fallback rate
        $vatPct          = (float) ($v['vat_pct'] ?? 0);   // fallback rate

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

        // ── Cargo status per container from most recent gate-in movement ──────
        $cargoStatusByContainer = GateMovement::where('customer_id', $shippingLine->id)
            ->where('movement_type', 'in')
            ->orderByDesc('gate_in_time')
            ->get()
            ->keyBy('container_id')
            ->map(fn ($m) => $m->cargo_status);

        if ($storageRecords->isEmpty() && $liftOffByContainer->isEmpty() && $liftOnByContainer->isEmpty()) {
            return response()->json([
                'lines'                  => [],
                'tax_exempt'             => $taxExempt,
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
        $storageTariff = StorageMasterHeader::with('details.equipmentType', 'details.chargeCode.taxCode')
            ->where('customer_id', $shippingLine->id)
            ->where('is_active', true)
            ->where('valid_from', '<=', $periodTo)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $periodFrom))
            ->latest('valid_from')
            ->first();

        // ── Active handling tariff ────────────────────────────────────────────
        $handlingTariff = HandlingTariff::with('rates.chargeCode.taxCode')
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

            $eqtId        = $container->equipment_type_id;
            $cargoStatus  = $cargoStatusByContainer[$container->id] ?? 'empty';
            $freeDays     = $storageTariff?->default_free_days ?? $storage->free_days ?? 0;
            $storageRate  = 0.0;
            $storageCur   = 'USD';
            $chargeCodeId         = null;
            $tax1Rate             = $taxExempt ? 0.0 : $ssclPct;  // storage fallback
            $tax2Rate             = $taxExempt ? 0.0 : $vatPct;   // storage fallback
            $handlingChargeCodeId = null;
            $handlingTax1Rate     = $taxExempt ? 0.0 : $ssclPct;  // handling fallback
            $handlingTax2Rate     = $taxExempt ? 0.0 : $vatPct;   // handling fallback

            if ($storageTariff) {
                $detail = $storageTariff->details
                    ->where('equipment_type_id', $eqtId)
                    ->where('cargo_status', $cargoStatus)
                    ->first();
                if ($detail) {
                    $storageRate  = (float) $detail->storage_rate;
                    $storageCur   = $detail->currency;
                    $chargeCodeId = $detail->charge_code_id;

                    if (! $taxExempt && $detail->chargeCode?->taxCode) {
                        $tax1Rate = (float) $detail->chargeCode->taxCode->tax1_rate;
                        $tax2Rate = (float) $detail->chargeCode->taxCode->tax2_rate;
                    }
                }
            } else {
                $storageRate = (float) $storage->daily_rate;
                $freeDays    = (int)   ($storage->free_days ?? 0);
            }

            $freeDaysRemaining = max(0, $freeDays - $daysBeforePeriod);
            $freeDaysInPeriod  = min($totalDays, $freeDaysRemaining);
            $chargeableDays    = max(0, $totalDays - $freeDaysInPeriod);

            // Convert tariff rate to default currency: only multiply by exchangeRate when tariff is USD
            $storageMult           = CurrencyService::tariffMultiplier($storageCur, $exchangeRate);
            $storageDailyConverted = round($storageRate * $storageMult, 2);
            $storageSubtotal       = round($chargeableDays * $storageDailyConverted, 2);

            // ── Handling calculation ──────────────────────────────────────────
            $containerSize = $this->normalizeSize($container->size ?? '');
            $hasLiftOff    = isset($liftOffByContainer[$container->id]);
            $hasLiftOn     = isset($liftOnByContainer[$container->id]);

            $liftOffRate    = 0.0;
            $liftOnRate     = 0.0;
            $liftOffRateUsd = 0.0;
            $liftOnRateUsd  = 0.0;
            $handlingCur    = 'USD';

            if ($handlingTariff && $containerSize) {
                $hRate = $handlingTariff->rates
                    ->where('container_size', $containerSize)
                    ->where('cargo_status', $cargoStatus)
                    ->first();
                if ($hRate) {
                    $liftOffRateUsd = (float) $hRate->lift_off_rate;
                    $liftOnRateUsd  = (float) $hRate->lift_on_rate;
                    $handlingCur    = $hRate->currency ?? 'USD';
                    // Convert handling rates to default currency (same tariff-multiplier logic)
                    $handlingMult   = CurrencyService::tariffMultiplier($handlingCur, $exchangeRate);
                    $liftOffRate = round($liftOffRateUsd * $handlingMult, 2);
                    $liftOnRate  = round($liftOnRateUsd  * $handlingMult, 2);
                    // Capture handling charge code and tax rates separately from storage
                    $handlingChargeCodeId = $hRate->charge_code_id;
                    if (! $taxExempt && $hRate->chargeCode?->taxCode) {
                        $handlingTax1Rate = (float) $hRate->chargeCode->taxCode->tax1_rate;
                        $handlingTax2Rate = (float) $hRate->chargeCode->taxCode->tax2_rate;
                    }
                }
            }

            $handlingSubtotal = round(
                ($hasLiftOff ? $liftOffRate : 0.0) + ($hasLiftOn ? $liftOnRate : 0.0),
                2
            );
            $lineTotal = round($storageSubtotal + $handlingSubtotal, 2);

            // Calculate taxes separately so each portion uses its own charge code's rates
            $storageSscl  = round($storageSubtotal  * $tax1Rate         / 100, 2);
            $storageVat   = round(($storageSubtotal  + $storageSscl)  * $tax2Rate         / 100, 2);
            $handlingSscl = round($handlingSubtotal * $handlingTax1Rate / 100, 2);
            $handlingVat  = round(($handlingSubtotal + $handlingSscl) * $handlingTax2Rate / 100, 2);
            $lineSscl       = round($storageSscl  + $handlingSscl, 2);
            $lineVat        = round($storageVat   + $handlingVat,  2);
            $lineGrandTotal = round($lineTotal + $lineSscl + $lineVat, 2);
            // Value = default-currency (LKR) amount; Amount = invoice-currency amount
            $lineValue  = $lineGrandTotal;
            $dispFactor = CurrencyService::invoiceDisplayFactor($invoiceCurrency, $exchangeRate);
            $lineAmount = round($lineGrandTotal * $dispFactor, 2);

            $eqt      = $container->equipmentType;
            $eqtCode  = $eqt ? $eqt->eqt_code  : ($container->size . ($container->type_code ?? ''));
            $isoCode  = $eqt?->iso_code ?? null;
            $eqtLabel = $eqt
                ? $eqt->eqt_code . ' — ' . $eqt->description
                : ($container->size . "' " . $container->type_code);

            $lines[] = [
                'container_id'             => $container->id,
                'container_no'             => $container->container_no,
                'container_size'           => $containerSize,
                'equipment_type_id'        => $eqtId ?: null,
                'equipment_type'           => $eqtLabel,
                'eqt_code'                 => $eqtCode,
                'iso_code'                 => $isoCode,
                'type_code'                => $eqt ? $eqt->type_code : $container->type_code,
                'cargo_status'             => $cargoStatus,
                'gate_in_date'             => $gateIn->toDateString(),
                'gate_out_date'            => $storage->gate_out_date?->toDateString() ?? '',
                'storage_from'             => $fromDate->toDateString(),
                'storage_to'               => $toDate->toDateString(),
                'storage_total_days'       => $totalDays,
                'storage_free_days'        => $freeDaysInPeriod,
                'storage_chargeable_days'  => $chargeableDays,
                'storage_daily_rate'       => $storageDailyConverted,
                'storage_daily_rate_usd'   => $storageRate,
                'storage_tariff_currency'  => $storageCur,
                'exchange_rate'            => $exchangeRate,
                'storage_currency'         => $defaultCurrency,
                'storage_subtotal'         => $storageSubtotal,
                'has_lift_off'             => $hasLiftOff ? 1 : 0,
                'lift_off_rate'            => $liftOffRate,
                'lift_off_rate_usd'        => $liftOffRateUsd,
                'has_lift_on'              => $hasLiftOn ? 1 : 0,
                'lift_on_rate'             => $liftOnRate,
                'lift_on_rate_usd'         => $liftOnRateUsd,
                'handling_tariff_currency' => $handlingCur,
                'handling_currency'        => $defaultCurrency,
                'handling_subtotal'        => $handlingSubtotal,
                'charge_code_id'           => $chargeCodeId,
                'tax1_rate'                => $tax1Rate,
                'tax2_rate'                => $tax2Rate,
                'handling_charge_code_id'  => $handlingChargeCodeId,
                'handling_tax1_rate'       => $handlingTax1Rate,
                'handling_tax2_rate'       => $handlingTax2Rate,
                'line_total'               => $lineTotal,
                'line_sscl'                => $lineSscl,
                'line_vat'                 => $lineVat,
                'line_grand_total'         => $lineGrandTotal,
                'line_value'               => $lineValue,   // default-currency (LKR) amount
                'line_amount'              => $lineAmount,  // invoice-currency amount (for display)
            ];
        }

        $storageTotalAmt  = round(array_sum(array_column($lines, 'storage_subtotal')), 2);
        $handlingTotalAmt = round(array_sum(array_column($lines, 'handling_subtotal')), 2);
        $subtotal         = round($storageTotalAmt + $handlingTotalAmt, 2);
        $ssclAmount       = round(array_sum(array_column($lines, 'line_sscl')), 2);
        $vatAmount        = round(array_sum(array_column($lines, 'line_vat')), 2);
        $totalAmount      = round($subtotal + $ssclAmount + $vatAmount, 2);
        $totalValue       = $totalAmount;
        $dispFactor       = CurrencyService::invoiceDisplayFactor($invoiceCurrency, $exchangeRate);
        $totalDisplay     = round($totalAmount * $dispFactor, 2);

        return response()->json([
            'shipping_line'          => $shippingLine->name,
            'tax_exempt'             => $taxExempt,
            'lines'                  => $lines,
            'invoice_currency'       => $invoiceCurrency,
            'default_currency'       => $defaultCurrency,
            'exchange_rate'          => $exchangeRate,
            'storage_subtotal'       => $storageTotalAmt,
            'handling_subtotal'      => $handlingTotalAmt,
            'subtotal'               => $subtotal,
            'sscl_percentage'        => $ssclPct,
            'sscl_amount'            => $ssclAmount,
            'vat_percentage'         => $vatPct,
            'vat_amount'             => $vatAmount,
            'total_amount'           => $totalAmount,
            'total_value'            => $totalValue,
            'total_display'          => $totalDisplay,
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
            'billing_party_id'                   => 'nullable|exists:customers,id',
            'invoice_type'                        => 'nullable|string|in:tax_invoice,invoice,debit_note',
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
            'lines.*.equipment_type_id'           => 'nullable|integer',
            'lines.*.equipment_type'              => 'required|string',
            'lines.*.cargo_status'                => 'nullable|in:laden,empty',
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
            'lines.*.charge_code_id'              => 'nullable|integer',
            'lines.*.tax1_rate'                   => 'nullable|numeric|min:0',
            'lines.*.tax2_rate'                   => 'nullable|numeric|min:0',
            'lines.*.handling_charge_code_id'     => 'nullable|integer',
            'lines.*.handling_tax1_rate'          => 'nullable|numeric|min:0',
            'lines.*.handling_tax2_rate'          => 'nullable|numeric|min:0',
            'lines.*.line_total'                  => 'required|numeric|min:0',
            'lines.*.line_sscl'                   => 'required|numeric|min:0',
            'lines.*.line_vat'                    => 'required|numeric|min:0',
            'lines.*.line_grand_total'            => 'required|numeric|min:0',
            'lines.*.line_value'                  => 'nullable|numeric|min:0',
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
        $totalValue       = round(array_sum(array_column($v['lines'], 'line_value')), 2) ?: $totalAmount;

        // Sequential invoice number: SHI-YYYYMM-XXXX
        $prefix    = 'SHI-' . now()->format('Ym') . '-';
        $lastNo    = StorageHandlingInvoice::where('invoice_no', 'like', $prefix . '%')
                        ->lockForUpdate()
                        ->count();
        $invoiceNo = $prefix . str_pad($lastNo + 1, 4, '0', STR_PAD_LEFT);

        $invoice = null;

        DB::transaction(function () use ($v, $invoiceNo, $invoiceCurrency, $exchangeRate, $ssclPct, $vatPct, $storageTotalAmt, $handlingTotalAmt, $subtotal, $ssclAmount, $vatAmount, $totalAmount, $totalValue, &$invoice) {
            $invoice = StorageHandlingInvoice::create([
                'invoice_no'          => $invoiceNo,
                'invoice_type'        => $v['invoice_type'] ?? 'invoice',
                'shipping_line_id'    => $v['shipping_line_id'],
                'billing_party_id'    => $v['billing_party_id'] ?? $v['shipping_line_id'],
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
                'total_value'         => $totalValue,
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
                    'equipment_type_id'        => ($line['equipment_type_id'] ?? null) ?: null,
                    'equipment_type'           => $line['equipment_type'],
                    'cargo_status'             => $line['cargo_status'] ?? null,
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
                    'charge_code_id'           => ($line['charge_code_id'] ?? null) ?: null,
                    'tax1_rate'                => $line['tax1_rate'] ?? 0,
                    'tax2_rate'                => $line['tax2_rate'] ?? 0,
                    'handling_charge_code_id'  => ($line['handling_charge_code_id'] ?? null) ?: null,
                    'handling_tax1_rate'       => $line['handling_tax1_rate'] ?? 0,
                    'handling_tax2_rate'       => $line['handling_tax2_rate'] ?? 0,
                    'line_total'               => $line['line_total'],
                    'line_sscl'                => $line['line_sscl'],
                    'line_vat'                 => $line['line_vat'],
                    'line_grand_total'         => $line['line_grand_total'],
                    'line_value'               => $line['line_value'] ?? $line['line_grand_total'],
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
        $storageHandlingInvoice->load(['shippingLine', 'billingParty', 'lines.equipmentType', 'lines.chargeCode.taxCode', 'lines.handlingChargeCode.taxCode', 'createdBy']);
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

        $irdNo = $storageHandlingInvoice->ird_invoice_no
            ?? app(IrdInvoiceNumberService::class)->generate('storage_handling', $storageHandlingInvoice->invoice_date);

        $storageHandlingInvoice->update(['status' => 'issued', 'sent_at' => now(), 'ird_invoice_no' => $irdNo]);

        NotificationService::notifyAll(
            'Handling Invoice Issued — ' . $storageHandlingInvoice->invoice_no,
            ($storageHandlingInvoice->billingParty->name ?? 'Unknown') . ' · ' . $storageHandlingInvoice->invoice_currency . ' ' . number_format($storageHandlingInvoice->total_amount, 2),
            'success',
            route('billing.storage-handling.show', $storageHandlingInvoice)
        );

        return back()->with('success', "Invoice {$storageHandlingInvoice->invoice_no} marked as issued.");
    }

    public function markPaid(StorageHandlingInvoice $storageHandlingInvoice)
    {
        if (! in_array($storageHandlingInvoice->status, ['issued', 'draft'])) {
            return back()->with('error', 'Invoice cannot be marked as paid from its current status.');
        }
        $storageHandlingInvoice->update(['status' => 'paid']);

        NotificationService::notifyAll(
            'Handling Invoice Paid — ' . $storageHandlingInvoice->invoice_no,
            ($storageHandlingInvoice->billingParty->name ?? 'Unknown') . ' · ' . $storageHandlingInvoice->invoice_currency . ' ' . number_format($storageHandlingInvoice->total_amount, 2),
            'success',
            route('billing.storage-handling.show', $storageHandlingInvoice)
        );

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

    // ── IRD Tax Invoice print ─────────────────────────────────────────────────

    public function irdPrint(StorageHandlingInvoice $storageHandlingInvoice)
    {
        $storageHandlingInvoice->load(['shippingLine', 'billingParty', 'lines', 'createdBy']);
        $company = CompanySetting::current();

        $eqtCode = fn ($label) => trim(explode(' — ', $label ?? '')[0]) ?: '—';

        $lines = $storageHandlingInvoice->lines->map(fn ($l) => [
            'reference'       => $l->container_no,
            'description'     => 'Storage & Handling — ' . $eqtCode($l->equipment_type ?? $l->container_no)
                                 . ' | ' . \Carbon\Carbon::parse($l->storage_from)->format('d M Y')
                                 . ' to ' . \Carbon\Carbon::parse($l->storage_to)->format('d M Y'),
            'quantity'        => $l->storage_chargeable_days ?? 1,
            'unit_price'      => ($l->storage_subtotal + $l->handling_subtotal) / max(1, $l->storage_chargeable_days ?? 1),
            'amount_excl_vat' => $l->storage_subtotal + $l->handling_subtotal,
        ]);

        $from         = $storageHandlingInvoice->billing_period_from?->format('d M Y');
        $to           = $storageHandlingInvoice->billing_period_to?->format('d M Y');
        $shippingLine = $storageHandlingInvoice->shippingLine ?? $storageHandlingInvoice->billingParty;

        $data = [
            'ird_invoice_no'   => $storageHandlingInvoice->ird_invoice_no ?? '—',
            'invoice_date'     => $storageHandlingInvoice->invoice_date,
            'company'          => $company,
            'customer'         => $shippingLine,
            'lines'            => $lines,
            'subtotal'         => $storageHandlingInvoice->subtotal,
            'sscl_amount'      => $storageHandlingInvoice->sscl_amount ?? 0,
            'sscl_percentage'  => (float) ($storageHandlingInvoice->lines->firstWhere('tax1_rate', '>', 0)?->tax1_rate
                                  ?? $storageHandlingInvoice->sscl_percentage ?? 0),
            'vat_amount'       => $storageHandlingInvoice->vat_amount ?? 0,
            'vat_percentage'   => (float) ($storageHandlingInvoice->lines->firstWhere('tax2_rate', '>', 0)?->tax2_rate
                                  ?? $storageHandlingInvoice->vat_percentage ?? 0),
            'total_incl_vat'   => $storageHandlingInvoice->total_amount,
            'invoice_currency' => $storageHandlingInvoice->invoice_currency,
            'exchange_rate'    => $storageHandlingInvoice->exchange_rate,
            'invoice_no'       => $storageHandlingInvoice->invoice_no,
            'category_info'    => array_filter([
                'Category'          => 'Storage & Handling',
                'Billing Period'    => $from && $to ? "{$from} to {$to}" : null,
                'Shipping Line'     => $shippingLine?->name,
                'No. of Containers' => $storageHandlingInvoice->lines->count() . ' unit(s)',
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

    // ── Print / PDF ───────────────────────────────────────────────────────────

    public function pdf(StorageHandlingInvoice $storageHandlingInvoice)
    {
        $storageHandlingInvoice->load(['shippingLine', 'lines', 'createdBy']);

        $pdf = Pdf::loadView('billing.storage-handling.pdf', ['invoice' => $storageHandlingInvoice])
            ->setPaper('a4', 'landscape')
            ->set_option('defaultFont', 'sans-serif')
            ->set_option('isHtml5ParserEnabled', true)
            ->set_option('isRemoteEnabled', false);

        $filename = 'Invoice-' . $storageHandlingInvoice->invoice_no . '.pdf';

        return $pdf->stream($filename);
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
