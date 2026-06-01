@extends('layouts.app')

@section('title', 'New Repair Estimate')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('estimates.index') }}">Repair Estimates</a></li>
    <li class="breadcrumb-item active">New Estimate</li>
@endsection

@push('styles')
<style>
    .estimate-line:hover { background: #f8f9fa; }
    .source-badge { font-size: .65rem; }
</style>
@endpush

@section('content')

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-tools me-2 text-primary"></i>New Repair Estimate</h4>
        <p class="text-muted mb-0 small">Generate a repair cost estimate
            @if($selectedInquiry) from survey <strong>{{ $selectedInquiry->inquiry_no }}</strong> @endif
        </p>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show py-2 small">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('estimates.store') }}" id="estimateForm">
    @csrf

    <div class="row g-3">

        {{-- ═══ LEFT: main content ═══ --}}
        <div class="col-lg-8">

            {{-- Header Info --}}
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Estimate Header
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Container Number</label>
                            <input type="text" class="form-control font-monospace"
                                   value="{{ $selectedInquiry->container_no ?? ($selectedContainer->container_no ?? '') }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Survey / Inquiry Ref.</label>
                            <input type="text" class="form-control"
                                   value="{{ $selectedInquiry->inquiry_no ?? '—' }}" readonly>
                            <input type="hidden" name="inquiry_id" value="{{ $selectedInquiry->id ?? '' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Customer</label>
                            <input type="text" class="form-control"
                                   value="{{ $selectedInquiry->customer->name ?? ($selectedContainer->customer->name ?? '—') }}" readonly>
                            <input type="hidden" name="customer_id"
                                   value="{{ $selectedInquiry->customer_id ?? ($selectedContainer->customer_id ?? '') }}">
                            <input type="hidden" name="container_id"
                                   value="{{ $selectedInquiry->container_id ?? ($selectedContainer->id ?? '') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Equipment Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2 align-items-center">
                                <select name="equipment_type_id" id="eqtSelect" class="form-select s2-code" required>
                                    <option value="">— Select Equipment Type —</option>
                                    @foreach($equipmentTypes as $eqt)
                                    <option value="{{ $eqt->id }}"
                                            data-code="{{ $eqt->eqt_code }}"
                                            data-name="{{ $eqt->description }}"
                                            data-size="{{ $eqt->size }}"
                                            data-type="{{ $eqt->type_code }}"
                                            @if(in_array($eqt->type_code, ['RF','RH'])) data-chip-class="s2-code-chip s2-chip-reefer" @endif
                                            {{ old('equipment_type_id', $selectedInquiry->equipment_type_id ?? ($selectedContainer->equipment_type_id ?? '')) == $eqt->id ? 'selected' : '' }}>
                                        {{ $eqt->eqt_code }} — {{ $eqt->description }}
                                    </option>
                                    @endforeach
                                </select>
                                <span id="eqtSizeBadge" class="badge bg-light border text-dark text-nowrap d-none"></span>
                                <span id="eqtTypeBadge" class="badge bg-info-subtle text-info text-nowrap d-none"></span>
                            </div>
                            <input type="hidden" name="size" id="eqtSize">
                            <input type="hidden" name="type_code" id="eqtTypeCode">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Estimate Date <span class="text-danger">*</span></label>
                            <input type="date" name="estimate_date" class="form-control"
                                   value="{{ old('estimate_date', date('Y-m-d')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Valid Until <span class="text-danger">*</span></label>
                            <input type="date" name="valid_until" class="form-control"
                                   value="{{ old('valid_until', date('Y-m-d', strtotime('+30 days'))) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                            <select name="currency" class="form-select" required>
                                <option value="LKR" {{ old('currency','LKR')==='LKR'?'selected':'' }}>LKR</option>
                                <option value="USD" {{ old('currency')==='USD'?'selected':'' }}>USD</option>
                                <option value="SGD" {{ old('currency')==='SGD'?'selected':'' }}>SGD</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select" required>
                                <option value="normal"   {{ old('priority','normal')==='normal'?'selected':'' }}>Normal</option>
                                <option value="urgent"   {{ old('priority')==='urgent'?'selected':'' }}>Urgent</option>
                                <option value="critical" {{ old('priority')==='critical'?'selected':'' }}>Critical</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Line Items --}}
            <div class="card content-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul me-2 text-primary"></i>Repair Line Items</span>
                    <div class="d-flex gap-2">
                        @if($selectedInquiry && $selectedInquiry->damages->isNotEmpty())
                        <button type="button" class="btn btn-sm btn-warning" id="importDamagesBtn"
                                data-url="{{ route('estimates.import-damages', $selectedInquiry) }}"
                                data-count="{{ $selectedInquiry->damages->count() }}">
                            <i class="bi bi-download me-1"></i>Import {{ $selectedInquiry->damages->count() }} Damage(s) as Lines
                        </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addLine">
                            <i class="bi bi-plus-circle me-1"></i>Add Line
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="importAlert" class="alert alert-success py-2 small mx-3 mt-2 d-none">
                        <i class="bi bi-check-circle me-1"></i><span id="importAlertText"></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="lineTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width:32px;"></th>
                                    <th style="width:11%">MR Code</th>
                                    <th style="width:11%">Charge Code</th>
                                    <th style="width:14%">Description</th>
                                    <th style="width:13%">Repair Type</th>
                                    <th style="width:6%">Qty</th>
                                    <th style="width:9%">Unit Price</th>
                                    <th style="width:8%">Tax Code</th>
                                    <th style="width:9%" class="text-end pe-2">Net Amount</th>
                                    <th style="width:36px;"></th>
                                </tr>
                            </thead>
                            <tbody id="lineItems">
                                {{-- Rows injected by JS --}}
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="8" class="text-end fw-semibold pe-3 small">Subtotal:</td>
                                    <td class="fw-semibold text-end pe-2 small" id="subtotal">0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="8" class="text-end pe-3 small text-muted">
                                        SSCL <span id="ssclPctDisplay" class="text-muted"></span>:
                                    </td>
                                    <td class="text-end pe-2 small text-muted" id="totalSscl">0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="8" class="text-end pe-3 small text-muted">
                                        VAT <span id="vatPctDisplay" class="text-muted"></span>:
                                    </td>
                                    <td class="text-end pe-2 small text-muted" id="totalVat">0.00</td>
                                    <td></td>
                                </tr>
                                <tr class="table-primary">
                                    <td colspan="8" class="text-end fw-bold pe-3">TOTAL:</td>
                                    <td class="fw-bold text-end pe-2" id="grandTotal">0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Terms --}}
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-file-text me-2 text-primary"></i>Terms &amp; Remarks
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Scope of Work</label>
                        <textarea name="scope_of_work" class="form-control" rows="3"
                                  placeholder="Describe the detailed scope of repair work…">{{ old('scope_of_work') }}</textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Terms &amp; Conditions</label>
                        <textarea name="terms" class="form-control" rows="3"
                                  placeholder="Payment terms, validity clauses, etc…">{{ old('terms', "1. This estimate is valid for 30 days from the date of issue.\n2. Prices are subject to change based on actual damage found during repair.\n3. Additional damages discovered during repair will be notified and re-estimated.\n4. Payment is due within 30 days of invoice.") }}</textarea>
                    </div>
                </div>
            </div>

        </div>

        {{-- ═══ RIGHT: sidebar ═══ --}}
        <div class="col-lg-4">

            {{-- Damage Summary (dynamic) --}}
            @if($selectedInquiry)
            <div class="card content-card mb-3">
                <div class="card-header bg-warning-subtle d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Survey Damages</span>
                    <span class="badge bg-warning text-dark">{{ $selectedInquiry->damages->count() }}</span>
                </div>
                @if($selectedInquiry->damages->isEmpty())
                <div class="card-body text-center text-muted small py-3">
                    <i class="bi bi-shield-check fs-4 d-block mb-1 text-success"></i>No damages recorded on this survey.
                </div>
                @else
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        @foreach($selectedInquiry->damages as $dmg)
                        @php
                            $sc = match($dmg->severity ?? 'minor') {
                                'severe'   => 'danger',
                                'moderate' => 'warning',
                                default    => 'success',
                            };
                        @endphp
                        <li class="list-group-item py-2 px-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="fw-semibold">
                                        {{ $dmg->locationCode?->name ?? ucwords(str_replace('_', ' ', $dmg->location ?? '—')) }}
                                    </span>
                                    @if($dmg->componentCode)
                                        <span class="badge bg-primary-subtle text-primary border font-monospace ms-1 source-badge">{{ $dmg->componentCode->code }}</span>
                                    @endif
                                    <div class="text-muted" style="font-size:.78rem;">
                                        {{ $dmg->damageCode?->name ?? ucwords(str_replace('_', ' ', $dmg->damage_type ?? '')) }}
                                        @if($dmg->repairCode) → {{ $dmg->repairCode->name }} @endif
                                    </div>
                                </div>
                                <span class="badge bg-{{ $sc }}-subtle text-{{ $sc }} ms-2 flex-shrink-0">
                                    {{ ucfirst($dmg->severity ?? '—') }}
                                </span>
                            </div>
                            @if($dmg->cedex_code)
                            <div class="mt-1">
                                <span class="badge bg-dark text-white source-badge">{{ $dmg->cedex_code }}</span>
                            </div>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @if($selectedInquiry->damages->isNotEmpty())
                <div class="card-footer bg-white py-2">
                    <button type="button" class="btn btn-warning btn-sm w-100" id="importDamagesSideBtn"
                            data-url="{{ route('estimates.import-damages', $selectedInquiry) }}"
                            data-count="{{ $selectedInquiry->damages->count() }}">
                        <i class="bi bi-download me-1"></i>Import All as Lines
                    </button>
                </div>
                @endif
                @endif
            </div>
            @else
            <div class="card content-card mb-3 border-0 bg-light">
                <div class="card-body text-center text-muted small py-3">
                    <i class="bi bi-search fs-4 d-block mb-1"></i>
                    No survey linked. Line items must be entered manually.
                </div>
            </div>
            @endif

            {{-- Send Options --}}
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-send me-2 text-primary"></i>Send Options
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Send To</label>
                        <input type="email" name="send_to_email" class="form-control form-control-sm"
                               value="{{ old('send_to_email', $selectedInquiry->customer->email ?? '') }}"
                               placeholder="customer@email.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">CC</label>
                        <input type="email" name="send_cc_email" class="form-control form-control-sm"
                               value="{{ old('send_cc_email') }}" placeholder="manager@email.com">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Email Message</label>
                        <textarea name="email_message" class="form-control form-control-sm" rows="3"
                                  placeholder="Brief message to the customer…">{{ old('email_message') }}</textarea>
                    </div>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" name="attach_pdf" id="attachPdf"
                               {{ old('attach_pdf', '1') ? 'checked' : '' }} value="1">
                        <label class="form-check-label small" for="attachPdf">Attach PDF estimate</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="attach_photos" id="attachPhotos"
                               {{ old('attach_photos') ? 'checked' : '' }} value="1">
                        <label class="form-check-label small" for="attachPhotos">Attach inspection photos</label>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Save Estimate (Draft)
                </button>
                <a href="{{ route('estimates.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </a>
            </div>

        </div>

    </div>
</form>

@endsection

@push('scripts')
<script>
(function () {
    // ── Equipment Type badges ──────────────────────────────────────────────
    const eqtSel    = document.getElementById('eqtSelect');
    const eqtSize   = document.getElementById('eqtSize');
    const eqtType   = document.getElementById('eqtTypeCode');
    const sizeBadge = document.getElementById('eqtSizeBadge');
    const typeBadge = document.getElementById('eqtTypeBadge');

    function applyEqt(opt) {
        if (!opt?.value) {
            eqtSize.value = eqtType.value = '';
            sizeBadge.classList.add('d-none');
            typeBadge.classList.add('d-none');
            return;
        }
        const isReefer = ['RF', 'RH'].includes(opt.dataset.type);
        eqtSize.value = opt.dataset.size;
        eqtType.value = opt.dataset.type;
        sizeBadge.textContent = opt.dataset.size + "'";
        typeBadge.textContent = opt.dataset.type;
        typeBadge.className = 'badge text-nowrap' + (isReefer ? ' badge-reefer' : ' bg-info-subtle text-info');
        sizeBadge.classList.remove('d-none');
        typeBadge.classList.remove('d-none');
    }

    eqtSel.addEventListener('change', () => applyEqt(eqtSel.selectedOptions[0]));
    if (eqtSel.value) applyEqt(eqtSel.selectedOptions[0]);

    // ── Line counter ───────────────────────────────────────────────────────
    let lineIdx = 0;

    const REPAIR_TYPES = [
        ['replace',        'Replace'],
        ['repair',         'Repair'],
        ['weld',           'Weld'],
        ['straighten',     'Straighten'],
        ['clean_and_treat','Clean & Treat'],
        ['paint',          'Paint'],
    ];

    function repairTypeOptions(selected) {
        return REPAIR_TYPES.map(([val, label]) =>
            `<option value="${val}"${val === selected ? ' selected' : ''}>${label}</option>`
        ).join('');
    }

    // MR component code options
    const mrCmpCodeOpts = @json($mrComponentCodes->map(fn($c) => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));

    // Charge code options — embed tax1/tax2 rates for cascade calculation
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

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function buildCodeSelect(name, options, selectedId) {
        let html = `<select name="${name}" class="form-select form-select-sm mb-1 s2 s2-code"><option value="">— any —</option>`;
        options.forEach(o => {
            html += `<option value="${o.id}" data-code="${esc(o.code)}" data-name="${esc(o.name)}"${o.id == selectedId ? ' selected' : ''}>${esc(o.code)} — ${esc(o.name)}</option>`;
        });
        return html + '</select>';
    }

    function buildChargeCodeSelect(name, selectedId) {
        let opts = '<option value="">— none —</option>';
        chargeCodeOpts.forEach(c => {
            opts += `<option value="${c.id}" data-code="${esc(c.code)}" data-name="${esc(c.description)}" data-tax1-rate="${c.tax1_rate}" data-tax2-rate="${c.tax2_rate}" data-tax-code-id="${c.tax_code_id ?? ''}"${c.id == selectedId ? ' selected' : ''}>${esc(c.code)} — ${esc(c.description)}</option>`;
        });
        return `<select name="${name}" class="form-select form-select-sm charge-code-sel s2 s2-code">${opts}</select>`;
    }

    function buildTaxCodeSelect(name, selectedId) {
        let opts = '<option value="">— none —</option>';
        taxCodeOpts.forEach(tc => {
            const fullLabel = `${tc.code} (SSCL ${tc.tax1_rate}% + VAT ${tc.tax2_rate}%)`;
            opts += `<option value="${tc.id}" data-code="${esc(tc.code)}" data-name="${esc(fullLabel)}" data-tax1-rate="${tc.tax1_rate}" data-tax2-rate="${tc.tax2_rate}" title="${esc(fullLabel)}"${tc.id == selectedId ? ' selected' : ''}>${esc(tc.code)}</option>`;
        });
        return `<select name="${name}" class="form-select form-select-sm tax-code-sel s2 s2-code">${opts}</select>`;
    }

    function initLineSelects(tr) {
        $(tr).find('select.s2').each(function() { window.initS2Code($(this), { width: '100%' }); });
    }

    const resolveUrl = '{{ route("estimates.resolve-charge-code") }}';

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

    function buildRow(data = {}) {
        const i          = lineIdx++;
        const fromDamage = !!data.damage_id;
        const sourceBadge = fromDamage
            ? `<span class="badge bg-warning-subtle text-warning border source-badge" title="Imported from survey damage"><i class="bi bi-clipboard-data"></i></span>`
            : `<span class="badge bg-light text-muted border source-badge" title="Manual entry"><i class="bi bi-pencil"></i></span>`;

        return `<tr class="estimate-line">
            <td class="ps-2 text-center">${sourceBadge}</td>
            <td class="ps-1">
                ${buildCodeSelect(`line_items[${i}][component_code_id]`, mrCmpCodeOpts, data.component_code_id ?? '')}
                <input type="hidden" name="line_items[${i}][damage_id]"          value="${data.damage_id          ?? ''}">
                <input type="hidden" name="line_items[${i}][mr_tariff_rule_id]"  value="${data.mr_tariff_rule_id  ?? ''}">
                <input type="hidden" name="line_items[${i}][location_code_id]"   value="${data.location_code_id   ?? ''}">
                <input type="hidden" name="line_items[${i}][damage_code_id]"     value="${data.damage_code_id     ?? ''}">
                <input type="hidden" name="line_items[${i}][repair_code_id]"     value="${data.repair_code_id     ?? ''}">
                <input type="hidden" name="line_items[${i}][material_code_id]"   value="${data.material_code_id   ?? ''}">
                <input type="hidden" name="line_items[${i}][cedex_code]"         value="${esc(data.cedex_code     ?? '')}">
                <input type="hidden" name="line_items[${i}][std_labor_hours]"    value="${data.std_labor_hours    ?? 0}">
                <input type="hidden" name="line_items[${i}][labor_rate]"         value="${data.labor_rate         ?? 0}">
                <input type="hidden" name="line_items[${i}][labor_amount]"       value="${data.labor_amount       ?? 0}">
                <input type="hidden" name="line_items[${i}][material_qty]"       value="${data.material_qty       ?? 0}">
                <input type="hidden" name="line_items[${i}][material_rate]"      value="${data.material_rate      ?? 0}">
                <input type="hidden" name="line_items[${i}][material_amount]"    value="${data.material_amount    ?? 0}">
                <input type="hidden" name="line_items[${i}][ancillary_amount]"   value="${data.ancillary_amount   ?? 0}">
                ${fromDamage && data.cedex_code ? `<small class="text-muted font-monospace" style="font-size:.68rem;">${esc(data.cedex_code)}</small>` : ''}
            </td>
            <td>
                ${buildChargeCodeSelect(`line_items[${i}][charge_code_id]`, data.charge_code_id ?? '')}
            </td>
            <td>
                <input type="text" name="line_items[${i}][component]" class="form-control form-control-sm comp-desc" placeholder="Description" value="${esc(data.component ?? '')}">
            </td>
            <td>
                <select name="line_items[${i}][repair_type]" class="form-select form-select-sm">
                    ${repairTypeOptions(data.repair_type ?? 'repair')}
                </select>
            </td>
            <td>
                <input type="number" name="line_items[${i}][qty]"        class="form-control form-control-sm qty"        value="${data.qty       ?? 1}"  min="0.01" step="0.01">
            </td>
            <td>
                <input type="number" name="line_items[${i}][unit_price]" class="form-control form-control-sm unit-price" value="${data.unit_price ?? 0}" min="0"    step="0.01">
            </td>
            <td>
                ${buildTaxCodeSelect(`line_items[${i}][tax_code_id]`, data.tax_code_id ?? '')}
            </td>
            <td class="text-end pe-2 small">
                <div class="fw-semibold line-net">0.00</div>
                <div style="font-size:.68rem; line-height:1.4; color:#6c757d;">
                    <span class="line-sscl-amt"></span>
                    <span class="line-vat-amt"></span>
                </div>
            </td>
            <td class="pe-1">
                <button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button>
            </td>
        </tr>`;
    }

    // ── Totals ─────────────────────────────────────────────────────────────
    const currencyEl = document.querySelector('[name="currency"]');
    function currency() { return currencyEl?.value || 'LKR'; }
    function fmt(n) { return currency() + ' ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function fmtSmall(n) { return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

    function recalculate() {
        let subtotal = 0, ssclTotal = 0, vatTotal = 0;
        let ssclRates = new Set(), vatRates = new Set();

        document.querySelectorAll('.estimate-line').forEach(row => {
            const qty   = parseFloat(row.querySelector('.qty')?.value       || 0);
            const price = parseFloat(row.querySelector('.unit-price')?.value || 0);
            const net   = qty * price;

            const taxSel  = row.querySelector('.tax-code-sel');
            const selOpt  = taxSel?.selectedOptions[0];
            const t1Rate  = parseFloat(selOpt?.dataset.tax1Rate || 0);
            const t2Rate  = parseFloat(selOpt?.dataset.tax2Rate || 0);

            const t1 = net * (t1Rate / 100);
            const t2 = (net + t1) * (t2Rate / 100);

            subtotal  += net;
            ssclTotal += t1;
            vatTotal  += t2;

            if (t1Rate > 0) ssclRates.add(t1Rate);
            if (t2Rate > 0) vatRates.add(t2Rate);

            // Per-line breakdown display
            const netEl  = row.querySelector('.line-net');
            const sEl    = row.querySelector('.line-sscl-amt');
            const vEl    = row.querySelector('.line-vat-amt');
            if (netEl) netEl.textContent = fmt(net);
            if (sEl)   sEl.textContent  = t1 > 0 ? '+SSCL ' + fmtSmall(t1) : '';
            if (vEl)   vEl.textContent  = t2 > 0 ? ' +VAT ' + fmtSmall(t2) : '';
        });

        document.getElementById('subtotal').textContent   = fmt(subtotal);
        document.getElementById('totalSscl').textContent  = fmt(ssclTotal);
        document.getElementById('totalVat').textContent   = fmt(vatTotal);
        document.getElementById('grandTotal').textContent  = fmt(subtotal + ssclTotal + vatTotal);

        // Show rate hints in tfoot labels
        const ssclRateLabel = ssclRates.size === 1 ? `(${[...ssclRates][0]}%)` : '';
        const vatRateLabel  = vatRates.size  === 1 ? `(${[...vatRates][0]}%)`  : '';
        document.getElementById('ssclPctDisplay').textContent = ssclRateLabel;
        document.getElementById('vatPctDisplay').textContent  = vatRateLabel;
    }

    // ── Component code: auto-fill description + AJAX resolve charge/tax ──
    // ── Charge code: auto-set matching tax code ───────────────────────────
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

    // ── Add / Remove lines ────────────────────────────────────────────────
    const lineItems = document.getElementById('lineItems');

    document.getElementById('addLine').addEventListener('click', () => {
        lineItems.insertAdjacentHTML('beforeend', buildRow());
        initLineSelects(lineItems.lastElementChild);
        recalculate();
    });

    lineItems.addEventListener('click', e => {
        if (e.target.closest('.remove-line')) {
            if (document.querySelectorAll('.estimate-line').length > 1) {
                e.target.closest('.estimate-line').remove();
                recalculate();
            }
        }
    });

    document.getElementById('lineTable').addEventListener('input', recalculate);

    // ── Import damages ────────────────────────────────────────────────────
    function importDamages(btn) {
        const url   = btn.dataset.url;
        const count = btn.dataset.count;
        if (!url) return;

        const existingRows = document.querySelectorAll('.estimate-line');
        const hasData = Array.from(existingRows).some(row => {
            const price = parseFloat(row.querySelector('.unit-price')?.value || 0);
            const desc  = row.querySelector('.comp-desc')?.value?.trim();
            return price > 0 || (desc && desc.length > 0);
        });
        const doImport = () => {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importing…';

            const eqtId    = document.getElementById('eqtSelect').value;
            const fetchUrl = url + (eqtId ? `?equipment_type_id=${eqtId}` : '');

            fetch(fetchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    lineItems.innerHTML = '';
                    lineIdx = 0;
                    data.lines.forEach(line => {
                        lineItems.insertAdjacentHTML('beforeend', buildRow(line));
                        initLineSelects(lineItems.lastElementChild);
                    });
                    recalculate();

                    const alertEl   = document.getElementById('importAlert');
                    const alertText = document.getElementById('importAlertText');
                    let msg = `${data.lines.length} damage(s) imported as line items.`;
                    if (data.tariff_found) {
                        msg += ` Prices pre-filled from tariff: <strong>${data.tariff_name}</strong>.`;
                        const noPrice = data.lines.filter(l => !l._tariff_matched).length;
                        if (noPrice > 0) msg += ` <span class="text-warning">${noPrice} line(s) had no matching tariff rule — unit price set to 0.</span>`;
                    } else {
                        msg += ' <span class="text-warning">No active M&R tariff found — unit prices set to 0. Please fill them in manually.</span>';
                    }
                    alertText.innerHTML = msg;
                    alertEl.classList.remove('d-none');

                    btn.disabled = false;
                    btn.innerHTML = `<i class="bi bi-check-circle me-1"></i>Reimport ${count} Damage(s)`;
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = `<i class="bi bi-download me-1"></i>Import ${count} Damage(s) as Lines`;
                    showToast('Failed to import damages. Please try again.', 'danger');
                });
        };

        if (hasData) {
            confirmAction(
                `This will replace all ${existingRows.length} existing line item(s). Continue?`,
                doImport,
                { title: 'Import Damages', confirmClass: 'btn-warning', confirmLabel: 'Replace & Import' }
            );
        } else {
            doImport();
        }

    document.getElementById('importDamagesBtn')?.addEventListener('click', function () { importDamages(this); });
    document.getElementById('importDamagesSideBtn')?.addEventListener('click', function () {
        const mainBtn = document.getElementById('importDamagesBtn');
        if (mainBtn) { importDamages(mainBtn); } else { importDamages(this); }
    });

    // ── Initialise with one blank row (inside jQuery ready to guarantee Select2 is loaded) ──
    $(function () {
        if (lineItems.children.length === 0) {
            lineItems.insertAdjacentHTML('beforeend', buildRow());
            initLineSelects(lineItems.lastElementChild);
            recalculate();
        }
    });
})();
</script>
@endpush
