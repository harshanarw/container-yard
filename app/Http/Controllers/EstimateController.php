<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstimateRequest;
use App\Http\Requests\UpdateEstimateRequest;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EstimateController extends Controller
{
    public function index(Request $request)
    {
        $estimates = Estimate::with(['container', 'customer', 'createdBy'])
            ->when($request->search, fn ($q, $s) =>
                $q->where('estimate_no', 'like', "%{$s}%")
                  ->orWhere('container_no', 'like', "%{$s}%")
            )
            ->when($request->status && $request->status !== 'all', fn ($q, $v) => $q->where('status', $v))
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($request->date_from,   fn ($q, $v) => $q->whereDate('estimate_date', '>=', $v))
            ->when($request->date_to,     fn ($q, $v) => $q->whereDate('estimate_date', '<=', $v))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('estimates.index', compact('estimates', 'customers'));
    }

    public function create(Request $request)
    {
        $customers  = Customer::where('status', 'active')->orderBy('name')->get();
        $containers = Container::whereIn('status', ['in_yard', 'in_repair'])
            ->with('customer')
            ->orderBy('container_no')
            ->get();

        $selectedInquiry   = $request->inquiry_id
            ? Inquiry::with(['container', 'customer', 'damages'])->find($request->inquiry_id)
            : null;

        $selectedContainer = $request->container_id
            ? Container::with('customer')->find($request->container_id)
            : null;

        return view('estimates.create', compact(
            'customers', 'containers', 'selectedInquiry', 'selectedContainer'
        ));
    }

    public function store(StoreEstimateRequest $request)
    {
        $container = Container::findOrFail($request->container_id);

        $totals = $this->calculateTotals(
            $request->line_items,
            (float) $request->tax_percentage
        );

        $estimate = Estimate::create([
            'estimate_no'    => $this->generateEstimateNo(),
            'inquiry_id'     => $request->inquiry_id,
            'container_id'   => $container->id,
            'container_no'   => $container->container_no,
            'customer_id'    => $request->customer_id,
            'size'           => $container->size,
            'type_code'      => $container->type_code,
            'estimate_date'  => $request->estimate_date,
            'valid_until'    => $request->valid_until,
            'currency'       => $request->currency,
            'priority'       => $request->priority,
            'status'         => 'draft',
            'scope_of_work'  => $request->scope_of_work,
            'terms'          => $request->terms,
            'tax_percentage' => $request->tax_percentage ?? 0,
            'subtotal'       => $totals['subtotal'],
            'tax_amount'     => $totals['tax_amount'],
            'grand_total'    => $totals['grand_total'],
            'send_to_email'  => $request->send_to_email,
            'send_cc_email'  => $request->send_cc_email,
            'email_message'  => $request->email_message,
            'attach_pdf'     => $request->boolean('attach_pdf'),
            'attach_photos'  => $request->boolean('attach_photos'),
            'created_by'     => auth()->id(),
        ]);

        foreach ($request->line_items as $item) {
            $lineAmount = $item['qty'] * $item['unit_price'];
            $estimate->lineItems()->create([
                'component'      => $item['component'],
                'repair_type'    => $item['repair_type'],
                'qty'            => $item['qty'],
                'unit_price'     => $item['unit_price'],
                'tax_percentage' => $item['tax_percentage'] ?? 0,
                'line_amount'    => $lineAmount,
            ]);
        }

        // Link inquiry status
        if ($request->inquiry_id) {
            Inquiry::where('id', $request->inquiry_id)
                ->update(['status' => 'estimate_sent']);
        }

        return redirect()->route('estimates.show', $estimate)
            ->with('success', "Estimate {$estimate->estimate_no} created successfully.");
    }

    public function show(Estimate $estimate)
    {
        $estimate->load(['container', 'customer', 'inquiry', 'lineItems', 'createdBy', 'approvedBy']);

        return view('estimates.show', compact('estimate'));
    }

    public function edit(Estimate $estimate)
    {
        if (in_array($estimate->status, ['approved', 'completed'])) {
            return back()->with('error', 'Approved or completed estimates cannot be edited.');
        }

        $customers  = Customer::where('status', 'active')->orderBy('name')->get();
        $containers = Container::whereIn('status', ['in_yard', 'in_repair'])
            ->with('customer')->orderBy('container_no')->get();

        $estimate->load('lineItems');

        return view('estimates.edit', compact('estimate', 'customers', 'containers'));
    }

    public function update(UpdateEstimateRequest $request, Estimate $estimate)
    {
        if (in_array($estimate->status, ['approved', 'completed'])) {
            return back()->with('error', 'Approved or completed estimates cannot be edited.');
        }

        $totals = $this->calculateTotals(
            $request->line_items,
            (float) $request->tax_percentage
        );

        $estimate->update([
            'estimate_date'  => $request->estimate_date,
            'valid_until'    => $request->valid_until,
            'currency'       => $request->currency,
            'priority'       => $request->priority,
            'scope_of_work'  => $request->scope_of_work,
            'terms'          => $request->terms,
            'tax_percentage' => $request->tax_percentage ?? 0,
            'subtotal'       => $totals['subtotal'],
            'tax_amount'     => $totals['tax_amount'],
            'grand_total'    => $totals['grand_total'],
            'send_to_email'  => $request->send_to_email,
            'send_cc_email'  => $request->send_cc_email,
            'email_message'  => $request->email_message,
            'attach_pdf'     => $request->boolean('attach_pdf'),
            'attach_photos'  => $request->boolean('attach_photos'),
        ]);

        $estimate->lineItems()->delete();
        foreach ($request->line_items as $item) {
            $estimate->lineItems()->create([
                'component'      => $item['component'],
                'repair_type'    => $item['repair_type'],
                'qty'            => $item['qty'],
                'unit_price'     => $item['unit_price'],
                'tax_percentage' => $item['tax_percentage'] ?? 0,
                'line_amount'    => $item['qty'] * $item['unit_price'],
            ]);
        }

        return redirect()->route('estimates.show', $estimate)
            ->with('success', 'Estimate updated successfully.');
    }

    public function destroy(Estimate $estimate)
    {
        if ($estimate->status === 'approved') {
            return back()->with('error', 'Approved estimates cannot be deleted.');
        }

        $estimate->lineItems()->delete();
        $estimate->delete();

        return redirect()->route('estimates.index')
            ->with('success', 'Estimate deleted successfully.');
    }

    public function send(Request $request, Estimate $estimate)
    {
        $request->validate([
            'send_to_email' => ['required', 'email'],
            'send_cc_email' => ['nullable', 'email'],
            'email_message' => ['nullable', 'string'],
        ]);

        $estimate->update([
            'status'        => 'sent',
            'sent_at'       => now(),
            'send_to_email' => $request->send_to_email,
            'send_cc_email' => $request->send_cc_email,
            'email_message' => $request->email_message,
        ]);

        // TODO: dispatch SendEstimateEmail job

        return back()->with('success', "Estimate sent to {$request->send_to_email}.");
    }

    public function approve(Request $request, Estimate $estimate)
    {
        $estimate->update([
            'status'        => 'approved',
            'approved_by'   => auth()->id(),
            'approved_date' => now(),
        ]);

        return back()->with('success', 'Estimate approved successfully.');
    }

    public function reject(Request $request, Estimate $estimate)
    {
        $request->validate([
            'rejected_reason' => ['required', 'string', 'max:500'],
        ]);

        $estimate->update([
            'status'          => 'rejected',
            'rejected_reason' => $request->rejected_reason,
        ]);

        return back()->with('success', 'Estimate rejected.');
    }

    public function pdf(Estimate $estimate)
    {
        $estimate->load(['container', 'customer', 'inquiry', 'lineItems', 'createdBy']);

        return view('estimates.pdf', compact('estimate'));
    }

    private function calculateTotals(array $lineItems, float $taxPct): array
    {
        $subtotal = collect($lineItems)->sum(fn ($item) => $item['qty'] * $item['unit_price']);
        $taxAmount = round($subtotal * $taxPct / 100, 2);

        return [
            'subtotal'   => round($subtotal, 2),
            'tax_amount' => $taxAmount,
            'grand_total' => round($subtotal + $taxAmount, 2),
        ];
    }

    private function generateEstimateNo(): string
    {
        $last = Estimate::latest('id')->value('estimate_no');
        $next = $last ? (int) Str::afterLast($last, '-') + 1 : 1;
        return 'RE-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
