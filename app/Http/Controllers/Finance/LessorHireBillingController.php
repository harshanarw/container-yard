<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\ChargeCode;
use App\Models\Customer;
use App\Models\LessorOnHire;
use App\Models\SupplierInvoice;
use App\Services\Billing\HirePriorBilling;
use App\Services\Billing\HireTierPricing;
use App\Services\Billing\LessorHireBilling;
use App\Services\Billing\ManualPricing;
use App\Services\NumberSequenceService;
use App\Services\Finance\PaymentTermsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What the yard owes its shipping lines for containers it holds on hire.
 *
 * The AP side of the rental trade, and the half that has been missing: a lease
 * has carried its own job since it was written, and `JobPnlService` accrues the
 * per-diem as WIP cost, but nothing ever turned that into a bill. A completed
 * lease produced no payable at all.
 *
 * Shaped like the reefer periodic billing screen — pick a period, see what is
 * owed on every open agreement, tick what to raise — because it is the same
 * problem in the opposite direction and an operator should not have to learn it
 * twice.
 *
 * One draft supplier invoice **per shipping line**, not per container. That is
 * how the line bills: one statement covering every box the yard has on hire
 * from them, which is the document this has to be reconciled against.
 */
class LessorHireBillingController extends Controller
{
    /** The charge code every lease-in line is raised under. */
    private const CHARGE_CODE = 'LHIRE';

    public function __construct()
    {
        $this->middleware('can:finance.ap.view')->only('index');
        $this->middleware('can:finance.ap.create')->only('store');
    }

    public function index(Request $request)
    {
        [$from, $to, $lessorId] = $this->period($request);

        // Nothing runs until a period is asked for: the preview prices every
        // open lease against its whole billed history, which is not work to do
        // on a bare page load.
        $ran  = $request->filled('from') || $request->filled('to');
        $rows = $ran ? LessorHireBilling::previewForPeriod($from, $to, $lessorId) : [];

        $lessors = Customer::selectable()->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        $summary = [
            'leases'   => count($rows),
            'billable' => collect($rows)->where('billable', true)->count(),
            'days'     => collect($rows)->sum('days'),
            'amount'   => round(collect($rows)->sum('amount'), 2),
        ];

        $chargeCode = ChargeCode::where('code', self::CHARGE_CODE)->first();

        return view('finance.ap.lessor-hire-billing', compact(
            'rows', 'from', 'to', 'lessorId', 'lessors', 'summary', 'ran', 'chargeCode',
        ));
    }

    /**
     * Raise the drafts.
     *
     * Draft, never posted: a supplier invoice is the yard agreeing it owes
     * money, and that is a person's decision. The existing approve/post flow on
     * the AP module then applies unchanged.
     */
    public function store(Request $request)
    {
        [$from, $to] = $this->period($request);

        $validated = $request->validate([
            'lease_ids'   => ['required', 'array', 'min:1'],
            'lease_ids.*' => ['integer', 'exists:lessor_on_hires,id'],
            'invoice_date' => ['nullable', 'date'],
        ]);

        $invoiceDate = $validated['invoice_date'] ?? $to;

        $leases = LessorOnHire::with(['container', 'lessor', 'yardJob', 'rateTiers'])
            ->whereIn('id', $validated['lease_ids'])
            ->get();

        if ($leases->isEmpty()) {
            return back()->with('error', 'No leases were selected.');
        }

        // Re-priced here rather than trusted from the form. A preview can be
        // minutes old, and in between someone may have raised the same period
        // on another screen — the amount posted has to be the amount owed at
        // the moment of posting, not the amount on a stale page.
        $prior    = HirePriorBilling::for($leases->pluck('id')->all());
        $previews = [];

        foreach ($leases as $lease) {
            $preview = LessorHireBilling::preview($lease, $from, $to, $prior);

            if ($preview['billable']) {
                $previews[] = $preview;
            }
        }

        if (! $previews) {
            return back()->with('error',
                'Nothing is billable for this period — the selected leases have already been '
                . 'invoiced for it, or they have no rate tiers.');
        }

        $charge  = ChargeCode::where('code', self::CHARGE_CODE)->first();
        $taxCode = $charge?->taxCode;
        $expense = $charge ? AccountMapping::where('mapping_type', 'charge_expense')
            ->where('source_type', ChargeCode::class)
            ->where('source_id', $charge->id)
            ->where('is_active', true)
            ->value('account_id') : null;

        if (! $expense) {
            return back()->with('error',
                'The ' . self::CHARGE_CODE . ' charge code has no expense account mapped, so the '
                . 'invoice could not be costed. Map one under Finance → Account Mappings first.');
        }

        // One invoice per lessor: that is how the line bills, and it is the
        // document this gets reconciled against.
        $created = [];

        foreach (collect($previews)->groupBy(fn ($p) => $p['lease']->lessor_id) as $lessorId => $group) {
            // `values()` because groupBy keeps the original keys, and raise()
            // reads the first preview by position for the currency and dates.
            $created[] = $this->raise((int) $lessorId, $group->values()->all(), $invoiceDate, $charge, $taxCode, $expense);
        }

        $count = count($created);

        return redirect()->route('finance.ap.invoices.index')->with(
            'success',
            $count === 1
                ? "Draft supplier invoice {$created[0]->invoice_no} raised."
                : "{$count} draft supplier invoices raised.",
        );
    }

    /** One lessor's draft, with a line per container on hire from them. */
    private function raise(
        int $lessorId,
        array $previews,
        string $invoiceDate,
        ?ChargeCode $charge,
        $taxCode,
        int $expenseAccountId,
    ): SupplierInvoice {
        return DB::transaction(function () use ($lessorId, $previews, $invoiceDate, $charge, $taxCode, $expenseAccountId) {
            $t1 = (float) ($taxCode?->tax1_rate ?? 0);
            $t2 = (float) ($taxCode?->tax2_rate ?? 0);

            $subtotal = 0.0;
            $sscl     = 0.0;
            $vat      = 0.0;
            $lines    = [];

            foreach ($previews as $p) {
                $lease = $p['lease'];
                $net   = (float) $p['amount'];

                $tax = ManualPricing::taxOn($net, $t1, $t2);

                $subtotal += $net;
                $sscl     += $tax['sscl'];
                $vat      += $tax['vat'];

                $lines[] = [
                    'description'        => $this->describe($p),
                    'yard_job_id'        => $lease->yard_job_id,
                    'container_id'       => $lease->container_id,
                    'lessor_on_hire_id'  => $lease->id,
                    'billed_from'        => $p['from'],
                    'billed_to'          => $p['to'],
                    'is_interim'         => $p['is_interim'],
                    'charge_code_id'     => $charge?->id,
                    'tax_code_id'        => $taxCode?->id,
                    'expense_account_id' => $expenseAccountId,
                    'amount'             => round($net, 2),
                    'tax1_rate'          => $t1,
                    'tax2_rate'          => $t2,
                    'tax1_amount'        => $tax['sscl'],
                    'tax2_amount'        => $tax['vat'],
                    'gross_amount'       => $tax['gross'],
                ];
            }

            $contact = Customer::find($lessorId);

            $invoice = SupplierInvoice::create([
                'invoice_no'    => app(NumberSequenceService::class)->generate('supplier_invoice'),
                'customer_id'   => $lessorId,
                'invoice_date'  => $invoiceDate,
                'due_date'      => $contact?->ap_payment_terms
                    ? PaymentTermsHelper::dueDate($contact->ap_payment_terms, Carbon::parse($invoiceDate))->toDateString()
                    : null,
                'currency'      => $previews[0]['lease']->hireCurrency(),
                'exchange_rate' => 1,
                'subtotal'      => round($subtotal, 2),
                'sscl_amount'   => round($sscl, 2),
                'vat_amount'    => round($vat, 2),
                'tax_amount'    => round($sscl + $vat, 2),
                'total_amount'  => round($subtotal + $sscl + $vat, 2),
                'status'        => 'draft',
                'notes'         => 'Container lease / on-hire charges for '
                    . Carbon::parse($previews[0]['from'])->format('d M Y') . ' to '
                    . Carbon::parse($previews[0]['to'])->format('d M Y') . '.',
                'created_by'    => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $invoice->lines()->create($line);
            }

            return $invoice;
        });
    }

    /**
     * The line text.
     *
     * Names the container, the days and which rates applied — "1 month + 7
     * days" rather than "37 days", because the question when checking a bill
     * against the line's statement is which rates were used, and a day count
     * alone does not answer it.
     */
    private function describe(array $p): string
    {
        $lease = $p['lease'];

        return sprintf(
            '%s on hire %s to %s — %s (%d day%s)%s',
            $lease->container?->container_no ?? 'Container',
            Carbon::parse($p['from'])->format('d M Y'),
            Carbon::parse($p['to'])->format('d M Y'),
            HireTierPricing::describe($p['pricing']),
            $p['days'],
            $p['days'] === 1 ? '' : 's',
            $p['is_interim'] ? ' — interim' : '',
        );
    }

    /**
     * The period, defaulting to last month.
     *
     * Last rather than this: a rental bill arrives after the month it covers,
     * and billing a month that has not finished is the exception.
     *
     * @return array{0:string,1:string,2:?int}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from'      => ['nullable', 'date'],
            'to'        => ['nullable', 'date', 'after_or_equal:from'],
            'lessor_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        $default = now()->subMonthNoOverflow();

        return [
            $validated['from'] ?? $default->copy()->startOfMonth()->toDateString(),
            $validated['to']   ?? $default->copy()->endOfMonth()->toDateString(),
            isset($validated['lessor_id']) ? (int) $validated['lessor_id'] : null,
        ];
    }
}
