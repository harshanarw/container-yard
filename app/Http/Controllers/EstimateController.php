<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstimateRequest;
use App\Http\Requests\UpdateEstimateRequest;
use App\Jobs\SendEstimateEmailJob;
use App\Mail\EstimateReminderMail;
use App\Models\ChargeCode;
use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\Estimate;
use App\Models\Inquiry;
use App\Models\MrCode;
use App\Models\MrTariffHeader;
use App\Models\PortalToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

        $pendingApprovalCount = Estimate::whereIn('status', ['sent', 'under_review'])->count();

        return view('estimates.index', compact('estimates', 'customers', 'pendingApprovalCount'));
    }

    public function create(Request $request)
    {
        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $containers     = Container::whereIn('status', ['in_yard', 'in_repair'])
            ->with('customer')
            ->orderBy('container_no')
            ->get();
        $equipmentTypes = EquipmentType::active()->get();

        $selectedInquiry   = $request->inquiry_id
            ? Inquiry::with(['container', 'customer', 'damages.locationCode', 'damages.componentCode',
                             'damages.damageCode', 'damages.repairCode', 'equipmentType'])->find($request->inquiry_id)
            : null;

        $selectedContainer = $request->container_id
            ? Container::with(['customer', 'equipmentType'])->find($request->container_id)
            : null;

        $mrComponentCodes = MrCode::ofType('component')->active()->orderBy('sort_order')->get();
        $mrLocationCodes  = MrCode::ofType('location')->active()->orderBy('sort_order')->get();
        $chargeCodes      = ChargeCode::with('taxCode')
            ->where('is_active', true)
            ->whereIn('category', ['repair', 'labour'])
            ->orderBy('sort_order')->orderBy('code')
            ->get();

        return view('estimates.create', compact(
            'customers', 'containers', 'equipmentTypes', 'selectedInquiry', 'selectedContainer',
            'mrComponentCodes', 'mrLocationCodes', 'chargeCodes'
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
            'estimate_no'       => $this->generateEstimateNo(),
            'inquiry_id'        => $request->inquiry_id,
            'container_id'      => $container->id,
            'equipment_type_id' => $request->equipment_type_id ?? $container->equipment_type_id,
            'container_no'      => $container->container_no,
            'customer_id'       => $request->customer_id,
            'size'              => $container->size,
            'type_code'         => $container->type_code,
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
            $lineAmount = round($item['qty'] * $item['unit_price'], 2);
            $estimate->lineItems()->create([
                'component'           => $item['component'],
                'repair_type'         => $item['repair_type'],
                'qty'                 => $item['qty'],
                'unit_price'          => $item['unit_price'],
                'tax_percentage'      => $item['tax_percentage'] ?? 0,
                'line_amount'         => $lineAmount,
                // MR code traceability
                'damage_id'           => $item['damage_id'] ?? null,
                'mr_tariff_rule_id'   => $item['mr_tariff_rule_id'] ?? null,
                'location_code_id'    => $item['location_code_id'] ?? null,
                'component_code_id'   => $item['component_code_id'] ?? null,
                'damage_code_id'      => $item['damage_code_id'] ?? null,
                'repair_code_id'      => $item['repair_code_id'] ?? null,
                'material_code_id'    => $item['material_code_id'] ?? null,
                'cedex_code'          => $item['cedex_code'] ?? null,
                'repair_category_id'  => $item['repair_category_id'] ?? null,
                // Charge / tax code
                'charge_code_id'      => $item['charge_code_id'] ?? null,
                'tax_code_id'         => $item['tax_code_id'] ?? null,
                // Labor / material breakdown
                'std_labor_hours'     => $item['std_labor_hours'] ?? 0,
                'labor_rate'          => $item['labor_rate'] ?? 0,
                'labor_amount'        => $item['labor_amount'] ?? 0,
                'material_qty'        => $item['material_qty'] ?? 0,
                'material_rate'       => $item['material_rate'] ?? 0,
                'material_amount'     => $item['material_amount'] ?? 0,
                'ancillary_amount'    => $item['ancillary_amount'] ?? 0,
            ]);
        }

        if ($request->inquiry_id) {
            Inquiry::where('id', $request->inquiry_id)
                ->update(['status' => 'estimate_sent']);
        }

        return redirect()->route('estimates.show', $estimate)
            ->with('success', "Estimate {$estimate->estimate_no} created successfully.");
    }

    public function show(Estimate $estimate)
    {
        $estimate->load([
            'container', 'customer', 'inquiry', 'lineItems',
            'createdBy', 'approvedBy', 'parentEstimate', 'revisions',
            'approvalActions.lineItem', 'approvalActions.actionedBy',
        ]);

        $activeToken = PortalToken::where('tokenable_type', Estimate::class)
            ->where('tokenable_id', $estimate->id)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        return view('estimates.show', compact('estimate', 'activeToken'));
    }

    public function edit(Estimate $estimate)
    {
        if (in_array($estimate->status, ['approved', 'completed'])) {
            return back()->with('error', 'Approved or completed estimates cannot be edited.');
        }

        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $containers     = Container::whereIn('status', ['in_yard', 'in_repair'])
            ->with('customer')->orderBy('container_no')->get();
        $equipmentTypes = EquipmentType::active()->get();

        $estimate->load(['lineItems', 'equipmentType']);

        $mrComponentCodes = MrCode::ofType('component')->active()->orderBy('sort_order')->get();
        $mrLocationCodes  = MrCode::ofType('location')->active()->orderBy('sort_order')->get();
        $chargeCodes      = ChargeCode::with('taxCode')
            ->where('is_active', true)
            ->whereIn('category', ['repair', 'labour'])
            ->orderBy('sort_order')->orderBy('code')
            ->get();

        return view('estimates.edit', compact('estimate', 'customers', 'containers', 'equipmentTypes',
                                             'mrComponentCodes', 'mrLocationCodes', 'chargeCodes'));
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
            $lineAmount = round($item['qty'] * $item['unit_price'], 2);
            $estimate->lineItems()->create([
                'component'           => $item['component'],
                'repair_type'         => $item['repair_type'],
                'qty'                 => $item['qty'],
                'unit_price'          => $item['unit_price'],
                'tax_percentage'      => $item['tax_percentage'] ?? 0,
                'line_amount'         => $lineAmount,
                // MR code traceability
                'damage_id'           => $item['damage_id'] ?? null,
                'mr_tariff_rule_id'   => $item['mr_tariff_rule_id'] ?? null,
                'location_code_id'    => $item['location_code_id'] ?? null,
                'component_code_id'   => $item['component_code_id'] ?? null,
                'damage_code_id'      => $item['damage_code_id'] ?? null,
                'repair_code_id'      => $item['repair_code_id'] ?? null,
                'material_code_id'    => $item['material_code_id'] ?? null,
                'cedex_code'          => $item['cedex_code'] ?? null,
                'repair_category_id'  => $item['repair_category_id'] ?? null,
                // Charge / tax code
                'charge_code_id'      => $item['charge_code_id'] ?? null,
                'tax_code_id'         => $item['tax_code_id'] ?? null,
                // Labor / material breakdown
                'std_labor_hours'     => $item['std_labor_hours'] ?? 0,
                'labor_rate'          => $item['labor_rate'] ?? 0,
                'labor_amount'        => $item['labor_amount'] ?? 0,
                'material_qty'        => $item['material_qty'] ?? 0,
                'material_rate'       => $item['material_rate'] ?? 0,
                'material_amount'     => $item['material_amount'] ?? 0,
                'ancillary_amount'    => $item['ancillary_amount'] ?? 0,
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
            'expiry_days'   => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $isResend = in_array($estimate->status, ['sent', 'under_review', 'returned', 'rejected']);

        if ($isResend) {
            // Auto-version the estimate
            $estimate->increment('version_no');
            // Reset all line approval statuses
            $estimate->lineItems()->update(['approval_status' => 'pending']);
        }

        $estimate->update([
            'status'        => 'sent',
            'sent_at'       => now(),
            'send_to_email' => $request->send_to_email,
            'send_cc_email' => $request->send_cc_email,
            'email_message' => $request->email_message,
        ]);

        // Revoke old tokens for this estimate
        PortalToken::where('tokenable_type', Estimate::class)
            ->where('tokenable_id', $estimate->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $expiryDays = (int) ($request->expiry_days ?? 30);
        $portalToken = PortalToken::generate($estimate, $request->send_to_email, $expiryDays);

        SendEstimateEmailJob::dispatch($estimate, $portalToken, $request->email_message);

        $versionNote = $isResend ? " (v{$estimate->version_no})" : '';
        return back()->with('success', "Estimate sent to {$request->send_to_email}{$versionNote}.");
    }

    public function sendReminder(Estimate $estimate)
    {
        $token = PortalToken::where('tokenable_type', Estimate::class)
            ->where('tokenable_id', $estimate->id)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        if (!$token) {
            return back()->with('error', 'No active portal token — send the estimate first.');
        }

        try {
            Mail::to($token->email)->send(new EstimateReminderMail($estimate, $token));
            return back()->with('success', "Reminder sent to {$token->email}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to send reminder: ' . $e->getMessage());
        }
    }

    public function revokeToken(Estimate $estimate)
    {
        PortalToken::where('tokenable_type', Estimate::class)
            ->where('tokenable_id', $estimate->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return back()->with('success', 'Portal link revoked. Owner can no longer access via the old link.');
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

    /**
     * AJAX: convert a survey's damage findings into pre-priced estimate line items.
     * Looks up the best matching MR tariff rule for each damage.
     */
    public function importDamages(Request $request, Inquiry $inquiry)
    {
        $inquiry->load([
            'damages.locationCode', 'damages.componentCode',
            'damages.damageCode',   'damages.repairCode',
            'damages.materialCode',
        ]);

        // Repair code → estimate repair_type enum
        $repairTypeMap = [
            'RPL' => 'replace',
            'SLR' => 'replace',
            'WLD' => 'weld',
            'STR' => 'straighten',
            'TAP' => 'paint',
            'CLN' => 'clean_and_treat',
            'PAT' => 'repair',
            'GRD' => 'repair',
            'BLT' => 'repair',
            'INS' => 'repair',
        ];

        // Best tariff: customer-specific first, then default (null customer)
        $customerId     = $inquiry->customer_id;
        $containerSize  = $request->container_size ?? $inquiry->equipmentType?->size;

        $tariffHeader = MrTariffHeader::with('rules')
            ->where('is_active', true)
            ->where(function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)->orWhereNull('customer_id');
            })
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now());
            })
            ->orderByRaw('CASE WHEN customer_id IS NOT NULL THEN 0 ELSE 1 END')
            ->first();

        $lines = [];

        foreach ($inquiry->damages as $dmg) {
            // Find the most specific tariff rule: component + repair > component only > repair only
            $tariffRule = null;
            if ($tariffHeader) {
                // Try exact match on both codes
                if ($dmg->component_code_id && $dmg->repair_code_id) {
                    $tariffRule = $tariffHeader->rules
                        ->where('component_code_id', $dmg->component_code_id)
                        ->where('repair_code_id', $dmg->repair_code_id)
                        ->first();
                }
                // Fallback: component only
                if (!$tariffRule && $dmg->component_code_id) {
                    $tariffRule = $tariffHeader->rules
                        ->where('component_code_id', $dmg->component_code_id)
                        ->whereNull('repair_code_id')
                        ->first();
                }
                // Fallback: repair only
                if (!$tariffRule && $dmg->repair_code_id) {
                    $tariffRule = $tariffHeader->rules
                        ->whereNull('component_code_id')
                        ->where('repair_code_id', $dmg->repair_code_id)
                        ->first();
                }
            }

            $repairCode  = $dmg->repairCode?->code ?? '';
            $repairType  = $repairTypeMap[$repairCode] ?? 'repair';
            $qty         = max(1, (float) ($dmg->quantity ?? 1));
            $unitPrice   = $tariffRule ? $tariffRule->computeAmount() : 0;

            $lines[] = [
                // Traceability
                'damage_id'         => $dmg->id,
                'mr_tariff_rule_id' => $tariffRule?->id,
                'location_code_id'  => $dmg->location_code_id,
                'component_code_id' => $dmg->component_code_id,
                'damage_code_id'    => $dmg->damage_code_id,
                'repair_code_id'    => $dmg->repair_code_id,
                'material_code_id'  => $dmg->material_code_id,
                'cedex_code'        => $dmg->cedex_code,
                // Line data
                'component'         => $dmg->componentCode?->name
                                        ?? ucwords(str_replace('_', ' ', $dmg->location ?? '')),
                'repair_type'       => $repairType,
                'qty'               => $qty,
                'unit_price'        => $unitPrice,
                'tax_percentage'    => 0,
                // Labor / material breakdown from tariff
                'std_labor_hours'   => (float) ($tariffRule?->std_labor_hours ?? 0),
                'labor_rate'        => (float) ($tariffRule?->labor_rate ?? 0),
                'labor_amount'      => round((float)($tariffRule?->std_labor_hours ?? 0) * (float)($tariffRule?->labor_rate ?? 0), 2),
                'material_qty'      => (float) ($tariffRule?->material_qty ?? 0),
                'material_rate'     => (float) ($tariffRule?->material_rate ?? 0),
                'material_amount'   => round((float)($tariffRule?->material_qty ?? 0) * (float)($tariffRule?->material_rate ?? 0), 2),
                'ancillary_amount'  => (float) ($tariffRule?->ancillary ?? 0),
                // Display labels (for the import preview, not submitted)
                '_location'         => $dmg->locationCode?->name ?? ucwords(str_replace('_', ' ', $dmg->location ?? '')),
                '_damage'           => $dmg->damageCode?->name ?? ucwords(str_replace('_', ' ', $dmg->damage_type ?? '')),
                '_severity'         => $dmg->severity,
                '_tariff_matched'   => $tariffRule !== null,
            ];
        }

        return response()->json([
            'lines'        => $lines,
            'tariff_name'  => $tariffHeader?->name,
            'tariff_found' => $tariffHeader !== null,
            'damage_count' => count($lines),
        ]);
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
