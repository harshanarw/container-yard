<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\ChargeCode;
use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\HandlingTariff;
use App\Models\StorageHandlingInvoice;
use App\Services\CurrencyService;
use App\Services\IrdInvoiceNumberService;
use App\Services\NotificationService;
use App\Services\Tariff\TariffRateGuard;
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
            ->when($request->bill_type,        fn ($q, $v) => $q->where('bill_type', $v))
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

    public function create(Request $request)
    {
        $shippingLines = Customer::with('billingParty')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $allCustomers = Customer::where('status', 'active')->orderBy('name')->get();

        $billType = in_array($request->query('bill_type'), [
            StorageHandlingInvoice::BILL_STORAGE_HANDLING,
            StorageHandlingInvoice::BILL_STORAGE_ONLY,
            StorageHandlingInvoice::BILL_HANDLING_ONLY,
        ], true) ? $request->query('bill_type') : StorageHandlingInvoice::BILL_STORAGE_HANDLING;

        return view('billing.storage-handling.create', compact('shippingLines', 'allCustomers', 'billType'));
    }

    // ── AJAX preview ──────────────────────────────────────────────────────────

    public function preview(Request $request)
    {
        $v = $request->validate([
            'bill_type'        => 'nullable|in:storage_handling,storage_only,handling_only',
            'shipping_line_id' => 'required|exists:customers,id',
            'period_from'      => 'required|date',
            'period_to'        => 'required|date|after_or_equal:period_from',
            'invoice_currency' => 'nullable|string|size:3',
            'exchange_rate'    => 'nullable|numeric|min:0.0001',
            'sscl_pct'         => 'nullable|numeric|min:0|max:100',
            'vat_pct'          => 'nullable|numeric|min:0|max:100',
        ]);

        // Bill type gates what is computed: storage records, handling (lift) events, or both.
        $billType      = $v['bill_type'] ?? StorageHandlingInvoice::BILL_STORAGE_HANDLING;
        $wantsStorage  = $billType !== StorageHandlingInvoice::BILL_HANDLING_ONLY;
        $wantsHandling = $billType !== StorageHandlingInvoice::BILL_STORAGE_ONLY;

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

        // ── Storage records active during period (only when storage is billed) ──
        $storageRecords = $wantsStorage
            ? YardStorage::with(['container.equipmentType'])
                ->where('customer_id', $shippingLine->id)
                ->where('gate_in_date', '<=', $periodTo)
                ->where(fn ($q) => $q->whereNull('gate_out_date')
                                      ->orWhere('gate_out_date', '>=', $periodFrom))
                ->orderBy('gate_in_date')
                ->get()
            : collect();

        // ── Gate movements → Lift Off / Lift On (only when handling is billed) ──
        $liftOffByContainer = $wantsHandling
            ? GateMovement::where('customer_id', $shippingLine->id)
                ->where('movement_type', 'in')
                ->whereBetween('gate_in_time', [$periodFrom, $periodToEod])
                ->get()
                ->keyBy('container_id')
            : collect();

        $liftOnByContainer = $wantsHandling
            ? GateMovement::where('customer_id', $shippingLine->id)
                ->where('movement_type', 'out')
                ->whereBetween('gate_out_time', [$periodFrom, $periodToEod])
                ->get()
                ->keyBy('container_id')
            : collect();

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
                'bill_type'              => $billType,
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

        // ── Active storage tariff (only when storage is billed) ───────────────
        $storageTariff = $wantsStorage
            ? StorageMasterHeader::with('details.equipmentType', 'details.chargeCode.taxCode')
                ->where('customer_id', $shippingLine->id)
                ->where('is_active', true)
                ->where('valid_from', '<=', $periodTo)
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $periodFrom))
                ->latest('valid_from')
                ->first()
            : null;

        // ── Active handling tariff (only when handling is billed) ─────────────
        $handlingTariff = $wantsHandling
            ? HandlingTariff::with('rates.chargeCode.taxCode')
                ->where('shipping_line_id', $shippingLine->id)
                ->where('is_active', true)
                ->where('valid_from', '<=', $periodTo)
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $periodFrom))
                ->latest('valid_from')
                ->first()
            : null;

        $lines = [];
        $guard = new TariffRateGuard();
        $storageFixUrl    = $storageTariff
            ? route('masters.storage-tariff.show', $storageTariff->id)
            : route('masters.storage-tariff.index');
        $storageFixLabel  = $storageTariff ? 'Edit storage tariff' : 'Set up storage tariff';
        $handlingFixUrl   = $handlingTariff
            ? route('masters.handling-tariff.show', $handlingTariff->id)
            : route('masters.handling-tariff.index');
        $handlingFixLabel = $handlingTariff ? 'Edit handling tariff' : 'Set up handling tariff';

        // Billing spine: storage/both are driven by storage records; handling-only
        // is driven by the containers that had a lift event in the period (which may
        // have no active storage record). Each unit is [container, storage(nullable)].
        if ($billType === StorageHandlingInvoice::BILL_HANDLING_ONLY) {
            $ids        = $liftOffByContainer->keys()->merge($liftOnByContainer->keys())->unique()->values();
            $containers = Container::with('equipmentType')->whereIn('id', $ids)->get()->keyBy('id');
            $spine      = $ids->map(fn ($id) => ['container' => $containers->get($id), 'storage' => null])
                              ->filter(fn ($u) => $u['container']);
        } else {
            $spine = $storageRecords->map(fn ($s) => ['container' => $s->container, 'storage' => $s])
                                    ->filter(fn ($u) => $u['container']);
        }

        foreach ($spine as $unit) {
            $container = $unit['container'];
            $storage   = $unit['storage'];

            $eqtId       = $container->equipment_type_id;
            $cargoStatus = $cargoStatusByContainer[$container->id] ?? 'empty';

            // Handling tax defaults (overridden by the handling charge code below).
            $handlingChargeCodeId = null;
            $handlingTaxCodeId    = null;
            $handlingTax1Rate     = $taxExempt ? 0.0 : $ssclPct;  // handling fallback
            $handlingTax2Rate     = $taxExempt ? 0.0 : $vatPct;   // handling fallback

            // Storage totals default to zero (handling-only leaves them zero); the
            // NOT-NULL date columns get period placeholders when there is no storage.
            $totalDays = 0; $freeDaysInPeriod = 0; $chargeableDays = 0;
            $storageRate = 0.0; $storageCur = $defaultCurrency; $storageDailyConverted = 0.0; $storageSubtotal = 0.0;
            $chargeCodeId = null; $taxCodeId = null; $tax1Rate = 0.0; $tax2Rate = 0.0;
            $detail = null;
            $fromStr    = $periodFrom->toDateString();
            $toStr      = $periodTo->toDateString();
            $gateInStr  = $periodFrom->toDateString();
            $gateOutStr = '';

            // ── Storage calculation (only when this bill includes storage) ────
            // billing_gate_in_date is the free-day anchor (original physical gate-in).
            // fromDate uses gate_in_date so resumed records aren't billed before they exist.
            // toDate is capped at gate_out_date for records closed mid-period.
            if ($wantsStorage && $storage) {
                $gateIn   = $storage->billing_gate_in_date;
                $fromDate = $storage->gate_in_date->gt($periodFrom)
                    ? $storage->gate_in_date->copy()
                    : $periodFrom->copy();
                $toDate   = $periodTo->copy();
                if ($storage->gate_out_date && $storage->gate_out_date->lt($toDate)) {
                    $toDate = $storage->gate_out_date->copy();
                }

                // Empty window (gate_out before gate_in, e.g. a same-day hire's
                // original record closed at gate_in − 1) accrues zero storage.
                $totalDays        = $toDate->lt($fromDate) ? 0 : max(1, (int) $fromDate->diffInDays($toDate) + 1);
                $daysBeforePeriod = max(0, (int) $gateIn->diffInDays($fromDate));

                $freeDays   = $storageTariff?->default_free_days ?? $storage->free_days ?? 0;
                $storageCur = 'USD';
                $tax1Rate   = $taxExempt ? 0.0 : $ssclPct;  // storage fallback
                $tax2Rate   = $taxExempt ? 0.0 : $vatPct;   // storage fallback

                if ($storageTariff) {
                    $detail = $storageTariff->details
                        ->where('equipment_type_id', $eqtId)
                        ->where('cargo_status', $cargoStatus)
                        ->first();
                    if ($detail) {
                        $storageRate  = (float) $detail->storage_rate;
                        $storageCur   = $detail->currency;
                        $chargeCodeId = $detail->charge_code_id;
                        $taxCodeId    = $detail->chargeCode?->tax_code_id;

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

                $fromStr    = $fromDate->toDateString();
                $toStr      = $toDate->toDateString();
                $gateInStr  = $gateIn->toDateString();
                $gateOutStr = $storage->gate_out_date?->toDateString() ?? '';
            } elseif ($wantsHandling) {
                // Handling-only: derive a display gate-in date from the lift movement.
                $gm = $liftOffByContainer->get($container->id) ?? $liftOnByContainer->get($container->id);
                $gateInStr  = $gm?->gate_in_time?->toDateString()
                           ?? $gm?->gate_out_time?->toDateString()
                           ?? $periodFrom->toDateString();
                $gateOutStr = $liftOnByContainer->get($container->id)?->gate_out_time?->toDateString() ?? '';
            }

            // ── Handling calculation ──────────────────────────────────────────
            $containerSize = $this->normalizeSize($container->size ?? '');
            $hasLiftOff    = isset($liftOffByContainer[$container->id]);
            $hasLiftOn     = isset($liftOnByContainer[$container->id]);

            $liftOffRate    = 0.0;
            $liftOnRate     = 0.0;
            $liftOffRateUsd = 0.0;
            $liftOnRateUsd  = 0.0;
            $handlingCur    = 'USD';

            $hRate = null;
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
                    $handlingTaxCodeId    = $hRate->chargeCode?->tax_code_id;
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

            // Flag missing/zero tariff rates only where they affect a billable
            // amount: storage with chargeable days, or an actual lift event.
            $storageReason = TariffRateGuard::storageReason($chargeableDays > 0, $storageRate, (bool) $storageTariff, (bool) $detail);
            if ($storageReason) {
                $guard->flag('storage', $eqtCode, $cargoStatus, $storageReason, $container->container_no, $storageFixUrl, $storageFixLabel);
            }
            $liftOffReason = TariffRateGuard::handlingReason($hasLiftOff, $liftOffRateUsd, (bool) $handlingTariff, (bool) $hRate, 'off');
            if ($liftOffReason) {
                $guard->flag('lift-off', $containerSize ? $containerSize . "'" : null, $cargoStatus, $liftOffReason, $container->container_no, $handlingFixUrl, $handlingFixLabel);
            }
            $liftOnReason = TariffRateGuard::handlingReason($hasLiftOn, $liftOnRateUsd, (bool) $handlingTariff, (bool) $hRate, 'on');
            if ($liftOnReason) {
                $guard->flag('lift-on', $containerSize ? $containerSize . "'" : null, $cargoStatus, $liftOnReason, $container->container_no, $handlingFixUrl, $handlingFixLabel);
            }

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
                'gate_in_date'             => $gateInStr,
                'gate_out_date'            => $gateOutStr,
                'storage_from'             => $fromStr,
                'storage_to'               => $toStr,
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
                'tax_code_id'              => $taxCodeId ?? null,
                'tax1_rate'                => $tax1Rate,
                'tax2_rate'                => $tax2Rate,
                'handling_charge_code_id'  => $handlingChargeCodeId,
                'handling_tax_code_id'     => $handlingTaxCodeId ?? null,
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
            'bill_type'              => $billType,
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
            'missing_rates'          => $guard->toArray(),
        ]);
    }

    // ── Save invoice ──────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        // Manual pricing bypasses the customer's agreed tariff, so the mode is
        // decided before validation and gates on its own permission rather than
        // on 'create'. An absent mode is the tariff flow, unchanged.
        $manual = $request->input('pricing_mode') === StorageHandlingInvoice::PRICING_MANUAL;

        if ($manual && ! auth()->user()?->can('billing.storage-handling.manual')) {
            abort(403, 'You are not permitted to price a storage & handling invoice manually.');
        }

        $rules = [
            'pricing_mode'                       => 'nullable|in:tariff,manual',
            'manual_free_days'                   => 'nullable|integer|min:0|max:9999',
            'bill_type'                          => 'nullable|in:storage_handling,storage_only,handling_only',
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
            'lines.*.container_size'              => 'nullable|string',
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
        ];

        if ($manual) {
            // A blank rate box is the operator forgetting a line, not a malformed
            // request. `required` would answer with "The field is required" against
            // an index the operator cannot see; the manual guard below names the
            // containers instead. Rules stay identical in tariff mode.
            foreach (['storage_daily_rate', 'lift_off_rate', 'lift_on_rate'] as $rate) {
                $rules["lines.*.{$rate}"] = 'present|nullable|numeric|min:0';
            }
        }

        $v = $request->validate($rules);

        // ── Authoritative rate guard ───────────────────────────────────────────
        // Tariff mode re-resolves rates from the tariffs (posted line values are
        // not trusted). Manual mode has no tariff to check against, so it checks
        // the only things that can still be wrong: a chargeable line with no rate
        // typed, and charge codes that will not resolve.
        $guardError = $manual ? $this->guardManualRates($v) : $this->guardHandlingRates($v);
        if ($guardError) {
            return $guardError;
        }

        if ($manual) {
            // The blanks that survive the guard are on lines with nothing to
            // price — a box still inside its free time, never lifted. They are
            // stored as 0 because the columns are decimals and '' is not a
            // number; the guard, not this, is what stops a blank that mattered.
            foreach ($v['lines'] as $i => $line) {
                foreach (['storage_daily_rate', 'lift_off_rate', 'lift_on_rate'] as $rate) {
                    $v['lines'][$i][$rate] = $this->hasManualRate($line[$rate] ?? null)
                        ? (float) $line[$rate]
                        : 0;
                }
            }
        }

        $invoiceCurrency  = strtoupper($v['invoice_currency'] ?? CurrencyService::defaultCurrency());
        // A foreign-currency invoice must carry a positive rate — never persist with
        // a silent 1.0 fallback, which would understate the base-currency ledger.
        if ($invoiceCurrency !== CurrencyService::defaultCurrency() && (float) ($v['exchange_rate'] ?? 0) <= 0) {
            return back()->withInput()->with('error',
                "A valid {$invoiceCurrency} → " . CurrencyService::defaultCurrency() . ' exchange rate is required for a foreign-currency invoice.');
        }
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

        $invoice = null;

        // Each bill type has its own invoice-number series (continuity for storage,
        // a dedicated series for handling).
        $billType = $v['bill_type'] ?? StorageHandlingInvoice::BILL_STORAGE_HANDLING;
        $seqKey   = match ($billType) {
            StorageHandlingInvoice::BILL_STORAGE_ONLY  => 'storage_invoice',
            StorageHandlingInvoice::BILL_HANDLING_ONLY => 'handling_invoice',
            default                                    => 'storage_handling_invoice',
        };

        DB::transaction(function () use ($v, $manual, $billType, $seqKey, $invoiceCurrency, $exchangeRate, $ssclPct, $vatPct, $storageTotalAmt, $handlingTotalAmt, $subtotal, $ssclAmount, $vatAmount, $totalAmount, $totalValue, &$invoice) {
            $invoiceNo = app(\App\Services\NumberSequenceService::class)->generate($seqKey);
            // Due date follows the debtor's (shipping line's) AR payment terms.
            $debtorTerms = \App\Models\Customer::where('id', $v['shipping_line_id'])->value('payment_terms') ?? 'net30';
            $dueDate     = \App\Services\Finance\PaymentTermsHelper::dueDate(
                $debtorTerms, \Carbon\Carbon::parse($v['invoice_date'])
            )->toDateString();

            $invoice = StorageHandlingInvoice::create([
                'invoice_no'          => $invoiceNo,
                'invoice_type'        => $v['invoice_type'] ?? 'invoice',
                'bill_type'           => $billType,
                // Stamped once. An invoice priced by hand stays priced by hand,
                // because that is what happened; the free time is kept as typed,
                // separately from what each line actually consumed.
                'pricing_mode'        => $manual
                    ? StorageHandlingInvoice::PRICING_MANUAL
                    : StorageHandlingInvoice::PRICING_TARIFF,
                'manual_free_days'    => $manual ? (int) ($v['manual_free_days'] ?? 0) : null,
                'shipping_line_id'    => $v['shipping_line_id'],
                'billing_party_id'    => $v['billing_party_id'] ?? $v['shipping_line_id'],
                'invoice_date'        => $v['invoice_date'],
                'due_date'            => $dueDate,
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
                    'container_size'           => $line['container_size'] ?? null,
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
                    'tax_code_id'              => ($line['tax_code_id'] ?? null) ?: null,
                    'tax1_rate'                => $line['tax1_rate'] ?? 0,
                    'tax2_rate'                => $line['tax2_rate'] ?? 0,
                    'handling_charge_code_id'  => ($line['handling_charge_code_id'] ?? null) ?: null,
                    'handling_tax_code_id'     => ($line['handling_tax_code_id'] ?? null) ?: null,
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
            ->with('success', "Storage & Handling invoice {$invoice->invoice_no} created successfully.");
    }

    /**
     * Re-resolve storage & handling rates from the active tariffs (posted line
     * values are not trusted) and return a redirect-back response if any
     * chargeable storage line or lift event has no usable rate; null otherwise.
     */
    /**
     * The charge codes a manual bill posts against.
     *
     * A tariff line carries its own `charge_code_id`, and with it the tax codes
     * and the GL mapping. Manual pricing has no tariff line to inherit from, so
     * it resolves the same two codes the tariff screens pre-select — which is
     * what keeps a manual bill posting to the same accounts as every other one.
     *
     * @return array{0: ?ChargeCode, 1: ?ChargeCode} storage, handling
     */
    private function defaultChargeCodes(): array
    {
        $codes = ChargeCode::with('taxCode')
            ->whereIn('code', [ChargeCode::DEFAULT_STORAGE, ChargeCode::DEFAULT_HANDLING])
            ->where('is_active', true)
            ->get()
            ->keyBy('code');

        return [
            $codes->get(ChargeCode::DEFAULT_STORAGE),
            $codes->get(ChargeCode::DEFAULT_HANDLING),
        ];
    }

    /**
     * The manual-pricing counterpart of guardHandlingRates().
     *
     * There is no tariff to re-resolve against — the operator's numbers *are*
     * the authority, which is the whole point of the mode — so the guard checks
     * the two things that can still make a manual bill wrong: a chargeable line
     * nobody typed a rate for, and a charge code that will not resolve (without
     * one the line has no tax treatment and no account to post to).
     *
     * A zero rate is deliberately not blocked here; Phase 3 asks for it to be
     * confirmed rather than rejected, because zero is occasionally intended.
     */
    private function guardManualRates(array $v)
    {
        [$storageCode, $handlingCode] = $this->defaultChargeCodes();

        $missingCodes = [];
        $needsStorage  = false;
        $needsHandling = false;

        $guard   = new TariffRateGuard();
        $fixUrl  = route('billing.storage-handling.index');
        $missing = 'No rate entered for this line.';

        foreach ($v['lines'] as $line) {
            $cargo       = $line['cargo_status'] ?? null;
            $containerNo = $line['container_no'] ?? null;
            $size        = $line['container_size'] ?? null;

            if ((int) ($line['storage_chargeable_days'] ?? 0) > 0) {
                $needsStorage = true;
                if (! $this->hasManualRate($line['storage_daily_rate'] ?? null)) {
                    $guard->flag('storage', $line['equipment_type'] ?? null, $cargo, $missing, $containerNo, $fixUrl, 'Back to the invoice');
                }
            }

            if (! empty($line['has_lift_off'])) {
                $needsHandling = true;
                if (! $this->hasManualRate($line['lift_off_rate'] ?? null)) {
                    $guard->flag('lift-off', $size ? $size . "'" : null, $cargo, $missing, $containerNo, $fixUrl, 'Back to the invoice');
                }
            }

            if (! empty($line['has_lift_on'])) {
                $needsHandling = true;
                if (! $this->hasManualRate($line['lift_on_rate'] ?? null)) {
                    $guard->flag('lift-on', $size ? $size . "'" : null, $cargo, $missing, $containerNo, $fixUrl, 'Back to the invoice');
                }
            }
        }

        // Only complain about a code the bill actually needs — a handling-only
        // bill has no business being blocked by a missing storage code.
        if ($needsStorage && ! $storageCode) {
            $missingCodes[] = ChargeCode::DEFAULT_STORAGE;
        }
        if ($needsHandling && ! $handlingCode) {
            $missingCodes[] = ChargeCode::DEFAULT_HANDLING;
        }

        if ($missingCodes) {
            return redirect()->back()->withInput()->with('error',
                'Invoice not saved — charge code ' . implode(' and ', $missingCodes)
                . ' is missing or inactive. Manual pricing takes its tax codes and accounts from there,'
                . ' so it must exist in the Charge Code master before a manual bill can be raised.');
        }

        if ($guard->isEmpty()) {
            return null;
        }

        return redirect()->back()->withInput()
            ->with('tariff_block', $guard->toArray())
            ->with('error', 'Invoice not saved — no rate entered for: ' . $guard->summary()
                . '. Please fill in every chargeable line.');
    }

    /**
     * A rate the operator actually typed. Blank and null are "not entered";
     * an explicit 0 is a value, and is handled separately.
     */
    private function hasManualRate($value): bool
    {
        return $value !== null && $value !== '' && is_numeric($value);
    }

    private function guardHandlingRates(array $v)
    {
        $storageTariff = StorageMasterHeader::with('details')
            ->where('customer_id', $v['shipping_line_id'])
            ->where('is_active', true)
            ->where('valid_from', '<=', $v['period_to'])
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $v['period_from']))
            ->latest('valid_from')
            ->first();

        $handlingTariff = HandlingTariff::with('rates')
            ->where('shipping_line_id', $v['shipping_line_id'])
            ->where('is_active', true)
            ->where('valid_from', '<=', $v['period_to'])
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $v['period_from']))
            ->latest('valid_from')
            ->first();

        $guard = new TariffRateGuard();
        $storageFixUrl    = $storageTariff ? route('masters.storage-tariff.show', $storageTariff->id) : route('masters.storage-tariff.index');
        $storageFixLabel  = $storageTariff ? 'Edit storage tariff' : 'Set up storage tariff';
        $handlingFixUrl   = $handlingTariff ? route('masters.handling-tariff.show', $handlingTariff->id) : route('masters.handling-tariff.index');
        $handlingFixLabel = $handlingTariff ? 'Edit handling tariff' : 'Set up handling tariff';

        foreach ($v['lines'] as $line) {
            $cargo       = $line['cargo_status'] ?? null;
            $containerNo = $line['container_no'] ?? null;
            $size        = $line['container_size'] ?? null;

            // Storage portion
            if ((int) ($line['storage_chargeable_days'] ?? 0) > 0) {
                $eqtId  = ($line['equipment_type_id'] ?? null) ?: null;
                $detail = $storageTariff
                    ? $storageTariff->details->where('equipment_type_id', $eqtId)->where('cargo_status', $cargo)->first()
                    : null;
                $rate = $storageTariff
                    ? (float) ($detail->storage_rate ?? 0)
                    : (float) ($line['storage_daily_rate'] ?? 0);
                $reason = TariffRateGuard::storageReason(true, $rate, (bool) $storageTariff, (bool) $detail);
                if ($reason) {
                    $guard->flag('storage', $line['equipment_type'] ?? null, $cargo, $reason, $containerNo, $storageFixUrl, $storageFixLabel);
                }
            }

            // Handling portion — only the lift directions that actually occurred
            $hRate = ($handlingTariff && $size)
                ? $handlingTariff->rates->where('container_size', $size)->where('cargo_status', $cargo)->first()
                : null;

            if (! empty($line['has_lift_off'])) {
                $rate   = (float) ($hRate->lift_off_rate ?? 0);
                $reason = TariffRateGuard::handlingReason(true, $rate, (bool) $handlingTariff, (bool) $hRate, 'off');
                if ($reason) {
                    $guard->flag('lift-off', $size ? $size . "'" : null, $cargo, $reason, $containerNo, $handlingFixUrl, $handlingFixLabel);
                }
            }
            if (! empty($line['has_lift_on'])) {
                $rate   = (float) ($hRate->lift_on_rate ?? 0);
                $reason = TariffRateGuard::handlingReason(true, $rate, (bool) $handlingTariff, (bool) $hRate, 'on');
                if ($reason) {
                    $guard->flag('lift-on', $size ? $size . "'" : null, $cargo, $reason, $containerNo, $handlingFixUrl, $handlingFixLabel);
                }
            }
        }

        if ($guard->isEmpty()) {
            return null;
        }

        return redirect()->back()->withInput()
            ->with('tariff_block', $guard->toArray())
            ->with('error', 'Invoice not saved — missing tariff rates for: ' . $guard->summary()
                . '. Please update the tariff and preview again.');
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

    public function markIssued(StorageHandlingInvoice $storageHandlingInvoice, \App\Services\Finance\CreditService $credit)
    {
        if ($storageHandlingInvoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be issued.');
        }

        // IRD middle tag identifies the bill type (STG / HDL / HND) on a shared counter.
        $irdType = match ($storageHandlingInvoice->bill_type) {
            StorageHandlingInvoice::BILL_STORAGE_ONLY  => 'storage',
            StorageHandlingInvoice::BILL_HANDLING_ONLY => 'handling',
            default                                    => 'storage_handling',
        };
        $irdNo = $storageHandlingInvoice->ird_invoice_no
            ?? app(IrdInvoiceNumberService::class)->generate($irdType, $storageHandlingInvoice->invoice_date);

        $storageHandlingInvoice->update(['status' => 'issued', 'sent_at' => now(), 'ird_invoice_no' => $irdNo]);

        NotificationService::notifyAll(
            'Handling Invoice Issued — ' . $storageHandlingInvoice->invoice_no,
            ($storageHandlingInvoice->billingParty->name ?? 'Unknown') . ' · ' . $storageHandlingInvoice->invoice_currency . ' ' . number_format($storageHandlingInvoice->total_amount, 2),
            'success',
            route('billing.storage-handling.show', $storageHandlingInvoice)
        );

        // AR exposure for handling invoices is keyed on the shipping line (debtor).
        $redirect = back()->with('success', "Invoice {$storageHandlingInvoice->invoice_no} marked as issued.");
        $debtor   = \App\Models\Customer::find($storageHandlingInvoice->shipping_line_id);
        if ($debtor && ($warning = $credit->arOverLimitWarning($debtor))) {
            $redirect->with('warning', $warning);
        }
        // Surface an auto-post failure so the invoice isn't silently left unposted.
        if ($err = \App\Services\Finance\InvoicePostingService::lastFailure()) {
            $redirect->with('warning', 'Issued, but not yet posted to the ledger — ' . $err . ' Use “Retry posting” on the invoice once the cause is resolved.');
        }

        return $redirect;
    }

    public function markPaid(StorageHandlingInvoice $storageHandlingInvoice)
    {
        if ($storageHandlingInvoice->status !== 'issued') {
            return back()->with('error', 'Only issued invoices can be marked as paid.');
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
        $storageHandlingInvoice->load(['shippingLine', 'billingParty', 'lines.chargeCode', 'lines.handlingChargeCode', 'createdBy']);
        $company = CompanySetting::current();

        $eqtCode = fn ($label) => trim(explode(' — ', $label ?? '')[0]) ?: '';

        // ── Build storage lines ───────────────────────────────────────────────
        $storageLines = $storageHandlingInvoice->lines
            ->filter(fn ($l) => ($l->storage_subtotal ?? 0) > 0 || ($l->storage_chargeable_days ?? 0) > 0)
            ->map(fn ($l) => [
                'reference'       => $l->container_no,
                'description'     => 'CONTAINER STORAGE'
                                     . (($eqt = trim($eqtCode($l->equipment_type) . ' ' . strtoupper($l->cargo_status ?? ''))) ? ' — ' . $eqt : '')
                                     . ' | ' . \Carbon\Carbon::parse($l->storage_from)->format('d M Y')
                                     . ' TO ' . \Carbon\Carbon::parse($l->storage_to)->format('d M Y'),
                'quantity'        => $l->storage_chargeable_days ?? 0,
                'unit_price'      => $l->storage_daily_rate ?? 0,
                'amount_excl_vat' => $l->storage_subtotal ?? 0,
            ]);

        // ── Build handling (LOLO) lines — separate lift-off and lift-on ───────
        $handlingLines = collect();
        foreach ($storageHandlingInvoice->lines as $l) {
            $eqtDesc = trim($eqtCode($l->equipment_type) . ' ' . strtoupper($l->cargo_status ?? ''));

            if ($l->has_lift_off && ($l->lift_off_rate ?? 0) > 0) {
                $handlingLines->push([
                    'reference'       => $l->container_no,
                    'description'     => 'LIFT-OFF (GATE-IN)' . ($eqtDesc ? ' — ' . $eqtDesc : ''),
                    'quantity'        => 1,
                    'unit_price'      => $l->lift_off_rate,
                    'amount_excl_vat' => $l->lift_off_rate,
                ]);
            }
            if ($l->has_lift_on && ($l->lift_on_rate ?? 0) > 0) {
                $handlingLines->push([
                    'reference'       => $l->container_no,
                    'description'     => 'LIFT-ON (GATE-OUT)' . ($eqtDesc ? ' — ' . $eqtDesc : ''),
                    'quantity'        => 1,
                    'unit_price'      => $l->lift_on_rate,
                    'amount_excl_vat' => $l->lift_on_rate,
                ]);
            }
        }

        // ── Combine: storage lines first, then LOLO lines ────────────────────
        $lines = $storageLines->concat($handlingLines);

        // ── Derive rate labels from all line-level charge codes ───────────────
        $invoiceLines = $storageHandlingInvoice->lines;
        $ssclRates = $invoiceLines->flatMap(fn ($l) => [
            ($l->tax1_rate ?? 0) > 0          ? round((float)$l->tax1_rate, 4)          : null,
            ($l->handling_tax1_rate ?? 0) > 0  ? round((float)$l->handling_tax1_rate, 4) : null,
        ])->filter()->unique()->sort()->values();
        $vatRates = $invoiceLines->flatMap(fn ($l) => [
            ($l->tax2_rate ?? 0) > 0          ? round((float)$l->tax2_rate, 4)          : null,
            ($l->handling_tax2_rate ?? 0) > 0  ? round((float)$l->handling_tax2_rate, 4) : null,
        ])->filter()->unique()->sort()->values();

        $from         = $storageHandlingInvoice->billing_period_from?->format('d M Y');
        $to           = $storageHandlingInvoice->billing_period_to?->format('d M Y');
        $shippingLine = $storageHandlingInvoice->shippingLine ?? $storageHandlingInvoice->billingParty;

        $data = [
            'ird_invoice_no'        => $storageHandlingInvoice->ird_invoice_no ?? '—',
            'invoice_date'          => $storageHandlingInvoice->invoice_date,
            'company'               => $company,
            'verifyUrl'             => \Illuminate\Support\Facades\URL::signedRoute('documents.verify', ['type' => 'storage-handling', 'id' => $storageHandlingInvoice->id]),
            'customer'              => $shippingLine,
            'lines'                 => $lines,
            'subtotal'              => $storageHandlingInvoice->subtotal,
            'sscl_amount'           => $storageHandlingInvoice->sscl_amount ?? 0,
            'sscl_percentage'       => (float) ($ssclRates->first() ?? $storageHandlingInvoice->sscl_percentage ?? 0),
            'sscl_percentage_label' => $ssclRates->isNotEmpty()
                                        ? $ssclRates->map(fn($r) => number_format($r, 2).'%')->implode(' / ')
                                        : null,
            'vat_amount'            => $storageHandlingInvoice->vat_amount ?? 0,
            'vat_percentage'        => (float) ($vatRates->first() ?? $storageHandlingInvoice->vat_percentage ?? 0),
            'vat_percentage_label'  => $vatRates->isNotEmpty()
                                        ? $vatRates->map(fn($r) => number_format($r, 2).'%')->implode(' / ')
                                        : null,
            'total_incl_vat'        => $storageHandlingInvoice->total_amount,
            'invoice_currency'      => $storageHandlingInvoice->invoice_currency,
            'exchange_rate'         => $storageHandlingInvoice->exchange_rate,
            'invoice_no'            => $storageHandlingInvoice->invoice_no,
            'category_info'         => array_filter([
                'Category'          => $storageHandlingInvoice->bill_type_label,
                'Payment Due'       => $storageHandlingInvoice->due_date?->format('d M Y'),
                'Billing Period'    => $from && $to ? "{$from} to {$to}" : null,
                'Shipping Line'     => $shippingLine?->name,
                'No. of Containers' => $storageHandlingInvoice->lines
                    ->filter(fn($l) => ($l->storage_subtotal ?? 0) + ($l->handling_subtotal ?? 0) > 0)
                    ->pluck('container_no')->unique()->count() . ' unit(s)',
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
            ->setPaper('a4', 'portrait')
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
