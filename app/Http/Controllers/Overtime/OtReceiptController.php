<?php

namespace App\Http\Controllers\Overtime;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\OtReceipt;
use App\Services\Overtime\OtReceiptService;
use App\Services\Overtime\OvertimeRuleResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Overtime Receipt module: generate a per-BL OT receipt for a chosen service
 * window (A/B), collect payment (posts to the GL), and print it. Also serves the
 * gate-in receipt lookup.
 */
class OtReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ot.receipt.view')->only(['index', 'show', 'rules']);
        $this->middleware('can:ot.receipt.generate')->only(['create', 'store', 'confirm']);
        $this->middleware('can:ot.receipt.cancel')->only(['cancel']);
        $this->middleware('can:ot.receipt.pdf')->only(['pdf']);
        $this->middleware('can:gatein.ot.select')->only(['lookup']);
    }

    public function index(Request $request)
    {
        $query = OtReceipt::with('customer', 'rule');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('receipt_no', 'like', "%{$s}%")
                ->orWhere('bl_number', 'like', "%{$s}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")));
        }

        return view('overtime.receipts.index', [
            'receipts' => $query->orderByDesc('id')->paginate(20)->withQueryString(),
            'statuses' => ['generated', 'paid', 'partially_used', 'fully_used', 'cancelled', 'void'],
        ]);
    }

    public function create()
    {
        return view('overtime.receipts.create', [
            'customers'    => Customer::orderBy('name')->get(['id', 'name', 'code']),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('bank_name')->get(),
        ]);
    }

    /** AJAX: applicable tariff rules (A/B) + rate + validity window for a date. */
    public function rules(Request $request, OvertimeRuleResolver $resolver)
    {
        $v = $request->validate([
            'operational_date' => 'required|date',
            'gate_in_time'     => 'nullable|date_format:H:i',
        ]);

        $date = Carbon::parse($v['operational_date'])->startOfDay();

        $rules = $resolver->getApplicableRules($date)->map(function ($rule) use ($resolver, $date) {
            $win = $resolver->buildValidityWindow($rule, $date);

            return [
                'id'          => $rule->id,
                'code'        => $rule->rule_code,
                'label'       => $rule->display_name,
                'period'      => strtoupper($rule->period_code),
                'rate'        => (float) $rule->rate_amount,
                'currency'    => $rule->currency,
                'valid_from'  => $win['from']->format('Y-m-d H:i'),
                'valid_to'    => $win['to']->format('Y-m-d H:i'),
            ];
        })->values();

        $summary = isset($v['gate_in_time'])
            ? $resolver->resolve(Carbon::parse($v['operational_date'] . ' ' . $v['gate_in_time']))
            : null;

        return response()->json([
            'day_category' => $resolver->resolveDayCategory($date),
            'rules'        => $rules,
            'unconfigured' => $summary['unconfigured'] ?? false,
            'within_normal'=> $summary['within_normal'] ?? null,
        ]);
    }

    public function store(Request $request, OtReceiptService $service)
    {
        $v = $request->validate([
            'bl_number'                => 'required|string|max:50',
            'customer_id'              => 'required|exists:customers,id',
            'operational_date'         => 'required|date',
            'tariff_rule_id'           => 'required|exists:ot_tariff_rules,id',
            'expected_container_count' => 'required|integer|min:1|max:999',
            'remarks'                  => 'nullable|string|max:500',
        ]);

        $receipt = $service->generate($v);

        return redirect()->route('overtime.receipts.show', $receipt)
            ->with('success', "Overtime receipt {$receipt->receipt_no} generated. Confirm payment to activate it.");
    }

    public function show(OtReceipt $otReceipt)
    {
        $otReceipt->load('customer', 'rule.version', 'bankAccount', 'extensionOf', 'journal');

        return view('overtime.receipts.show', [
            'receipt'      => $otReceipt,
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('bank_name')->get(),
        ]);
    }

    public function confirm(Request $request, OtReceipt $otReceipt, OtReceiptService $service)
    {
        $v = $request->validate([
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'payment_method'  => 'required|in:cash,bank,cheque,online',
        ]);

        try {
            $service->confirm($otReceipt, $v['bank_account_id'] ?? null, $v['payment_method']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not post the receipt: ' . $e->getMessage());
        }

        return redirect()->route('overtime.receipts.show', $otReceipt)
            ->with('success', 'Payment confirmed and posted to the ledger.');
    }

    public function cancel(Request $request, OtReceipt $otReceipt, OtReceiptService $service)
    {
        $v = $request->validate(['reason' => 'required|string|max:255']);

        if (! in_array($otReceipt->status, ['generated'], true)) {
            return back()->with('error', 'Only a generated (unpaid) receipt can be cancelled.');
        }

        $service->cancel($otReceipt, $v['reason']);

        return redirect()->route('overtime.receipts.show', $otReceipt)->with('success', 'Receipt cancelled.');
    }

    public function pdf(OtReceipt $otReceipt)
    {
        $otReceipt->load('customer', 'rule');

        $verifyUrl = \Illuminate\Support\Facades\URL::signedRoute('documents.verify', ['type' => 'ot-receipt', 'id' => $otReceipt->id]);
        $qr = \App\Support\Qr::svgDataUri($verifyUrl, 100);

        $pdf = Pdf::loadView('overtime.receipts.pdf', [
            'receipt' => $otReceipt,
            'company' => \App\Models\CompanySetting::current(),
            'qr'      => $qr,
        ])->setPaper('a5', 'portrait')
          ->set_option('defaultFont', 'sans-serif')
          ->set_option('isHtml5ParserEnabled', true)
          ->set_option('isRemoteEnabled', false);

        return $pdf->stream("OT-Receipt-{$otReceipt->receipt_no}.pdf");
    }

    /** AJAX (gate-in): usable receipts for a BL at a datetime. */
    public function lookup(Request $request)
    {
        $v = $request->validate([
            'bl_number'   => 'required|string|max:50',
            'gate_in_at'  => 'required|date',
        ]);

        $at = Carbon::parse($v['gate_in_at']);

        $receipts = OtReceipt::with('rule')
            ->forBl($v['bl_number'])
            ->whereIn('status', ['paid', 'partially_used'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (OtReceipt $r) => $r->isUsable($at))
            ->map(fn (OtReceipt $r) => [
                'id'         => $r->id,
                'receipt_no' => $r->receipt_no,
                'rule'       => $r->rule->display_name ?? '',
                'valid_to'   => $r->valid_to->format('Y-m-d H:i'),
                'remaining'  => $r->remainingCount(),
            ])->values();

        return response()->json(['receipts' => $receipts]);
    }
}
