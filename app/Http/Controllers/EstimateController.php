<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstimateRequest;
use App\Http\Requests\UpdateEstimateRequest;
use App\Jobs\SendEstimateEmailJob;
use App\Mail\EstimateReminderMail;
use App\Models\ChargeCode;
use App\Models\Container;
use App\Models\MrCodeChargeMapping;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\Estimate;
use App\Models\Inquiry;
use App\Models\MrCode;
use App\Models\MrTariffHeader;
use App\Models\PortalToken;
use App\Models\ExchangeRate;
use App\Models\TaxCode;
use App\Models\WashingTariff;
use App\Services\CurrencyService;
use App\Services\EstimateMailService;
use App\Services\ExternalRecipientResolver;
use App\Services\NotificationService;
use App\Services\RepairCategoryResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EstimateController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:estimates.view')->only(['index', 'show', 'resolveChargeCode', 'exchangeRateLookup', 'washingLookup', 'importDamages', 'pdf']);
        $this->middleware('can:estimates.create')->only(['create', 'store']);
        $this->middleware('can:estimates.edit')->only(['edit', 'update', 'send', 'sendReminder', 'revokeToken']);
        $this->middleware('can:estimates.delete')->only(['destroy']);
        $this->middleware('can:estimates.approve')->only(['approve', 'reject']);
    }

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
        $taxCodes         = TaxCode::where('is_active', true)->orderBy('sort_order')->get();

        $dimUom          = \App\Models\CompanySetting::current()->mr_dimension_uom ?? 'ft_in';
        $defaultCurrency = CurrencyService::defaultCurrency();
        $todayRate       = ExchangeRate::getRate('USD', $defaultCurrency, today()->toDateString());

        // Auto-default estimate currency from the linked customer (overseas → USD, local → default)
        $defaultEstimateCurrency = $selectedInquiry?->customer?->currency
            ?? $selectedContainer?->customer?->currency
            ?? 'USD';

        return view('estimates.create', compact(
            'customers', 'containers', 'equipmentTypes', 'selectedInquiry', 'selectedContainer',
            'mrComponentCodes', 'mrLocationCodes', 'chargeCodes', 'taxCodes', 'dimUom',
            'defaultCurrency', 'todayRate', 'defaultEstimateCurrency'
        ));
    }

    public function store(StoreEstimateRequest $request)
    {
        $container = Container::findOrFail($request->container_id);

        $lineItems = array_values($request->line_items);
        $totals    = $this->calculateLineTotals($lineItems);

        $estimate = DB::transaction(function () use ($container, $request, $lineItems, $totals) {
            $resolver = new RepairCategoryResolver();

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
                // Rate is USD → estimate currency; a USD estimate is 1:1 with the
                // tariff currency, so never let it carry a stray conversion rate.
                'exchange_rate'  => $request->currency === 'USD' ? 1.0 : ($request->exchange_rate ?? 1.0),
                'priority'       => $request->priority,
                'status'         => 'draft',
                'scope_of_work'  => $request->scope_of_work,
                'terms'          => $request->terms,
                'subtotal'       => $totals['subtotal'],
                'sscl_amount'    => $totals['sscl_amount'],
                'vat_amount'     => $totals['vat_amount'],
                'tax_percentage' => $totals['effective_tax_pct'],
                'tax_amount'     => $totals['tax_amount'],
                'grand_total'    => $totals['grand_total'],
                'send_to_email'  => $request->send_to_email,
                'send_cc_email'  => $request->send_cc_email,
                'email_message'  => $request->email_message,
                'attach_pdf'     => $request->boolean('attach_pdf'),
                'attach_photos'  => $request->boolean('attach_photos'),
                'created_by'     => auth()->id(),
            ]);

            foreach ($lineItems as $idx => $item) {
                $meta = $totals['lines'][$idx];
                $repairCategoryId = $item['repair_category_id']
                    ?? $resolver->resolve(
                        isset($item['component_code_id']) ? (int) $item['component_code_id'] : null,
                        $item['repair_type'] ?? null
                    )?->id;
                $estimate->lineItems()->create([
                    'component'           => $item['component'],
                    'repair_type'         => $item['repair_type'],
                    'qty'                 => $item['qty'],
                    'unit_price'          => $item['unit_price'],
                    'tax_percentage'      => $meta['tax1_rate'] + $meta['tax2_rate'],
                    'line_amount'         => $meta['line_amount'],
                    'tax1_rate'           => $meta['tax1_rate'],
                    'tax2_rate'           => $meta['tax2_rate'],
                    'tax1_amount'         => $meta['tax1_amount'],
                    'tax2_amount'         => $meta['tax2_amount'],
                    'gross_amount'        => $meta['gross_amount'],
                    // Washing traceability (set by the washing picker)
                    'washing_tariff_id'   => $item['washing_tariff_id'] ?? null,
                    'wash_scope'          => $item['wash_scope'] ?? null,
                    // MR code traceability
                    'damage_id'           => $item['damage_id'] ?? null,
                    'mr_tariff_rule_id'   => $item['mr_tariff_rule_id'] ?? null,
                    'location_code_id'    => $item['location_code_id'] ?? null,
                    'component_code_id'   => $item['component_code_id'] ?? null,
                    'damage_code_id'      => $item['damage_code_id'] ?? null,
                    'repair_code_id'      => $item['repair_code_id'] ?? null,
                    'material_code_id'    => $item['material_code_id'] ?? null,
                    'cedex_code'          => $item['cedex_code'] ?? null,
                    'repair_category_id'  => $repairCategoryId,
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
                    // Dimension audit trail
                    'dim_length'          => $item['dim_length'] ?? null,
                    'dim_width'           => $item['dim_width'] ?? null,
                    'dim_uom'             => $item['dim_uom'] ?? null,
                ]);
            }

            if ($request->inquiry_id) {
                Inquiry::where('id', $request->inquiry_id)
                    ->update(['status' => 'estimate_sent']);
            }

            return $estimate;
        });

        return redirect()->route('estimates.show', $estimate)
            ->with('success', "Estimate {$estimate->estimate_no} created successfully.");
    }

    public function show(Estimate $estimate)
    {
        $estimate->load([
            'container', 'customer', 'inquiry', 'lineItems.taxCode',
            'createdBy', 'updatedBy', 'approvedBy', 'parentEstimate', 'revisions',
            'approvalActions.lineItem', 'approvalActions.actionedBy',
            'documents',
            'workOrders.repairCategory', 'workOrders.assignedTo',
        ]);

        $hasUnassignedLines = $estimate->status === 'approved'
            && $estimate->lineItems()->whereDoesntHave('workOrderLine')->exists();

        $activeToken = PortalToken::where('tokenable_type', Estimate::class)
            ->where('tokenable_id', $estimate->id)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        return view('estimates.show', compact('estimate', 'activeToken', 'hasUnassignedLines'));
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

        $estimate->load(['lineItems.taxCode', 'equipmentType']);

        $mrComponentCodes = MrCode::ofType('component')->active()->orderBy('sort_order')->get();
        $mrLocationCodes  = MrCode::ofType('location')->active()->orderBy('sort_order')->get();
        $chargeCodes      = ChargeCode::with('taxCode')
            ->where('is_active', true)
            ->whereIn('category', ['repair', 'labour'])
            ->orderBy('sort_order')->orderBy('code')
            ->get();
        $taxCodes         = TaxCode::where('is_active', true)->orderBy('sort_order')->get();

        $dimUom          = \App\Models\CompanySetting::current()->mr_dimension_uom ?? 'ft_in';
        $defaultCurrency = CurrencyService::defaultCurrency();
        $todayRate       = ExchangeRate::getRate('USD', $defaultCurrency, $estimate->estimate_date->toDateString());
        $rateLocked      = in_array($estimate->status, ['sent', 'under_review', 'partially_approved', 'approved']);

        return view('estimates.edit', compact('estimate', 'customers', 'containers', 'equipmentTypes',
                                             'mrComponentCodes', 'mrLocationCodes', 'chargeCodes', 'taxCodes', 'dimUom',
                                             'defaultCurrency', 'todayRate', 'rateLocked'));
    }

    public function update(UpdateEstimateRequest $request, Estimate $estimate)
    {
        if (in_array($estimate->status, ['approved', 'completed'])) {
            return back()->with('error', 'Approved or completed estimates cannot be edited.');
        }

        $lineItems = array_values($request->line_items);
        $totals    = $this->calculateLineTotals($lineItems);

        // Lock exchange_rate once sent to customer; only allow changes while still a draft
        $lockRate = in_array($estimate->status, ['sent', 'under_review', 'partially_approved']);

        $estimate->update([
            'estimate_date'  => $request->estimate_date,
            'valid_until'    => $request->valid_until,
            'currency'       => $request->currency,
            // Locked once sent; while editable, a USD estimate is always 1:1.
            'exchange_rate'  => $lockRate
                ? $estimate->exchange_rate
                : ($request->currency === 'USD' ? 1.0 : ($request->exchange_rate ?? 1.0)),
            'priority'       => $request->priority,
            'scope_of_work'  => $request->scope_of_work,
            'terms'          => $request->terms,
            'subtotal'       => $totals['subtotal'],
            'sscl_amount'    => $totals['sscl_amount'],
            'vat_amount'     => $totals['vat_amount'],
            'tax_percentage' => $totals['effective_tax_pct'],
            'tax_amount'     => $totals['tax_amount'],
            'grand_total'    => $totals['grand_total'],
            'send_to_email'  => $request->send_to_email,
            'send_cc_email'  => $request->send_cc_email,
            'email_message'  => $request->email_message,
            'attach_pdf'     => $request->boolean('attach_pdf'),
            'attach_photos'  => $request->boolean('attach_photos'),
            'updated_by'     => auth()->id(),
        ]);

        $estimate->lineItems()->delete();
        $resolver = new RepairCategoryResolver();
        foreach ($lineItems as $idx => $item) {
            $meta = $totals['lines'][$idx];
            $repairCategoryId = $item['repair_category_id']
                ?? $resolver->resolve(
                    isset($item['component_code_id']) ? (int) $item['component_code_id'] : null,
                    $item['repair_type'] ?? null
                )?->id;
            $estimate->lineItems()->create([
                'component'           => $item['component'],
                'repair_type'         => $item['repair_type'],
                'qty'                 => $item['qty'],
                'unit_price'          => $item['unit_price'],
                'tax_percentage'      => $meta['tax1_rate'] + $meta['tax2_rate'],
                'line_amount'         => $meta['line_amount'],
                'tax1_rate'           => $meta['tax1_rate'],
                'tax2_rate'           => $meta['tax2_rate'],
                'tax1_amount'         => $meta['tax1_amount'],
                'tax2_amount'         => $meta['tax2_amount'],
                'gross_amount'        => $meta['gross_amount'],
                // Washing traceability (set by the washing picker)
                'washing_tariff_id'   => $item['washing_tariff_id'] ?? null,
                'wash_scope'          => $item['wash_scope'] ?? null,
                // MR code traceability
                'damage_id'           => $item['damage_id'] ?? null,
                'mr_tariff_rule_id'   => $item['mr_tariff_rule_id'] ?? null,
                'location_code_id'    => $item['location_code_id'] ?? null,
                'component_code_id'   => $item['component_code_id'] ?? null,
                'damage_code_id'      => $item['damage_code_id'] ?? null,
                'repair_code_id'      => $item['repair_code_id'] ?? null,
                'material_code_id'    => $item['material_code_id'] ?? null,
                'cedex_code'          => $item['cedex_code'] ?? null,
                'repair_category_id'  => $repairCategoryId,
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
                // Dimension audit trail
                'dim_length'          => $item['dim_length'] ?? null,
                'dim_width'           => $item['dim_width'] ?? null,
                'dim_uom'             => $item['dim_uom'] ?? null,
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
            'send_to_email' => ['required', 'string', 'max:2000'],
            'send_cc_email' => ['nullable', 'string', 'max:2000'],
            'email_message' => ['nullable', 'string'],
            'expiry_days'   => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $toEmails = $this->parseEmails($request->send_to_email);
        $ccEmails = $this->parseEmails($request->send_cc_email ?? '');

        if (empty($toEmails)) {
            return back()->withInput()->with('error', 'Please enter at least one valid To email address.');
        }

        $primaryTo = $toEmails[0];

        $isResend = in_array($estimate->status, ['sent', 'under_review', 'returned', 'rejected']);

        $versionNote = '';
        $toSummary   = '';

        try {
            DB::beginTransaction();

            if ($isResend) {
                $estimate->increment('version_no');
                $estimate->lineItems()->update(['approval_status' => 'pending']);
            }

            $estimate->update([
                'status'        => 'sent',
                'sent_at'       => now(),
                'send_to_email' => implode(', ', $toEmails),
                'send_cc_email' => $ccEmails ? implode(', ', $ccEmails) : null,
                'email_message' => $request->email_message,
            ]);

            PortalToken::where('tokenable_type', Estimate::class)
                ->where('tokenable_id', $estimate->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $expiryDays  = (int) ($request->expiry_days ?? 30);
            $portalToken = PortalToken::generate($estimate, $primaryTo, $expiryDays);

            SendEstimateEmailJob::dispatchSync($estimate, $portalToken, $request->email_message);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $this->friendlyMailError($e));
        }

        $versionNote = $isResend ? " (v{$estimate->version_no})" : '';
        $toSummary   = count($toEmails) > 1
            ? $primaryTo . ' +' . (count($toEmails) - 1) . ' more'
            : $primaryTo;

        NotificationService::notifyAll(
            'Estimate Sent — ' . $estimate->estimate_no,
            ($estimate->customer->name ?? 'Unknown') . ' · Sent to ' . $toSummary . $versionNote,
            'info',
            route('estimates.show', $estimate)
        );

        return back()->with('success', "Estimate sent to {$toSummary}{$versionNote}.");
    }

    /**
     * Parse a comma/semicolon/newline-separated string into a clean array of
     * valid, de-duplicated email addresses.
     *
     * @return string[]
     */
    private function parseEmails(?string $raw): array
    {
        if (blank($raw)) {
            return [];
        }

        $parts  = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $parts  = array_map('trim', $parts);
        $parts  = array_filter($parts, fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL));

        return array_values(array_unique($parts));
    }

    private function friendlyMailError(\Throwable $e): string
    {
        $msg = $e->getMessage() . ' ' . ($e->getPrevious()?->getMessage() ?? '');

        if (str_contains($msg, 'getaddrinfo') || str_contains($msg, 'No such host') || str_contains($msg, 'Name or service not known') || str_contains($msg, 'nodename nor servname')) {
            return 'Email could not be sent: the mail server hostname could not be resolved. Please check the server address in Settings → Email Config.';
        }

        if (str_contains($msg, 'Connection refused') || str_contains($msg, 'Connection timed out') || str_contains($msg, 'connect()') || str_contains($msg, 'Network is unreachable')) {
            return 'Email could not be sent: unable to connect to the mail server. Please verify the server address and port in Settings → Email Config.';
        }

        if (str_contains($msg, '535') || str_contains($msg, 'Authentication') || str_contains($msg, 'Invalid credentials') || str_contains($msg, 'Username and Password not accepted')) {
            return 'Email could not be sent: authentication failed. Please check the email username and password in Settings → Email Config.';
        }

        if (str_contains($msg, '550') || str_contains($msg, 'Relay access denied') || str_contains($msg, 'Sender address rejected')) {
            return 'Email could not be sent: the mail server rejected the message. Please verify the sender address in Settings → Email Config.';
        }

        return 'Email could not be sent: ' . rtrim($e->getMessage(), '.') . '. Please check Settings → Email Config.';
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
            $recipients = ExternalRecipientResolver::resolve(
                category: 'estimate',
                customerId: $estimate->customer_id,
                primaryTo: $token->email,
            );

            $pending = EstimateMailService::resolveMailer()->to($recipients['to']);

            if (!empty($recipients['cc'])) {
                $pending->cc($recipients['cc']);
            }

            $pending->send(new EstimateReminderMail($estimate, $token));
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

        NotificationService::notifyAll(
            'Estimate Approved — ' . $estimate->estimate_no,
            ($estimate->customer->name ?? 'Unknown') . ' · ' . $estimate->container_no,
            'success',
            route('estimates.show', $estimate)
        );

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

        NotificationService::notifyAll(
            'Estimate Rejected — ' . $estimate->estimate_no,
            ($estimate->customer->name ?? 'Unknown') . ' · ' . $estimate->container_no,
            'warning',
            route('estimates.show', $estimate)
        );

        return back()->with('success', 'Estimate rejected.');
    }

    public function pdf(Estimate $estimate)
    {
        $estimate->load(['container', 'customer', 'inquiry', 'lineItems.componentCode',
                         'lineItems.chargeCode', 'lineItems.taxCode', 'createdBy']);

        return view('estimates.pdf', compact('estimate'));
    }

    /**
     * AJAX: convert a survey's damage findings into pre-priced estimate line items.
     */
    public function importDamages(Request $request, Inquiry $inquiry)
    {
        $inquiry->load([
            'damages.locationCode', 'damages.componentCode',
            'damages.damageCode',   'damages.repairCode',
            'damages.materialCode',
        ]);

        $repairTypeMap = [
            'RPL' => 'replace', 'SLR' => 'replace', 'WLD' => 'weld',
            'STR' => 'straighten', 'TAP' => 'paint', 'CLN' => 'clean_and_treat',
            'PAT' => 'repair', 'GRD' => 'repair', 'BLT' => 'repair', 'INS' => 'repair',
        ];

        $customerId    = $inquiry->customer_id;
        $containerSize = $request->container_size ?? $inquiry->equipmentType?->size;

        $estCurrency  = strtoupper($request->get('currency', 'USD'));
        $exchangeRate = max(0.000001, (float) $request->get('exchange_rate', 1.0));

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

        // Convert tariff rates to the estimate currency, honouring the tariff's own
        // currency (usually USD). exchange_rate is USD → estimate currency, so it
        // only applies to a USD tariff; a tariff already in the estimate currency
        // needs no conversion, and an unknown cross-currency is left as-is rather
        // than double-converted.
        $tariffCurrency = strtoupper($tariffHeader?->currency ?: 'USD');
        if ($tariffCurrency === $estCurrency) {
            $factor = 1.0;
        } elseif ($tariffCurrency === 'USD') {
            $factor = $estCurrency === 'USD' ? 1.0 : $exchangeRate;
        } else {
            $factor = 1.0;
        }

        $lines = [];

        foreach ($inquiry->damages as $dmg) {
            $tariffRule = null;
            if ($tariffHeader) {
                if ($dmg->component_code_id && $dmg->repair_code_id) {
                    $tariffRule = $tariffHeader->rules
                        ->where('component_code_id', $dmg->component_code_id)
                        ->where('repair_code_id', $dmg->repair_code_id)
                        ->first();
                }
                if (!$tariffRule && $dmg->component_code_id) {
                    $tariffRule = $tariffHeader->rules
                        ->where('component_code_id', $dmg->component_code_id)
                        ->whereNull('repair_code_id')
                        ->first();
                }
                if (!$tariffRule && $dmg->repair_code_id) {
                    $tariffRule = $tariffHeader->rules
                        ->whereNull('component_code_id')
                        ->where('repair_code_id', $dmg->repair_code_id)
                        ->first();
                }
            }

            $repairCode = $dmg->repairCode?->code ?? '';
            $repairType = $repairTypeMap[$repairCode] ?? 'repair';

            // Derive the effective quantity from dimensions when the tariff rule's unit_type
            // is area- or length-based; otherwise use the manually-entered damage quantity.
            $unitType = $tariffRule?->unit_type ?? 'nos';
            if ($unitType === 'sqft' && (float)($dmg->dim_area ?? 0) > 0) {
                // dim_area is sq ft for ft_in UOM, m² for cm UOM
                $qty = $dmg->dim_uom === 'ft_in'
                    ? max(0.01, round((float)$dmg->dim_area, 4))
                    : max(0.01, round((float)$dmg->dim_area * 10.764, 4)); // m² → sqft
            } elseif ($unitType === 'inches' && (float)($dmg->dim_length ?? 0) > 0) {
                // dim_length is total inches for ft_in UOM, cm for cm UOM
                $qty = $dmg->dim_uom === 'ft_in'
                    ? max(0.01, (float)$dmg->dim_length)
                    : max(0.01, round((float)$dmg->dim_length / 2.54, 2)); // cm → inches
            } else {
                $qty = max(1, (float)($dmg->quantity ?? 1));
            }

            // Amounts multiplied by $factor: 1.0 for USD estimates, exchange_rate for local currency
            $unitPrice  = $tariffRule ? round($tariffRule->computeAmount() * $factor, 2) : 0;
            $laborHrs   = (float) ($tariffRule?->std_labor_hours ?? 0);
            $laborRate  = round((float)($tariffRule?->labor_rate ?? 0) * $factor, 2);
            $matQty     = (float) ($tariffRule?->material_qty ?? 0);
            $matRate    = round((float)($tariffRule?->material_rate ?? 0) * $factor, 2);

            $chargeMapping = MrCodeChargeMapping::resolve($dmg->component_code_id, $dmg->repair_code_id);
            $chargeCode    = $chargeMapping?->chargeCode;
            $taxCode       = $chargeCode?->taxCode;

            $lines[] = [
                'damage_id'         => $dmg->id,
                'mr_tariff_rule_id' => $tariffRule?->id,
                'location_code_id'  => $dmg->location_code_id,
                'component_code_id' => $dmg->component_code_id,
                'damage_code_id'    => $dmg->damage_code_id,
                'repair_code_id'    => $dmg->repair_code_id,
                'material_code_id'  => $dmg->material_code_id,
                'cedex_code'        => $dmg->cedex_code,
                'charge_code_id'    => $chargeCode?->id,
                'tax_code_id'       => $taxCode?->id,
                'tax1_rate'         => $taxCode?->tax1_rate ?? 0,
                'tax2_rate'         => $taxCode?->tax2_rate ?? 0,
                'component'         => $dmg->componentCode?->name
                                        ?? ucwords(str_replace('_', ' ', $dmg->location ?? '')),
                'repair_type'       => $repairType,
                'qty'               => $qty,
                'unit_price'        => $unitPrice,
                'std_labor_hours'   => $laborHrs,
                'labor_rate'        => $laborRate,
                'labor_amount'      => round($laborHrs * $laborRate, 2),
                'material_qty'      => $matQty,
                'material_rate'     => $matRate,
                'material_amount'   => round($matQty * $matRate, 2),
                'ancillary_amount'  => round((float)($tariffRule?->ancillary ?? 0) * $factor, 2),
                'dim_length'        => $dmg->dim_length,
                'dim_width'         => $dmg->dim_width,
                'dim_uom'           => $dmg->dim_uom,
                '_unit_type'        => $unitType,
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

    public function resolveChargeCode(Request $request)
    {
        $componentCodeId = $request->integer('component_code_id') ?: null;
        $repairCodeId    = $request->integer('repair_code_id')    ?: null;

        $mapping = MrCodeChargeMapping::resolve($componentCodeId, $repairCodeId);

        if (! $mapping) {
            return response()->json(['found' => false]);
        }

        $taxCode = $mapping->chargeCode->taxCode;

        return response()->json([
            'found'              => true,
            'charge_code_id'     => $mapping->charge_code_id,
            'charge_code'        => $mapping->chargeCode->code,
            'charge_description' => $mapping->chargeCode->description,
            'tax_code_id'        => $mapping->chargeCode->tax_code_id,
            'tax1_rate'          => $taxCode?->tax1_rate ?? 0,
            'tax2_rate'          => $taxCode?->tax2_rate ?? 0,
        ]);
    }

    /**
     * AJAX: look up the USD → target-currency exchange rate for a given date.
     * Used by the estimate create/edit form to auto-populate the rate field.
     */
    public function exchangeRateLookup(Request $request)
    {
        $from   = strtoupper($request->get('currency', 'USD'));
        $to     = strtoupper($request->get('target', CurrencyService::defaultCurrency()));
        $date   = $request->get('date', today()->toDateString());

        if ($from === $to) {
            return response()->json(['rate' => 1.0, 'found' => true, 'from' => $from, 'to' => $to]);
        }

        $rate = ExchangeRate::getRate($from, $to, $date);
        return response()->json(['rate' => $rate, 'found' => $rate !== null, 'from' => $from, 'to' => $to]);
    }

    /**
     * AJAX: resolve internal & external washing rates for the estimate context.
     * Tariff rates are in USD; the response is already converted to the estimate
     * currency at the given rate (mirrors importDamages / the Phase A factor).
     */
    public function washingLookup(Request $request)
    {
        $customerId = $request->integer('customer_id') ?: null;
        $size       = $request->get('size') ?: null;
        $type       = $request->get('wash_type', 'standard');
        $date       = $request->get('date') ?: today()->toDateString();
        $estCur     = strtoupper($request->get('currency', 'USD'));
        $rate       = max(0.000001, (float) $request->get('exchange_rate', 1.0));

        $out = [];
        foreach (['internal', 'external'] as $scope) {
            $wt = WashingTariff::resolve($customerId, $scope, $type, $size, $date);

            if (! $wt) {
                $out[$scope] = ['found' => false];
                continue;
            }

            // Convert the tariff rate to the estimate currency. Rates are normally
            // in USD; only USD tariffs are scaled by the USD→currency rate. A
            // tariff already in the estimate currency needs no conversion, and an
            // unknown cross-currency is left as-is rather than double-converted.
            $tariffCur = strtoupper($wt->currency ?: 'USD');
            if ($tariffCur === $estCur) {
                $factor = 1.0;
            } elseif ($tariffCur === 'USD') {
                $factor = $estCur === 'USD' ? 1.0 : $rate;
            } else {
                $factor = 1.0;
            }

            // Apply the configured minimum as a floor before conversion.
            $base = $wt->min_charge !== null
                ? max((float) $wt->rate, (float) $wt->min_charge)
                : (float) $wt->rate;

            $taxCode = $wt->taxCode ?: $wt->chargeCode?->taxCode;

            $out[$scope] = [
                'found'             => true,
                'washing_tariff_id' => $wt->id,
                'wash_scope'        => $scope,
                'label'             => $wt->scope_label . ' — ' . $wt->type_label,
                'unit_price'        => round($base * $factor, 2),
                'currency'          => $estCur,
                'charge_code_id'    => $wt->charge_code_id,
                'charge_code'       => $wt->chargeCode?->code,
                'tax_code_id'       => $taxCode?->id,
                'tax1_rate'         => (float) ($taxCode?->tax1_rate ?? 0),
                'tax2_rate'         => (float) ($taxCode?->tax2_rate ?? 0),
                'size'              => $wt->container_size,
            ];
        }

        return response()->json($out);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Compute per-line SSCL (Tax1) and VAT (Tax2) from each line's tax_code_id.
     * Tax1 applies to the net line amount; Tax2 applies to (net + Tax1).
     */
    private function calculateLineTotals(array $lineItems): array
    {
        $taxCodeIds = collect($lineItems)->pluck('tax_code_id')->filter()->unique()->values()->all();
        $taxCodes   = TaxCode::whereIn('id', $taxCodeIds)->get()->keyBy('id');

        $subtotal  = 0;
        $ssclAmt   = 0;
        $vatAmt    = 0;
        $lineMeta  = [];

        foreach ($lineItems as $item) {
            $net    = round((float)($item['qty'] ?? 0) * (float)($item['unit_price'] ?? 0), 2);
            $tc     = $taxCodes[$item['tax_code_id'] ?? 0] ?? null;
            $t1Rate = (float) ($tc?->tax1_rate ?? 0);
            $t2Rate = (float) ($tc?->tax2_rate ?? 0);
            $t1Amt  = round($net * $t1Rate / 100, 2);
            $t2Amt  = round(($net + $t1Amt) * $t2Rate / 100, 2);
            $gross  = round($net + $t1Amt + $t2Amt, 2);

            $subtotal += $net;
            $ssclAmt  += $t1Amt;
            $vatAmt   += $t2Amt;

            $lineMeta[] = [
                'line_amount'  => $net,
                'tax1_rate'    => $t1Rate,
                'tax2_rate'    => $t2Rate,
                'tax1_amount'  => $t1Amt,
                'tax2_amount'  => $t2Amt,
                'gross_amount' => $gross,
            ];
        }

        $subtotal = round($subtotal, 2);
        $ssclAmt  = round($ssclAmt,  2);
        $vatAmt   = round($vatAmt,   2);
        $taxAmt   = round($ssclAmt + $vatAmt, 2);

        return [
            'subtotal'         => $subtotal,
            'sscl_amount'      => $ssclAmt,
            'vat_amount'       => $vatAmt,
            'tax_amount'       => $taxAmt,
            'grand_total'      => round($subtotal + $taxAmt, 2),
            'effective_tax_pct'=> $subtotal > 0 ? round($taxAmt / $subtotal * 100, 4) : 0,
            'lines'            => $lineMeta,
        ];
    }

    private function generateEstimateNo(): string
    {
        return app(\App\Services\NumberSequenceService::class)->generate('estimate');
    }
}
