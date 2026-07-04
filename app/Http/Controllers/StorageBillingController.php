<?php

namespace App\Http\Controllers;

use App\Jobs\SendInvoiceEmailJob;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\StorageInvoice;
use App\Models\StorageInvoiceDetail;
use App\Models\StorageMasterHeader;
use App\Services\CurrencyService;
use App\Services\IrdInvoiceNumberService;
use App\Services\NotificationService;
use App\Services\Tariff\TariffRateGuard;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\YardStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorageBillingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:billing.storage.view')->only(['index', 'show']);
        $this->middleware('can:billing.storage.create')->only(['create', 'preview', 'store']);
        $this->middleware('can:billing.storage.delete')->only(['destroy', 'cancel']);
        $this->middleware('can:billing.storage.approve')->only(['markIssued', 'markPaid']);
        $this->middleware('can:billing.storage.pdf')->only(['pdf', 'irdPrint']);
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

        // Storage records for this customer that were active at any point during the billing period.
        // Includes closed records (gate_out_date set) that were still open at period start — this
        // ensures the pre-hire portion is billed when on-hire closes the record mid-period.
        // Excludes on_hire records (hire_type) which belong to the hire customer, not the original owner.
        $storageRecords = YardStorage::with(['container.equipmentType'])
            ->where('customer_id', $customer->id)
            ->whereIn('hire_type', ['normal', 'resumed'])
            ->where('gate_in_date', '<=', $periodTo)
            ->where(fn ($q) => $q->whereNull('gate_out_date')
                                  ->orWhere('gate_out_date', '>=', $periodFrom))
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
        $guard = new TariffRateGuard();
        $tariffFixUrl   = $tariffHeader
            ? route('masters.storage-tariff.show', $tariffHeader->id)
            : route('masters.storage-tariff.index');
        $tariffFixLabel = $tariffHeader ? 'Edit storage tariff' : 'Set up storage tariff';

        foreach ($storageRecords as $storage) {
            $container = $storage->container;
            if (! $container) continue;

            // billing_gate_in_date is the free-day continuity anchor:
            //   normal records  → gate_in_date (same)
            //   resumed records → original physical gate-in date (earlier than gate_in_date)
            $gateIn = $storage->billing_gate_in_date;

            // fromDate: start of the chargeable window for this record.
            // MUST use the record's actual gate_in_date (not billing_gate_in_date) so that
            // resumed records (gate_in = off-hire date) are never billed before they existed.
            $fromDate = $storage->gate_in_date->gt($periodFrom)
                ? $storage->gate_in_date->copy()
                : $periodFrom->copy();

            // toDate: cap at gate_out_date for closed records (e.g., original storage closed
            // at on_hire_date − 1).  Open records (null gate_out) run to the period end.
            $toDate = $periodTo->copy();
            if ($storage->gate_out_date && $storage->gate_out_date->lt($toDate)) {
                $toDate = $storage->gate_out_date->copy();
            }

            // Days in this billing period for this storage record
            $totalDays = max(1, (int) $fromDate->diffInDays($toDate) + 1);

            // Free days already consumed from the original gate-in up to fromDate
            $daysBeforePeriod = max(0, (int) $gateIn->diffInDays($fromDate));

            // Resolve rate from tariff, fall back to stored rate at gate-in
            $eqtId         = $container->equipment_type_id;
            $cargoStatus   = $gateInCargoStatus[$container->id] ?? 'empty';
            $freeDays      = $tariffHeader?->default_free_days ?? $storage->free_days ?? 0;
            $dailyRate     = 0.0;
            $currency      = 'LKR';
            $chargeCodeId  = null;
            $taxCodeId     = null;
            $tax1Rate      = $taxExempt ? 0.0 : $ssclPct;  // fallback
            $tax2Rate      = $taxExempt ? 0.0 : $vatPct;   // fallback
            $detail        = null;

            if ($tariffHeader) {
                $detail = $tariffHeader->details
                    ->where('equipment_type_id', $eqtId)
                    ->where('cargo_status', $cargoStatus)
                    ->first();
                if ($detail) {
                    $dailyRate    = (float) $detail->storage_rate;
                    $currency     = $detail->currency;
                    $chargeCodeId = $detail->charge_code_id;
                    $taxCodeId    = $detail->chargeCode?->tax_code_id;

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

            // Flag a missing/zero tariff rate only when it affects a chargeable
            // line (free-day-only containers bill zero legitimately).
            $reason = TariffRateGuard::storageReason(
                $chargeableDays > 0,
                $dailyRate,
                (bool) $tariffHeader,
                (bool) $detail
            );
            if ($reason) {
                $guard->flag('storage', $eqtCode, $cargoStatus, $reason, $container->container_no, $tariffFixUrl, $tariffFixLabel);
            }

            $lines[] = [
                'container_id'       => $container->id,
                'container_no'       => $container->container_no,
                'equipment_type_id'  => $eqtId ?: null,
                'equipment_type'     => $eqtLabel,
                'eqt_code'           => $eqtCode,
                'iso_code'           => $isoCode,
                'type_code'          => $eqt ? $eqt->type_code : $container->type_code,
                'container_size'  => $container->size,
                'cargo_status'    => $cargoStatus,
                'gate_in_date'    => $gateIn->toDateString(),
                'from_date'       => $fromDate->toDateString(),
                'to_date'         => $toDate->toDateString(),
                'total_days'      => $totalDays,
                'free_days'       => $freeDaysInPeriod,
                'chargeable_days' => $chargeableDays,
                'daily_rate'      => $dailyRateConverted,
                'daily_rate_usd'  => $dailyRate,   // raw tariff-currency rate (before conversion)
                'exchange_rate'   => $tariffMult,  // × factor applied to reach daily_rate (1 for LKR tariffs)
                'currency'        => $defaultCurrency,
                'subtotal'        => $lineSubtotal,
                'charge_code_id'  => $chargeCodeId,
                'tax_code_id'     => $taxCodeId ?? null,
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
            'missing_rates'    => $guard->toArray(),
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

        // ── Authoritative tariff guard ─────────────────────────────────────────
        // Re-resolve rates from the tariff (the posted line values are not trusted)
        // and block the save if any chargeable line has no usable rate.
        $guardError = $this->guardStorageRates($validated);
        if ($guardError) {
            return $guardError;
        }

        $invoiceCurrency = strtoupper($validated['invoice_currency'] ?? CurrencyService::defaultCurrency());
        // A foreign-currency invoice must carry a positive rate — never persist with
        // a silent 1.0 fallback, which would understate the base-currency ledger.
        if ($invoiceCurrency !== CurrencyService::defaultCurrency() && (float) ($validated['exchange_rate'] ?? 0) <= 0) {
            return back()->withInput()->with('error',
                "A valid {$invoiceCurrency} → " . CurrencyService::defaultCurrency() . ' exchange rate is required for a foreign-currency invoice.');
        }
        $exchangeRate    = (float) ($validated['exchange_rate'] ?? 1.0);
        $ssclPct         = (float) ($validated['sscl_percentage'] ?? 0);
        $vatPct          = (float) ($validated['vat_percentage'] ?? 0);
        $subtotal        = round(array_sum(array_column($validated['lines'], 'subtotal')), 2);
        $ssclAmount      = round(array_sum(array_column($validated['lines'], 'line_sscl')), 2);
        $vatAmount       = round(array_sum(array_column($validated['lines'], 'line_vat')), 2);
        $totalAmount     = round(array_sum(array_column($validated['lines'], 'line_total')), 2);
        $totalValue      = round(array_sum(array_column($validated['lines'], 'line_value')), 2) ?: $totalAmount;

        $invoice = null;

        DB::transaction(function () use ($validated, $invoiceCurrency, $exchangeRate, $ssclPct, $vatPct, $subtotal, $ssclAmount, $vatAmount, $totalAmount, $totalValue, &$invoice) {
            $invoiceNo = app(\App\Services\NumberSequenceService::class)->generate('storage_invoice');
            // Due date follows the debtor's AR payment terms (Net 30 default).
            $debtorTerms = \App\Models\Customer::where('id', $validated['customer_id'])->value('payment_terms') ?? 'net30';
            $dueDate     = \App\Services\Finance\PaymentTermsHelper::dueDate(
                $debtorTerms, \Carbon\Carbon::parse($validated['invoice_date'])
            )->toDateString();

            $invoice = StorageInvoice::create([
                'invoice_no'          => $invoiceNo,
                'invoice_type'        => $validated['invoice_type'] ?? 'invoice',
                'customer_id'         => $validated['customer_id'],
                'billing_party_id'    => $validated['billing_party_id'] ?? $validated['customer_id'],
                'invoice_date'        => $validated['invoice_date'],
                'due_date'            => $dueDate,
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
                    'tax_code_id'        => ($line['tax_code_id'] ?? null) ?: null,
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
            ->with('success', "Storage invoice {$invoice->invoice_no} saved successfully.");
    }

    /**
     * Re-resolve storage rates from the active tariff (posted line values are not
     * trusted) and return a redirect-back response if any chargeable line has no
     * usable rate, or null when everything resolves.
     */
    private function guardStorageRates(array $validated)
    {
        $header = StorageMasterHeader::with('details')
            ->where('customer_id', $validated['customer_id'])
            ->where('is_active', true)
            ->where('valid_from', '<=', $validated['period_to'])
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $validated['period_from']))
            ->latest('valid_from')
            ->first();

        $guard    = new TariffRateGuard();
        $fixUrl   = $header ? route('masters.storage-tariff.show', $header->id) : route('masters.storage-tariff.index');
        $fixLabel = $header ? 'Edit storage tariff' : 'Set up storage tariff';

        foreach ($validated['lines'] as $line) {
            if ((int) ($line['chargeable_days'] ?? 0) <= 0) {
                continue;
            }

            $eqtId  = ($line['equipment_type_id'] ?? null) ?: null;
            $cargo  = $line['cargo_status'] ?? null;
            $detail = $header
                ? $header->details->where('equipment_type_id', $eqtId)->where('cargo_status', $cargo)->first()
                : null;

            // Authoritative rate: from the tariff detail when a tariff exists; the
            // stored gate-in fallback (posted daily_rate) only applies with no tariff.
            $resolvedRate = $header
                ? (float) ($detail->storage_rate ?? 0)
                : (float) ($line['daily_rate'] ?? 0);

            $reason = TariffRateGuard::storageReason(true, $resolvedRate, (bool) $header, (bool) $detail);
            if ($reason) {
                $guard->flag('storage', $line['equipment_type'] ?? null, $cargo, $reason, $line['container_no'] ?? null, $fixUrl, $fixLabel);
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

    public function markIssued(StorageInvoice $invoice, \App\Services\Finance\CreditService $credit)
    {
        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be issued.');
        }

        $irdNo = $invoice->ird_invoice_no
            ?? app(IrdInvoiceNumberService::class)->generate('storage', $invoice->invoice_date);

        $invoice->update(['status' => 'issued', 'sent_at' => now(), 'ird_invoice_no' => $irdNo]);

        NotificationService::notifyAll(
            'Storage Invoice Issued — ' . $invoice->invoice_no,
            ($invoice->customer->name ?? 'Unknown') . ' · ' . $invoice->invoice_currency . ' ' . number_format($invoice->total_amount, 2),
            'success',
            route('billing.show', $invoice)
        );

        $redirect = back()->with('success', "Invoice {$invoice->invoice_no} marked as issued.");
        if ($invoice->customer && ($warning = $credit->arOverLimitWarning($invoice->customer))) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function markPaid(StorageInvoice $invoice)
    {
        if ($invoice->status !== 'issued') {
            return back()->with('error', 'Only issued invoices can be marked as paid.');
        }

        $invoice->update(['status' => 'paid']);

        NotificationService::notifyAll(
            'Storage Invoice Paid — ' . $invoice->invoice_no,
            ($invoice->customer->name ?? 'Unknown') . ' · ' . $invoice->invoice_currency . ' ' . number_format($invoice->total_amount, 2),
            'success',
            route('billing.show', $invoice)
        );

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

    // ── IRD Tax Invoice print ─────────────────────────────────────────────────

    public function irdPrint(StorageInvoice $invoice)
    {
        $invoice->load(['customer', 'details', 'createdBy']);
        $company = CompanySetting::current();

        $eqtCode = fn ($label) => trim(explode(' — ', $label ?? '')[0]) ?: '—';

        $lines = $invoice->details->map(fn ($d) => [
            'reference'       => $d->container_no,
            'description'     => 'CONTAINER STORAGE'
                                 . (($eqt = trim($eqtCode($d->equipment_type) . ' ' . strtoupper($d->cargo_status ?? ''))) ? ' — ' . $eqt : '')
                                 . ' | ' . \Carbon\Carbon::parse($d->from_date)->format('d M Y')
                                 . ' TO ' . \Carbon\Carbon::parse($d->to_date)->format('d M Y'),
            'quantity'        => $d->chargeable_days,
            'unit_price'      => $d->daily_rate,
            'amount_excl_vat' => $d->subtotal,
        ]);

        $from = $invoice->billing_period_from?->format('d M Y');
        $to   = $invoice->billing_period_to?->format('d M Y');

        $ssclRates = $invoice->details->map(fn ($d) => ($d->tax1_rate ?? 0) > 0 ? round((float) $d->tax1_rate, 4) : null)
            ->filter()->unique()->sort()->values();
        $vatRates  = $invoice->details->map(fn ($d) => ($d->tax2_rate ?? 0) > 0 ? round((float) $d->tax2_rate, 4) : null)
            ->filter()->unique()->sort()->values();

        $ssclLabel = $ssclRates->count() > 1
            ? $ssclRates->map(fn ($r) => number_format($r, 2) . '%')->implode(' / ')
            : null;
        $vatLabel  = $vatRates->count() > 1
            ? $vatRates->map(fn ($r) => number_format($r, 2) . '%')->implode(' / ')
            : null;

        $data = [
            'ird_invoice_no'        => $invoice->ird_invoice_no ?? '—',
            'invoice_date'          => $invoice->invoice_date,
            'company'               => $company,
            'verifyUrl'             => \Illuminate\Support\Facades\URL::signedRoute('documents.verify', ['type' => 'storage', 'id' => $invoice->id]),
            'customer'              => $invoice->customer,
            'lines'                 => $lines,
            'subtotal'              => $invoice->subtotal,
            'sscl_amount'           => $invoice->sscl_amount ?? 0,
            'sscl_percentage'       => (float) ($ssclRates->first() ?? $invoice->sscl_percentage ?? 0),
            'sscl_percentage_label' => $ssclLabel,
            'vat_amount'            => $invoice->vat_amount ?? 0,
            'vat_percentage'        => (float) ($vatRates->first() ?? $invoice->vat_percentage ?? 0),
            'vat_percentage_label'  => $vatLabel,
            'total_incl_vat'        => $invoice->total_amount,
            'invoice_currency'      => $invoice->invoice_currency,
            'exchange_rate'         => $invoice->exchange_rate,
            'invoice_no'            => $invoice->invoice_no,
            'category_info'         => array_filter([
                'Category'           => 'Container Storage',
                'Payment Due'        => $invoice->due_date?->format('d M Y'),
                'Billing Period'     => $from && $to ? "{$from} to {$to}" : null,
                'No. of Containers'  => $invoice->details
                    ->filter(fn ($d) => ($d->subtotal ?? 0) > 0)
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

    public function pdf(StorageInvoice $invoice)
    {
        $invoice->load(['customer', 'details', 'createdBy']);

        $pdf = Pdf::loadView('billing.pdf', compact('invoice'))
            ->setPaper('a4', 'portrait')
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

        $invoice->loadMissing(['customer', 'billingParty', 'details', 'createdBy']);

        // Mark as issued if still draft (assign ird_invoice_no same as markIssued)
        if ($invoice->isDraft()) {
            $irdNo = $invoice->ird_invoice_no
                ?? app(\App\Services\Finance\IrdInvoiceNumberService::class)->generate('storage', $invoice->invoice_date);
            $invoice->update(['status' => 'issued', 'sent_at' => now(), 'ird_invoice_no' => $irdNo]);
        }

        $manualCc = $validated['cc_email'] ? [$validated['cc_email']] : [];

        SendInvoiceEmailJob::dispatchSync(
            $invoice,
            $validated['to_email'],
            $validated['message'] ?? null,
            $manualCc,
        );

        return back()->with('success', "Invoice {$invoice->invoice_no} sent to {$validated['to_email']}.");
    }
}
