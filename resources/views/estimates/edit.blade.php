@extends('layouts.app')

@section('title', 'Edit Estimate — ' . $estimate->estimate_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('estimates.index') }}">Repair Estimates</a></li>
    <li class="breadcrumb-item"><a href="{{ route('estimates.show', $estimate) }}">{{ $estimate->estimate_no }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@push('styles')
<style>
    .estimate-line:hover { background: #f8f9fa; }
</style>
@endpush

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-tools me-2 text-primary"></i>Edit Estimate</h4>
        <p class="text-muted mb-0 small">Ref: <strong>{{ $estimate->estimate_no }}</strong>
            &nbsp;·&nbsp; {{ $estimate->estimate_date->format('d M Y') }}</p>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger py-2 small">
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('estimates.update', $estimate) }}" id="estimateForm">
    @csrf
    @method('PUT')

    <div class="row g-3">

        <!-- Main -->
        <div class="col-lg-8">

            <!-- Header Info -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Estimate Header
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Container Number</label>
                            <input type="text" class="form-control font-monospace"
                                   value="{{ $estimate->container_no }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Equipment Type</label>
                            <div class="d-flex flex-wrap gap-1 align-items-center" style="padding-top:.4rem;">
                                @if($estimate->equipmentType)
                                    <span class="badge bg-primary fw-bold" style="font-size:.8rem;letter-spacing:.5px;">
                                        {{ $estimate->equipmentType->eqt_code }}
                                    </span>
                                @endif
                                <span class="badge bg-light border text-dark">{{ $estimate->size }}'</span>
                                <span class="badge bg-info-subtle text-info">{{ $estimate->type_code }}</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Inquiry Ref.</label>
                            <input type="text" class="form-control"
                                   value="{{ $estimate->inquiry->inquiry_no ?? '—' }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer</label>
                            <input type="text" class="form-control"
                                   value="{{ $estimate->customer->name ?? '—' }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Estimate Date <span class="text-danger">*</span></label>
                            <input type="date" name="estimate_date" class="form-control"
                                   value="{{ old('estimate_date', $estimate->estimate_date->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Valid Until <span class="text-danger">*</span></label>
                            <input type="date" name="valid_until" class="form-control"
                                   value="{{ old('valid_until', $estimate->valid_until->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                            <select name="currency" class="form-select" required>
                                <option value="LKR" {{ old('currency', $estimate->currency) === 'LKR' ? 'selected' : '' }}>LKR — Sri Lankan Rupee</option>
                                <option value="USD" {{ old('currency', $estimate->currency) === 'USD' ? 'selected' : '' }}>USD — US Dollar</option>
                                <option value="SGD" {{ old('currency', $estimate->currency) === 'SGD' ? 'selected' : '' }}>SGD — Singapore Dollar</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Repair Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select" required>
                                <option value="normal"   {{ old('priority', $estimate->priority) === 'normal'   ? 'selected' : '' }}>Normal (7–14 days)</option>
                                <option value="urgent"   {{ old('priority', $estimate->priority) === 'urgent'   ? 'selected' : '' }}>Urgent (3–5 days)</option>
                                <option value="critical" {{ old('priority', $estimate->priority) === 'critical' ? 'selected' : '' }}>Critical (Next day)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="card content-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul me-2 text-primary"></i>Repair Line Items</span>
                    <button type="button" class="btn btn-sm btn-outline-success" id="getRateBtn" data-bs-toggle="modal" data-bs-target="#getRateModal">
                        <i class="bi bi-calculator me-1"></i>Get Rate
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addLine">
                        <i class="bi bi-plus-circle me-1"></i>Add Line
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="lineTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width:11%">MR Code</th>
                                    <th style="width:10%">Charge Code</th>
                                    <th style="width:17%">Description</th>
                                    <th style="width:14%">Repair Type</th>
                                    <th style="width:7%">Qty</th>
                                    <th style="width:10%">Unit Price</th>
                                    <th style="width:8%">Tax Code</th>
                                    <th style="width:11%">Net Amount</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="lineItems">
                                @foreach($estimate->lineItems as $i => $item)
                                <tr class="estimate-line">
                                    <td class="ps-3">
                                        <input type="hidden" name="line_items[{{ $i }}][id]"                value="{{ $item->id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][damage_id]"         value="{{ $item->damage_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][mr_tariff_rule_id]" value="{{ $item->mr_tariff_rule_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][location_code_id]"  value="{{ $item->location_code_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][damage_code_id]"    value="{{ $item->damage_code_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][repair_code_id]"    value="{{ $item->repair_code_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][material_code_id]"  value="{{ $item->material_code_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][cedex_code]"        value="{{ $item->cedex_code }}">
                                        <input type="hidden" name="line_items[{{ $i }}][repair_category_id]" value="{{ $item->repair_category_id }}">
                                        <input type="hidden" name="line_items[{{ $i }}][std_labor_hours]"   value="{{ $item->std_labor_hours ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][labor_rate]"        value="{{ $item->labor_rate ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][labor_amount]"      value="{{ $item->labor_amount ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][material_qty]"      value="{{ $item->material_qty ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][material_rate]"     value="{{ $item->material_rate ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][material_amount]"   value="{{ $item->material_amount ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][ancillary_amount]"  value="{{ $item->ancillary_amount ?? 0 }}">
                                        <input type="hidden" name="line_items[{{ $i }}][dim_length]"        value="{{ $item->dim_length ?? '' }}">
                                        <input type="hidden" name="line_items[{{ $i }}][dim_width]"         value="{{ $item->dim_width ?? '' }}">
                                        <input type="hidden" name="line_items[{{ $i }}][dim_uom]"           value="{{ $item->dim_uom ?? '' }}">
                                        <select name="line_items[{{ $i }}][component_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">— any —</option>
                                            @foreach($mrComponentCodes as $c)
                                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" {{ old("line_items.{$i}.component_code_id", $item->component_code_id) == $c->id ? 'selected' : '' }}>
                                                {{ $c->code }} — {{ $c->name }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="line_items[{{ $i }}][charge_code_id]" class="form-select form-select-sm charge-code-sel s2 s2-code">
                                            <option value="">— none —</option>
                                            @foreach($chargeCodes as $cc)
                                            <option value="{{ $cc->id }}"
                                                    data-code="{{ $cc->code }}"
                                                    data-name="{{ $cc->description }}"
                                                    data-tax1-rate="{{ $cc->taxCode?->tax1_rate ?? 0 }}"
                                                    data-tax2-rate="{{ $cc->taxCode?->tax2_rate ?? 0 }}"
                                                    data-tax-code-id="{{ $cc->tax_code_id ?? '' }}"
                                                    {{ old("line_items.{$i}.charge_code_id", $item->charge_code_id) == $cc->id ? 'selected' : '' }}>
                                                {{ $cc->code }} — {{ $cc->description }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="line_items[{{ $i }}][component]"
                                               class="form-control form-control-sm comp-desc"
                                               value="{{ old("line_items.{$i}.component", $item->component) }}"
                                               placeholder="Description">
                                    </td>
                                    <td>
                                        <select name="line_items[{{ $i }}][repair_type]" class="form-select form-select-sm" required>
                                            @foreach(['replace'=>'Replace','repair'=>'Repair','weld'=>'Weld','straighten'=>'Straighten','clean_and_treat'=>'Clean & Treat','paint'=>'Paint'] as $val => $lbl)
                                            <option value="{{ $val }}" {{ old("line_items.{$i}.repair_type", $item->repair_type) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="line_items[{{ $i }}][qty]"
                                               class="form-control form-control-sm qty"
                                               value="{{ old("line_items.{$i}.qty", $item->qty) }}"
                                               min="0.01" step="0.01" required>
                                    </td>
                                    <td>
                                        <input type="number" name="line_items[{{ $i }}][unit_price]"
                                               class="form-control form-control-sm unit-price"
                                               value="{{ old("line_items.{$i}.unit_price", $item->unit_price) }}"
                                               step="0.01" min="0" required>
                                    </td>
                                    <td>
                                        <select name="line_items[{{ $i }}][tax_code_id]" class="form-select form-select-sm tax-code-sel s2 s2-code">
                                            <option value="">— none —</option>
                                            @foreach($taxCodes as $tc)
                                            <option value="{{ $tc->id }}"
                                                    data-code="{{ $tc->code }}"
                                                    data-name="{{ $tc->code }} (SSCL {{ $tc->tax1_rate }}% + VAT {{ $tc->tax2_rate }}%)"
                                                    data-tax1-rate="{{ $tc->tax1_rate }}"
                                                    data-tax2-rate="{{ $tc->tax2_rate }}"
                                                    title="{{ $tc->code }} (SSCL {{ $tc->tax1_rate }}% + VAT {{ $tc->tax2_rate }}%)"
                                                    {{ old("line_items.{$i}.tax_code_id", $item->tax_code_id) == $tc->id ? 'selected' : '' }}>
                                                {{ $tc->code }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="text-end pe-2 small">
                                        <div class="fw-semibold line-net">
                                            {{ $estimate->currency }} {{ number_format($item->line_amount, 2) }}
                                        </div>
                                        <div style="font-size:.68rem; line-height:1.4; color:#6c757d;">
                                            @if(($item->tax1_amount ?? 0) > 0)
                                                <span class="line-sscl-amt">+SSCL {{ number_format($item->tax1_amount, 2) }}</span>
                                            @else
                                                <span class="line-sscl-amt"></span>
                                            @endif
                                            @if(($item->tax2_amount ?? 0) > 0)
                                                <span class="line-vat-amt"> +VAT {{ number_format($item->tax2_amount, 2) }}</span>
                                            @else
                                                <span class="line-vat-amt"></span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="pe-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-line">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="7" class="text-end fw-semibold pe-3">Subtotal:</td>
                                    <td class="fw-semibold text-end pe-2" id="subtotal">
                                        {{ $estimate->currency }} {{ number_format($estimate->subtotal, 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-end pe-3 small text-muted">
                                        SSCL <span id="ssclPctDisplay" class="text-muted"></span>:
                                    </td>
                                    <td class="text-end pe-2 small text-muted" id="totalSscl">
                                        {{ $estimate->currency }} {{ number_format($estimate->sscl_amount ?? 0, 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-end pe-3 small text-muted">
                                        VAT <span id="vatPctDisplay" class="text-muted"></span>:
                                    </td>
                                    <td class="text-end pe-2 small text-muted" id="totalVat">
                                        {{ $estimate->currency }} {{ number_format($estimate->vat_amount ?? 0, 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                                <tr class="table-primary">
                                    <td colspan="7" class="text-end fw-bold pe-3 fs-6">TOTAL:</td>
                                    <td class="fw-bold text-end pe-2 fs-6" id="grandTotal">
                                        {{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Terms -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-file-text me-2 text-primary"></i>Terms & Remarks
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Scope of Work</label>
                        <textarea name="scope_of_work" class="form-control" rows="3"
                                  placeholder="Describe the detailed scope of repair work…">{{ old('scope_of_work', $estimate->scope_of_work) }}</textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Terms &amp; Conditions</label>
                        <textarea name="terms" class="form-control" rows="3">{{ old('terms', $estimate->terms) }}</textarea>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Sidebar -->
        <div class="col-lg-4">

            <!-- Send Options -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-send me-2 text-primary"></i>Send Options
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Send To</label>
                        <input type="email" name="send_to_email" class="form-control form-control-sm"
                               value="{{ old('send_to_email', $estimate->send_to_email) }}"
                               placeholder="customer@email.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">CC</label>
                        <input type="email" name="send_cc_email" class="form-control form-control-sm"
                               value="{{ old('send_cc_email', $estimate->send_cc_email) }}"
                               placeholder="manager@email.com">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Email Message</label>
                        <textarea name="email_message" class="form-control form-control-sm" rows="3"
                                  placeholder="Brief message to the customer…">{{ old('email_message', $estimate->email_message) }}</textarea>
                    </div>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" name="attach_pdf" id="attachPdf"
                               value="1" {{ old('attach_pdf', $estimate->attach_pdf) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="attachPdf">Attach PDF estimate</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="attach_photos" id="attachPhotos"
                               value="1" {{ old('attach_photos', $estimate->attach_photos) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="attachPhotos">Attach inspection photos</label>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Save Changes
                </button>
                <a href="{{ route('estimates.show', $estimate) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
            </div>

        </div>

    </div>
</form>

{{-- ── Get Rate Modal ── --}}
<div class="modal fade" id="getRateModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title"><i class="bi bi-calculator me-2 text-success"></i>M&amp;R Tariff Rate Lookup</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" id="grSearchQ" class="form-control form-control-sm" placeholder="Search item code or description…">
                    </div>
                    <div class="col-md-3">
                        <select id="grOpFilter" class="form-select form-select-sm">
                            <option value="">All Operations</option>
                            <option value="straight">Straight</option>
                            <option value="insert">Insert</option>
                            <option value="section">Section</option>
                            <option value="replace">Replace</option>
                            <option value="weld">Weld</option>
                            <option value="remove">Remove</option>
                            <option value="paint">Paint</option>
                            <option value="resecure">Resecure</option>
                            <option value="free">Free / Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="grUnitFilter" class="form-select form-select-sm">
                            <option value="">All Units</option>
                            <option value="nos">NOS</option>
                            <option value="lift">LIFT</option>
                            <option value="sqft">SQFT</option>
                            <option value="inches">INCHES</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-primary w-100" id="grSearchBtn">
                            <i class="bi bi-search me-1"></i>Search
                        </button>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:320px; overflow-y:auto;">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-2" style="width:100px">Code</th>
                                <th style="width:90px">Operation</th>
                                <th>Description</th>
                                <th style="width:60px">Unit</th>
                                <th style="width:60px">Slabs</th>
                                <th style="width:90px">Qty</th>
                                <th style="width:70px">Labor Rate</th>
                                <th style="width:80px"></th>
                            </tr>
                        </thead>
                        <tbody id="grResultBody">
                            <tr><td colspan="8" class="text-center text-muted py-4">Search for tariff items above.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="grRateResult" class="d-none mt-3 p-3 border rounded bg-light">
                    <div class="row g-2 small">
                        <div class="col-md-2"><div class="text-muted">Labor Hours</div><div class="fw-bold" id="grLaborHrs">—</div></div>
                        <div class="col-md-2"><div class="text-muted">Labor Amount</div><div class="fw-bold" id="grLaborAmt">—</div></div>
                        <div class="col-md-2"><div class="text-muted">Material Cost</div><div class="fw-bold" id="grMaterialAmt">—</div></div>
                        <div class="col-md-2"><div class="text-muted">Total</div><div class="fw-bold text-success fs-6" id="grTotal">—</div></div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-success w-100" id="grApplyBtn">
                                <i class="bi bi-check-circle me-1"></i>Apply Rate to New Line
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    let lineIdx  = {{ $estimate->lineItems->count() }};
    const currency = '{{ $estimate->currency }}';

    function fmt(n)      { return currency + ' ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function fmtSmall(n) { return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function esc(str)    { return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    // MR component code options
    const mrCmpCodeOpts = @json($mrComponentCodes->map(fn($c) => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));

    // Charge code options with embedded Tax1/Tax2 rates
    @php
    $chargeCodeJson = $chargeCodes->map(fn($c) => [
        'id'          => $c->id,
        'code'        => $c->code,
        'description' => $c->description,
        'tax1_rate'   => $c->taxCode?->tax1_rate ?? 0,
        'tax2_rate'   => $c->taxCode?->tax2_rate ?? 0,
        'tax_code_id' => $c->tax_code_id,
    ]);
    @endphp
    const chargeCodeOpts = @json($chargeCodeJson);

    // Tax code options
    @php
    $taxCodeJson = $taxCodes->map(fn($tc) => [
        'id'        => $tc->id,
        'code'      => $tc->code,
        'tax1_rate' => $tc->tax1_rate,
        'tax2_rate' => $tc->tax2_rate,
    ]);
    @endphp
    const taxCodeOpts = @json($taxCodeJson);

    const resolveUrl = '{{ route("estimates.resolve-charge-code") }}';

    function buildCompSelect(name) {
        let opts = '<option value="">— any —</option>';
        mrCmpCodeOpts.forEach(o => { opts += `<option value="${o.id}" data-code="${esc(o.code)}" data-name="${esc(o.name)}">${esc(o.code)} — ${esc(o.name)}</option>`; });
        return `<select name="${name}" class="form-select form-select-sm mb-1 s2 s2-code">${opts}</select>`;
    }

    function buildChargeCodeSelect(name) {
        let opts = '<option value="">— none —</option>';
        chargeCodeOpts.forEach(c => {
            opts += `<option value="${c.id}" data-code="${esc(c.code)}" data-name="${esc(c.description)}" data-tax1-rate="${c.tax1_rate}" data-tax2-rate="${c.tax2_rate}" data-tax-code-id="${c.tax_code_id ?? ''}">${esc(c.code)} — ${esc(c.description)}</option>`;
        });
        return `<select name="${name}" class="form-select form-select-sm charge-code-sel s2 s2-code">${opts}</select>`;
    }

    function buildTaxCodeSelect(name) {
        let opts = '<option value="">— none —</option>';
        taxCodeOpts.forEach(tc => {
            const fullLabel = `${tc.code} (SSCL ${tc.tax1_rate}% + VAT ${tc.tax2_rate}%)`;
            opts += `<option value="${tc.id}" data-code="${esc(tc.code)}" data-name="${esc(fullLabel)}" data-tax1-rate="${tc.tax1_rate}" data-tax2-rate="${tc.tax2_rate}" title="${esc(fullLabel)}">${esc(tc.code)}</option>`;
        });
        return `<select name="${name}" class="form-select form-select-sm tax-code-sel s2 s2-code">${opts}</select>`;
    }

    function initLineSelects(tr) {
        $(tr).find('select.s2').each(function() { window.initS2Code($(this), { width: '100%' }); });
    }

    function applyChargeToRow(row, chargeCodeId, taxCodeId) {
        const chargeSel  = row.querySelector('.charge-code-sel');
        const taxCodeSel = row.querySelector('.tax-code-sel');
        if (chargeSel) {
            chargeSel.value = chargeCodeId || '';
            if (typeof $ !== 'undefined') $(chargeSel).trigger('change.select2');
        }
        if (taxCodeSel) {
            taxCodeSel.value = taxCodeId ?? '';
            if (typeof $ !== 'undefined') $(taxCodeSel).trigger('change.select2');
        }
        recalculate();
    }

    function recalculate() {
        let subtotal = 0, ssclTotal = 0, vatTotal = 0;
        let ssclRates = new Set(), vatRates = new Set();

        document.querySelectorAll('.estimate-line').forEach(row => {
            const qty   = parseFloat(row.querySelector('.qty')?.value       || 0);
            const price = parseFloat(row.querySelector('.unit-price')?.value || 0);
            const net   = qty * price;

            const taxSel = row.querySelector('.tax-code-sel');
            const selOpt = taxSel?.selectedOptions[0];
            const t1Rate = parseFloat(selOpt?.dataset.tax1Rate || 0);
            const t2Rate = parseFloat(selOpt?.dataset.tax2Rate || 0);

            const t1 = net * (t1Rate / 100);
            const t2 = (net + t1) * (t2Rate / 100);

            subtotal  += net;
            ssclTotal += t1;
            vatTotal  += t2;

            if (t1Rate > 0) ssclRates.add(t1Rate);
            if (t2Rate > 0) vatRates.add(t2Rate);

            const netEl = row.querySelector('.line-net');
            const sEl   = row.querySelector('.line-sscl-amt');
            const vEl   = row.querySelector('.line-vat-amt');
            if (netEl) netEl.textContent = fmt(net);
            if (sEl)   sEl.textContent  = t1 > 0 ? '+SSCL ' + fmtSmall(t1) : '';
            if (vEl)   vEl.textContent  = t2 > 0 ? ' +VAT ' + fmtSmall(t2) : '';
        });

        document.getElementById('subtotal').textContent   = fmt(subtotal);
        document.getElementById('totalSscl').textContent  = fmt(ssclTotal);
        document.getElementById('totalVat').textContent   = fmt(vatTotal);
        document.getElementById('grandTotal').textContent  = fmt(subtotal + ssclTotal + vatTotal);

        document.getElementById('ssclPctDisplay').textContent = ssclRates.size === 1 ? `(${[...ssclRates][0]}%)` : '';
        document.getElementById('vatPctDisplay').textContent  = vatRates.size  === 1 ? `(${[...vatRates][0]}%)`  : '';
    }

    // Change events: component code → auto-fill + AJAX resolve; charge code → set tax code; tax code → recalc
    document.getElementById('lineItems').addEventListener('change', function (e) {
        const sel = e.target;
        const row = sel.closest('tr');
        if (!row) return;

        if (sel.name?.includes('[component_code_id]')) {
            const descInput = row.querySelector('.comp-desc');
            if (descInput && !descInput.value.trim()) {
                const opt = mrCmpCodeOpts.find(o => o.id == sel.value);
                if (opt) descInput.value = opt.name;
            }
            if (sel.value) {
                const repairCodeId = row.querySelector('[name*="[repair_code_id]"]')?.value || '';
                const url = resolveUrl + '?component_code_id=' + sel.value + (repairCodeId ? '&repair_code_id=' + repairCodeId : '');
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(data => {
                        if (data.found) {
                            applyChargeToRow(row, data.charge_code_id, data.tax_code_id);
                        }
                    })
                    .catch(() => {});
            }
        }

        if (sel.classList.contains('charge-code-sel')) {
            const opt = sel.selectedOptions[0];
            applyChargeToRow(row, opt?.value || '', opt?.dataset.taxCodeId || '');
        }

        if (sel.classList.contains('tax-code-sel')) {
            recalculate();
        }
    });

    document.getElementById('lineTable').addEventListener('input', recalculate);

    // Add new row
    document.getElementById('addLine').addEventListener('click', function () {
        const tbody = document.getElementById('lineItems');
        const i = lineIdx++;
        tbody.insertAdjacentHTML('beforeend', `
            <tr class="estimate-line js-new-line">
                <td class="ps-3">
                    ${buildCompSelect(`line_items[${i}][component_code_id]`)}
                    <input type="hidden" name="line_items[${i}][damage_id]"         value="">
                    <input type="hidden" name="line_items[${i}][mr_tariff_rule_id]" value="">
                    <input type="hidden" name="line_items[${i}][location_code_id]"  value="">
                    <input type="hidden" name="line_items[${i}][damage_code_id]"    value="">
                    <input type="hidden" name="line_items[${i}][repair_code_id]"    value="">
                    <input type="hidden" name="line_items[${i}][material_code_id]"  value="">
                    <input type="hidden" name="line_items[${i}][cedex_code]"        value="">
                    <input type="hidden" name="line_items[${i}][repair_category_id]" value="">
                    <input type="hidden" name="line_items[${i}][std_labor_hours]"   value="0">
                    <input type="hidden" name="line_items[${i}][labor_rate]"        value="0">
                    <input type="hidden" name="line_items[${i}][labor_amount]"      value="0">
                    <input type="hidden" name="line_items[${i}][material_qty]"      value="0">
                    <input type="hidden" name="line_items[${i}][material_rate]"     value="0">
                    <input type="hidden" name="line_items[${i}][material_amount]"   value="0">
                    <input type="hidden" name="line_items[${i}][ancillary_amount]"  value="0">
                    <input type="hidden" name="line_items[${i}][dim_length]"        value="">
                    <input type="hidden" name="line_items[${i}][dim_width]"         value="">
                    <input type="hidden" name="line_items[${i}][dim_uom]"           value="">
                </td>
                <td>${buildChargeCodeSelect(`line_items[${i}][charge_code_id]`)}</td>
                <td><input type="text" name="line_items[${i}][component]" class="form-control form-control-sm comp-desc" placeholder="Description"></td>
                <td>
                    <select name="line_items[${i}][repair_type]" class="form-select form-select-sm" required>
                        <option value="replace">Replace</option>
                        <option value="repair" selected>Repair</option>
                        <option value="weld">Weld</option>
                        <option value="straighten">Straighten</option>
                        <option value="clean_and_treat">Clean &amp; Treat</option>
                        <option value="paint">Paint</option>
                    </select>
                </td>
                <td><input type="number" name="line_items[${i}][qty]"        class="form-control form-control-sm qty"        value="1"    min="0.01" step="0.01" required></td>
                <td><input type="number" name="line_items[${i}][unit_price]" class="form-control form-control-sm unit-price" value="0.00" step="0.01" min="0"   required></td>
                <td>${buildTaxCodeSelect(`line_items[${i}][tax_code_id]`)}</td>
                <td class="text-end pe-2 small">
                    <div class="fw-semibold line-net">${currency} 0.00</div>
                    <div style="font-size:.68rem; line-height:1.4; color:#6c757d;">
                        <span class="line-sscl-amt"></span>
                        <span class="line-vat-amt"></span>
                    </div>
                </td>
                <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button></td>
            </tr>
        `);
        initLineSelects(tbody.lastElementChild);
        recalculate();
    });

    document.getElementById('lineItems').addEventListener('click', function (e) {
        if (e.target.closest('.remove-line')) {
            if (document.querySelectorAll('.estimate-line').length > 1) {
                e.target.closest('.estimate-line').remove();
                recalculate();
            }
        }
    });

    // Initialize Select2 on all Blade-rendered line rows
    $(function () {
        document.querySelectorAll('#lineItems .estimate-line').forEach(function (row) {
            initLineSelects(row);
        });
    });

    // Initial calculation from Blade-rendered rows
    recalculate();

    // ── Get Rate Modal ────────────────────────────────────────────────────────
    (function () {
        const searchBtn  = document.getElementById('grSearchBtn');
        const resultBody = document.getElementById('grResultBody');
        const rateResult = document.getElementById('grRateResult');
        const applyBtn   = document.getElementById('grApplyBtn');
        let   selectedItem = null;
        let   selectedRate = null;
        const opColors = {
            straight:'info', insert:'success', section:'warning',
            replace:'danger', weld:'secondary', remove:'dark',
            paint:'primary', resecure:'info', free:'secondary'
        };

        function fetchItems() {
            const q  = document.getElementById('grSearchQ').value;
            const op = document.getElementById('grOpFilter').value;
            const ut = document.getElementById('grUnitFilter').value;
            const params = new URLSearchParams();
            if (q)  params.set('q', q);
            if (op) params.set('operation_type', op);
            if (ut) params.set('unit_type', ut);

            resultBody.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
            rateResult.classList.add('d-none');

            fetch('{{ route("masters.mr-tariff.item-search") }}?' + params)
                .then(r => r.json())
                .then(data => renderItems(data.items))
                .catch(() => { resultBody.innerHTML = '<tr><td colspan="8" class="text-danger text-center py-3">Failed to load items.</td></tr>'; });
        }

        const YARD_DIM_UOM = '{{ $dimUom ?? "ft_in" }}';
        const DIM_UOM_LABEL = { ft_in: 'ft', cm: 'cm', m: 'm' }[YARD_DIM_UOM] || 'ft';

        function dimsToQty(unitType, dimL, dimW) {
            dimL = parseFloat(dimL) || 0;
            dimW = parseFloat(dimW) || 0;
            if (unitType === 'sqft') {
                const area = dimL * dimW;
                if (YARD_DIM_UOM === 'cm')  return area / 929.0304;
                if (YARD_DIM_UOM === 'm')   return area / 0.09290304;
                return area;
            }
            if (unitType === 'inches') {
                if (YARD_DIM_UOM === 'cm')  return dimL / 2.54;
                if (YARD_DIM_UOM === 'm')   return dimL / 0.0254;
                return dimL * 12;
            }
            return null;
        }

        function dimQtyCell(unitType) {
            if (unitType === 'sqft') {
                return `<div class="d-flex align-items-center gap-1 flex-wrap" onclick="event.stopPropagation()">
                    <input type="number" class="form-control form-control-sm gr-dim-l" placeholder="L" min="0.01" step="0.01" style="width:58px" title="Length (${DIM_UOM_LABEL})">
                    <span class="text-muted small">×</span>
                    <input type="number" class="form-control form-control-sm gr-dim-w" placeholder="W" min="0.01" step="0.01" style="width:58px" title="Width (${DIM_UOM_LABEL})">
                    <span class="text-muted" style="font-size:.72rem;">${DIM_UOM_LABEL}</span>
                    <input type="hidden" class="gr-qty" value="1">
                    <div class="text-primary" style="font-size:.72rem;white-space:nowrap;" data-dim-display>—&nbsp;sqft</div>
                </div>`;
            }
            if (unitType === 'inches') {
                return `<div class="d-flex align-items-center gap-1" onclick="event.stopPropagation()">
                    <input type="number" class="form-control form-control-sm gr-dim-l" placeholder="Length" min="0.01" step="0.01" style="width:72px" title="Length (${DIM_UOM_LABEL})">
                    <span class="text-muted" style="font-size:.72rem;">${DIM_UOM_LABEL}</span>
                    <input type="hidden" class="gr-qty" value="1">
                    <div class="text-primary" style="font-size:.72rem;white-space:nowrap;" data-dim-display>—&nbsp;in</div>
                </div>`;
            }
            return `<input type="number" class="form-control form-control-sm gr-qty" value="1" min="0.01" step="0.01" style="width:70px" onclick="event.stopPropagation()">`;
        }

        function renderItems(items) {
            if (!items.length) {
                resultBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No items found.</td></tr>';
                return;
            }
            resultBody.innerHTML = items.map(item => {
                const color = opColors[item.operation_type] || 'secondary';
                return `<tr data-item-id="${item.id}" data-item-unit="${item.unit_type}" data-item-desc="${item.description}" style="cursor:pointer;">
                    <td class="ps-2 font-monospace small">${item.tariff_code || '—'}</td>
                    <td><span class="badge bg-${color}-subtle text-${color} border text-uppercase small">${item.operation_type}</span></td>
                    <td class="small">${item.description}</td>
                    <td class="small text-muted">${item.unit_type.toUpperCase()}</td>
                    <td class="text-center small">${item.slab_count}</td>
                    <td>${dimQtyCell(item.unit_type)}</td>
                    <td><input type="number" class="form-control form-control-sm gr-labor-rate" value="0" min="0" step="0.01" style="width:65px" placeholder="Rate" onclick="event.stopPropagation()"></td>
                    <td><button type="button" class="btn btn-xs btn-outline-success gr-calc-btn" onclick="event.stopPropagation()">
                        <i class="bi bi-calculator"></i> Calc
                    </button></td>
                </tr>`;
            }).join('');

            resultBody.addEventListener('input', function (e) {
                const inp = e.target;
                if (!inp.classList.contains('gr-dim-l') && !inp.classList.contains('gr-dim-w')) return;
                const row  = inp.closest('tr');
                const unit = row?.dataset.itemUnit;
                const dimL = parseFloat(row.querySelector('.gr-dim-l')?.value) || 0;
                const dimW = parseFloat(row.querySelector('.gr-dim-w')?.value) || 0;
                const computed = dimsToQty(unit, dimL, dimW);
                if (computed !== null && computed > 0) {
                    const qtyHidden = row.querySelector('.gr-qty');
                    if (qtyHidden) qtyHidden.value = computed.toFixed(4);
                    const display = row.querySelector('[data-dim-display]');
                    if (display) display.textContent = computed.toFixed(3) + (unit === 'sqft' ? ' sqft' : ' in');
                }
            });

            resultBody.querySelectorAll('.gr-calc-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const row = this.closest('tr');
                    const itemId    = row.dataset.itemId;
                    const qty       = parseFloat(row.querySelector('.gr-qty').value) || 1;
                    const laborRate = parseFloat(row.querySelector('.gr-labor-rate').value) || 0;
                    const custId    = document.querySelector('[name="customer_id"]')?.value || '';

                    const params = new URLSearchParams({ item_id: itemId, qty, labor_rate: laborRate });
                    if (custId) params.set('customer_id', custId);

                    fetch('{{ route("masters.mr-tariff.rate-lookup") }}?' + params)
                        .then(r => r.json())
                        .then(result => {
                            const dimL = parseFloat(row.querySelector('.gr-dim-l')?.value) || null;
                            const dimW = parseFloat(row.querySelector('.gr-dim-w')?.value) || null;
                            selectedItem = {
                                id: itemId, desc: row.dataset.itemDesc,
                                unit: row.dataset.itemUnit, qty, laborRate,
                                dimL, dimW,
                                dimUom: (dimL && (row.dataset.itemUnit === 'sqft' || row.dataset.itemUnit === 'inches')) ? YARD_DIM_UOM : null,
                            };
                            selectedRate = result;
                            document.getElementById('grLaborHrs').textContent  = result.labor_hours.toFixed(3) + ' hrs';
                            document.getElementById('grLaborAmt').textContent  = result.labor_amount.toFixed(2);
                            document.getElementById('grMaterialAmt').textContent = result.material_cost.toFixed(2);
                            document.getElementById('grTotal').textContent     = result.total.toFixed(2);
                            rateResult.classList.remove('d-none');
                        })
                        .catch(() => alert('Rate lookup failed.'));
                });
            });
        }

        searchBtn.addEventListener('click', fetchItems);
        document.getElementById('grSearchQ').addEventListener('keydown', e => { if (e.key === 'Enter') fetchItems(); });

        applyBtn.addEventListener('click', function () {
            if (!selectedItem || !selectedRate) return;
            const tbody = document.getElementById('lineItems');
            const i = lineIdx++;
            tbody.insertAdjacentHTML('beforeend', `<tr class="estimate-line">
                <td class="ps-2 text-center"><span class="badge bg-light text-muted border"><i class="bi bi-pencil"></i></span></td>
                <td class="ps-1">
                    <select name="line_items[${i}][component_code_id]" class="form-select form-select-sm code-sel"></select>
                    <input type="hidden" name="line_items[${i}][damage_id]" value="">
                    <input type="hidden" name="line_items[${i}][mr_tariff_rule_id]" value="">
                    <input type="hidden" name="line_items[${i}][location_code_id]" value="">
                    <input type="hidden" name="line_items[${i}][damage_code_id]" value="">
                    <input type="hidden" name="line_items[${i}][repair_code_id]" value="">
                    <input type="hidden" name="line_items[${i}][material_code_id]" value="">
                    <input type="hidden" name="line_items[${i}][cedex_code]" value="">
                    <input type="hidden" name="line_items[${i}][std_labor_hours]" value="${selectedRate.labor_hours}">
                    <input type="hidden" name="line_items[${i}][labor_rate]" value="${selectedItem.laborRate}">
                    <input type="hidden" name="line_items[${i}][labor_amount]" value="${selectedRate.labor_amount}">
                    <input type="hidden" name="line_items[${i}][material_qty]" value="1">
                    <input type="hidden" name="line_items[${i}][material_rate]" value="${selectedRate.material_cost}">
                    <input type="hidden" name="line_items[${i}][material_amount]" value="${selectedRate.material_cost}">
                    <input type="hidden" name="line_items[${i}][ancillary_amount]" value="0">
                    <input type="hidden" name="line_items[${i}][dim_length]"     value="${selectedItem.dimL ?? ''}">
                    <input type="hidden" name="line_items[${i}][dim_width]"      value="${selectedItem.dimW ?? ''}">
                    <input type="hidden" name="line_items[${i}][dim_uom]"        value="${selectedItem.dimUom ?? ''}">
                </td>
                <td><select name="line_items[${i}][charge_code_id]" class="form-select form-select-sm charge-code-sel"></select></td>
                <td><input type="text" name="line_items[${i}][component]" class="form-control form-control-sm comp-desc" value="${selectedItem.desc}"></td>
                <td><select name="line_items[${i}][repair_type]" class="form-select form-select-sm">
                    <option value="repair" selected>Repair</option>
                    <option value="cleaning">Cleaning</option>
                    <option value="paint">Paint</option>
                    <option value="replacement">Replacement</option>
                    <option value="other">Other</option>
                </select></td>
                <td><input type="number" name="line_items[${i}][qty]" class="form-control form-control-sm qty" value="${selectedItem.qty}" min="0.01" step="0.01"></td>
                <td><input type="number" name="line_items[${i}][unit_price]" class="form-control form-control-sm unit-price" value="${selectedRate.total}" min="0" step="0.01"></td>
                <td><select name="line_items[${i}][tax_code_id]" class="form-select form-select-sm tax-code-sel"></select></td>
                <td class="text-end pe-2 small"><div class="fw-semibold line-net">${currency} 0.00</div></td>
                <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button></td>
            </tr>`);
            initLineSelects(tbody.lastElementChild);
            recalculate();
            bootstrap.Modal.getInstance(document.getElementById('getRateModal'))?.hide();
            const addedRow = tbody.lastElementChild;
            addedRow.style.backgroundColor = '#d1fae5';
            setTimeout(() => { addedRow.style.backgroundColor = ''; }, 1400);
        });
    })();
})();
</script>
@endpush
