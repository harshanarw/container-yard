<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ChargeCode;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\GeneralInvoice;
use App\Models\TaxCode;
use App\Services\CurrencyService;
use App\Services\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GeneralInvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:billing.general.view')->only(['index', 'show', 'chargeCodeInfo', 'currencyRate']);
        $this->middleware('can:billing.general.pdf')->only(['pdf']);
        $this->middleware('can:billing.general.create')->only(['create', 'store']);
        $this->middleware('can:billing.general.edit')->only(['edit', 'update']);
        $this->middleware('can:billing.general.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $invoices = GeneralInvoice::with(['customer', 'billingParty'])
            ->when($request->search, fn ($q, $v) =>
                $q->where('invoice_no', 'like', "%{$v}%")
                  ->orWhere('ird_invoice_no', 'like', "%{$v}%")
                  ->orWhere('reference', 'like', "%{$v}%"))
            ->when($request->type,     fn ($q, $v) => $q->where('invoice_type', $v))
            ->when($request->status,   fn ($q, $v) => $q->where('status', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('billing.general.index', compact('invoices', 'customers'));
    }

    public function create()
    {
        return view('billing.general.edit', $this->formData(new GeneralInvoice([
            'invoice_type'   => 'tax_invoice',
            'currency'       => CurrencyService::defaultCurrency(),
            'exchange_rate'  => 1,
            'tax_applicable' => true,
            'invoice_date'   => now()->toDateString(),
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validateInvoice($request);

        $invoice = DB::transaction(function () use ($data, $request) {
            $totals = $this->computeTotals($data['lines'], (float) $data['exchange_rate'], $data['tax_applicable']);

            $invoice = GeneralInvoice::create($this->headerAttributes($data, $totals) + [
                'invoice_no' => $this->generateNumber($data['invoice_type']),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);

            $this->syncLines($invoice, $totals['lines']);

            return $invoice;
        });

        return redirect()->route('billing.general.show', $invoice)
            ->with('success', "General invoice {$invoice->invoice_no} created.");
    }

    public function show(GeneralInvoice $general)
    {
        $general->load([
            'customer', 'billingParty', 'createdBy', 'issuedBy',
            'lines.chargeCode', 'lines.taxCode',
        ]);

        return view('billing.general.show', ['invoice' => $general]);
    }

    public function pdf(GeneralInvoice $general)
    {
        $general->load(['customer', 'billingParty', 'lines.chargeCode', 'lines.taxCode', 'createdBy']);

        return view('billing.general.pdf', [
            'invoice'  => $general,
            'settings' => \App\Models\CompanySetting::current(),
            'base'     => CurrencyService::defaultCurrency(),
        ]);
    }

    public function edit(GeneralInvoice $general)
    {
        if (! in_array($general->status, ['draft'], true)) {
            return back()->with('error', 'Only draft general invoices can be edited.');
        }

        $general->load('lines.chargeCode', 'lines.taxCode');

        return view('billing.general.edit', $this->formData($general));
    }

    public function update(Request $request, GeneralInvoice $general)
    {
        if (! in_array($general->status, ['draft'], true)) {
            return back()->with('error', 'Only draft general invoices can be edited.');
        }

        $data = $this->validateInvoice($request);

        DB::transaction(function () use ($general, $data) {
            $totals = $this->computeTotals($data['lines'], (float) $data['exchange_rate'], $data['tax_applicable']);

            $general->update($this->headerAttributes($data, $totals));
            $general->lines()->delete();
            $this->syncLines($general, $totals['lines']);
        });

        return redirect()->route('billing.general.show', $general)
            ->with('success', "General invoice {$general->invoice_no} updated.");
    }

    public function destroy(GeneralInvoice $general)
    {
        if (! in_array($general->status, ['draft'], true)) {
            return back()->with('error', 'Only draft general invoices can be deleted.');
        }

        $general->delete();   // lines cascade

        return redirect()->route('billing.general.index')
            ->with('success', 'General invoice deleted.');
    }

    // ── AJAX ─────────────────────────────────────────────────────────────────

    /** Charge code → default tax code + mapped revenue account (for the picker). */
    public function chargeCodeInfo(Request $request)
    {
        $cc = ChargeCode::with('taxCode')->find($request->integer('charge_code_id'));
        if (! $cc) {
            return response()->json(['found' => false]);
        }

        $acc = $this->revenueAccount($cc->id);

        return response()->json([
            'found'          => true,
            'tax_code_id'    => $cc->tax_code_id,
            'tax1_rate'      => (float) ($cc->taxCode?->tax1_rate ?? 0),
            'tax2_rate'      => (float) ($cc->taxCode?->tax2_rate ?? 0),
            'account_code'   => $acc?->code,
            'account_name'   => $acc?->name,
        ]);
    }

    /** Cross rate FROM a line currency INTO the invoice currency (via base). */
    public function currencyRate(Request $request)
    {
        $line    = strtoupper((string) $request->get('line_currency'));
        $invoice = strtoupper((string) $request->get('invoice_currency'));
        $date    = $request->get('date');

        if ($line === '' || $invoice === '' || $line === $invoice) {
            return response()->json(['rate' => 1.0, 'found' => true]);
        }

        $lineToBase    = CurrencyService::rateFor($line, $date)['rate'];
        $invoiceToBase = CurrencyService::rateFor($invoice, $date)['rate'];

        if (! $lineToBase || ! $invoiceToBase || $invoiceToBase <= 0) {
            return response()->json(['rate' => null, 'found' => false]);
        }

        // line→invoice = (line→base) / (invoice→base)
        return response()->json([
            'rate'  => round($lineToBase / $invoiceToBase, 6),
            'found' => true,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formData(GeneralInvoice $invoice): array
    {
        $chargeCodes = ChargeCode::with('taxCode')
            ->where('is_active', true)
            ->orderBy('category')->orderBy('sort_order')->orderBy('code')
            ->get();

        // Pre-resolve each charge code's revenue account for display in the picker.
        $revenueAccounts = $this->revenueAccountMap($chargeCodes->pluck('id')->all());

        return [
            'invoice'         => $invoice,
            'customers'       => Customer::where('status', 'active')->orderBy('name')->get(),
            'chargeCodes'     => $chargeCodes,
            'taxCodes'        => TaxCode::where('is_active', true)->orderBy('sort_order')->get(),
            'currencies'      => CurrencyService::activeCurrencyNames(),
            'revenueAccounts' => $revenueAccounts,
            'baseCurrency'    => CurrencyService::defaultCurrency(),
        ];
    }

    private function validateInvoice(Request $request): array
    {
        $data = $request->validate([
            'invoice_type'     => ['required', Rule::in(array_keys(GeneralInvoice::TYPES))],
            'category'         => ['nullable', Rule::in(array_keys(GeneralInvoice::CATEGORIES))],
            'customer_id'      => ['required', 'exists:customers,id'],
            'billing_party_id' => ['nullable', 'exists:customers,id'],
            'invoice_date'     => ['required', 'date'],
            'due_date'         => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency'         => ['required', 'string', 'size:3'],
            'exchange_rate'    => ['required', 'numeric', 'min:0.000001'],
            'tax_applicable'   => ['nullable', 'boolean'],
            'reference'        => ['nullable', 'string', 'max:100'],
            'remarks'          => ['nullable', 'string', 'max:1000'],

            'lines'                     => ['required', 'array', 'min:1'],
            'lines.*.charge_code_id'    => ['required', 'exists:charge_codes,id'],
            'lines.*.description'       => ['required', 'string', 'max:255'],
            'lines.*.qty'               => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_rate'         => ['required', 'numeric', 'min:0'],
            'lines.*.line_currency'     => ['required', 'string', 'size:3'],
            'lines.*.line_exchange_rate'=> ['required', 'numeric', 'min:0.000001'],
            'lines.*.tax_code_id'       => ['nullable', 'exists:tax_codes,id'],
        ]);

        $data['currency']       = strtoupper($data['currency']);
        $data['tax_applicable'] = $request->boolean('tax_applicable');

        // Foreign document currency needs a real rate (mirrors the estimate guard).
        $base = CurrencyService::defaultCurrency();
        if ($data['currency'] !== $base && (float) $data['exchange_rate'] <= 1.0 && abs((float) $data['exchange_rate'] - 1.0) < 1e-7) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'exchange_rate' => "A {$data['currency']} invoice needs a real {$data['currency']} → {$base} exchange rate (1.0 means no conversion).",
            ]);
        }

        return $data;
    }

    /** Compute per-line and header totals in the invoice currency (+ base value). */
    private function computeTotals(array $lines, float $invoiceRate, bool $taxApplicable): array
    {
        $taxCodes = TaxCode::whereIn('id', collect($lines)->pluck('tax_code_id')->filter()->unique())
            ->get()->keyBy('id');

        $subtotal = 0; $ssclTotal = 0; $vatTotal = 0;
        $out = [];

        foreach ($lines as $i => $line) {
            $qty    = (float) ($line['qty'] ?? 0);
            $rate   = (float) ($line['unit_rate'] ?? 0);
            $lineFx = (float) ($line['line_exchange_rate'] ?? 1) ?: 1.0;   // line → invoice

            $native   = round($qty * $rate, 2);                 // in line currency
            $netInv   = round($native * $lineFx, 2);            // in invoice currency

            $tc = $taxCodes[$line['tax_code_id'] ?? 0] ?? null;
            $t1Rate = $taxApplicable ? (float) ($tc?->tax1_rate ?? 0) : 0.0;
            $t2Rate = $taxApplicable ? (float) ($tc?->tax2_rate ?? 0) : 0.0;
            $t1Amt  = round($netInv * $t1Rate / 100, 2);
            $t2Amt  = round(($netInv + $t1Amt) * $t2Rate / 100, 2);
            $gross  = round($netInv + $t1Amt + $t2Amt, 2);

            $subtotal  += $netInv;
            $ssclTotal += $t1Amt;
            $vatTotal  += $t2Amt;

            $out[] = [
                'charge_code_id'     => $line['charge_code_id'] ?? null,
                'tax_code_id'        => $line['tax_code_id'] ?? null,
                'description'        => $line['description'] ?? '',
                'qty'                => $qty,
                'unit_rate'          => $rate,
                'line_currency'      => strtoupper((string) ($line['line_currency'] ?? 'LKR')),
                'line_exchange_rate' => $lineFx,
                'native_amount'      => $native,
                'line_amount'        => $netInv,
                'tax1_rate'          => $t1Rate,
                'tax2_rate'          => $t2Rate,
                'tax1_amount'        => $t1Amt,
                'tax2_amount'        => $t2Amt,
                'gross_amount'       => $gross,
                'base_value'         => round($gross * $invoiceRate, 2),
                'sort_order'         => $i,
            ];
        }

        $subtotal  = round($subtotal, 2);
        $ssclTotal = round($ssclTotal, 2);
        $vatTotal  = round($vatTotal, 2);
        $taxAmount = round($ssclTotal + $vatTotal, 2);

        return [
            'subtotal'    => $subtotal,
            'sscl_total'  => $ssclTotal,
            'vat_total'   => $vatTotal,
            'tax_amount'  => $taxAmount,
            'grand_total' => round($subtotal + $taxAmount, 2),
            'tax_pct'     => $subtotal > 0 ? round($taxAmount / $subtotal * 100, 4) : 0,
            'lines'       => $out,
        ];
    }

    private function headerAttributes(array $data, array $totals): array
    {
        return [
            'invoice_type'    => $data['invoice_type'],
            'category'        => $data['category'] ?? null,
            'customer_id'     => $data['customer_id'],
            'billing_party_id'=> $data['billing_party_id'] ?: null,
            'invoice_date'    => $data['invoice_date'],
            'due_date'        => $data['due_date'] ?? null,
            'currency'        => $data['currency'],
            'exchange_rate'   => $data['currency'] === CurrencyService::defaultCurrency() ? 1.0 : (float) $data['exchange_rate'],
            'tax_applicable'  => $data['tax_applicable'],
            'reference'       => $data['reference'] ?? null,
            'remarks'         => $data['remarks'] ?? null,
            'subtotal'        => $totals['subtotal'],
            'sscl_total'      => $totals['sscl_total'],
            'vat_total'       => $totals['vat_total'],
            'tax_percentage'  => $totals['tax_pct'],
            'tax_amount'      => $totals['tax_amount'],
            'grand_total'     => $totals['grand_total'],
            'balance_due'     => $totals['grand_total'],
        ];
    }

    private function syncLines(GeneralInvoice $invoice, array $lines): void
    {
        foreach ($lines as $line) {
            $invoice->lines()->create($line);
        }
    }

    private function generateNumber(string $type): string
    {
        $module = $type === 'debit_note' ? 'general_debit_note' : 'general_invoice';
        return app(NumberSequenceService::class)->generate($module);
    }

    /** Revenue account mapped to a charge code (charge_revenue), or null. */
    private function revenueAccount(int $chargeCodeId): ?Account
    {
        $mapping = AccountMapping::where('mapping_type', 'charge_revenue')
            ->where('source_type', ChargeCode::class)
            ->where('source_id', $chargeCodeId)
            ->where('is_active', true)
            ->first();

        return $mapping?->account;
    }

    /** @return array<int, array{code:string,name:string}> keyed by charge_code_id */
    private function revenueAccountMap(array $chargeCodeIds): array
    {
        return AccountMapping::with('account')
            ->where('mapping_type', 'charge_revenue')
            ->where('source_type', ChargeCode::class)
            ->whereIn('source_id', $chargeCodeIds)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn ($m) => [$m->source_id => [
                'code' => $m->account?->code,
                'name' => $m->account?->name,
            ]])
            ->all();
    }
}
