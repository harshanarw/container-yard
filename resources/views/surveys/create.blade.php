@extends('layouts.app')

@section('title', 'New Container Survey')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('surveys.index') }}">Container Surveys</a></li>
    <li class="breadcrumb-item active">New Survey</li>
@endsection

@push('styles')
<style>
    #photoDropZone { border-style: dashed !important; }
    #photoDropZone:hover { background: #f0f4ff; border-color: #2196F3 !important; }
    .photo-card { transition: transform .15s; }
    .photo-card:hover { transform: translateY(-2px); }
    /* Dimension ft/in inputs — hide browser spinner arrows so labels fit */
    .dim-no-spin::-webkit-inner-spin-button,
    .dim-no-spin::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    .dim-no-spin { -moz-appearance: textfield; appearance: textfield; }
    .dim-unit-lbl { font-size: .72rem; color: #6c757d; }
    .dim-axis-lbl { font-size: .72rem; font-weight: 700; color: #0d6efd; min-width: 10px; }
</style>
@endpush

@section('content')

<div class="page-header">
    <h4><i class="bi bi-card-checklist me-2 text-primary"></i>New Container Survey</h4>
    <p class="text-muted mb-0 small">Record container inspection details and damage findings</p>
</div>

<form method="POST" action="{{ route('surveys.store') }}" enctype="multipart/form-data" id="surveyForm">
    @csrf

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div id="jsErrorBag" class="alert alert-danger alert-dismissible fade show mb-3 d-none" role="alert">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <div class="row g-3">

        <!-- Main Form -->
        <div class="col-lg-8">

            <!-- Container Details -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-box-seam me-2 text-primary"></i>Container Details
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        {{-- ── Container selector ── --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Container (in yard) <span class="text-danger">*</span></label>
                            <select name="container_id" id="containerSelect" class="form-select select2" required>
                                <option value="">— Select Container —</option>
                                @foreach($containers as $c)
                                <option value="{{ $c->id }}"
                                        data-customer-id="{{ $c->customer_id }}"
                                        data-customer-name="{{ $c->customer?->name }}"
                                        data-gate-ref="{{ $c->gate_movement_ref }}"
                                        data-gate-date="{{ $c->gate_movement_date }}"
                                        data-eqt-code="{{ $c->equipmentType?->eqt_code }}"
                                        data-eqt-name="{{ $c->equipmentType?->description }}"
                                        data-eqt-size="{{ $c->size }}"
                                        data-eqt-type="{{ $c->type_code }}"
                                        {{ (old('container_id') ?? $selectedContainer?->id) == $c->id ? 'selected' : '' }}>
                                    {{ $c->container_no }}
                                    @if($c->customer) — {{ $c->customer->name }} @endif
                                    @if($c->gate_movement_date) [GI: {{ $c->gate_movement_date }}] @endif
                                </option>
                                @endforeach
                            </select>
                            {{-- Hidden inputs — values set by JS from the selected container --}}
                            <input type="hidden" name="customer_id" id="customerIdHidden"
                                   value="{{ old('customer_id', $selectedContainer?->customer_id) }}">
                            <input type="hidden" name="gate_in_ref" id="gateRefHidden"
                                   value="{{ old('gate_in_ref') }}">
                        </div>

                        {{-- ── Container info panel (read-only, auto-filled) ── --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted">Container Details</label>
                            <div class="border rounded p-3 bg-light" style="min-height:72px">
                                <div id="containerInfoEmpty" class="text-muted small d-flex align-items-center" style="min-height:32px">
                                    <i class="bi bi-arrow-left-circle me-2"></i>Select a container to view its details
                                </div>
                                <div id="containerInfoFilled" class="d-none small">
                                    <div class="d-flex align-items-baseline gap-2 mb-1">
                                        <span class="text-muted" style="min-width:72px">Equipment</span>
                                        <span>
                                            <span id="eqtCodeDisplay" class="fw-semibold font-monospace"></span>
                                            <span id="eqtNameDisplay" class="text-muted ms-1"></span>
                                            <span id="eqtSizeBadge" class="badge bg-light border text-dark text-nowrap ms-1 d-none"></span>
                                            <span id="eqtTypeBadge" class="badge bg-info-subtle text-info text-nowrap ms-1 d-none"></span>
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-baseline gap-2 mb-1">
                                        <span class="text-muted" style="min-width:72px">Customer</span>
                                        <span id="customerDisplay" class="fw-semibold"></span>
                                    </div>
                                    <div class="d-flex align-items-baseline gap-2">
                                        <span class="text-muted" style="min-width:72px">Gate-In</span>
                                        <span>
                                            <span id="containerGateDate" class="fw-semibold"></span>
                                            <span class="text-muted mx-1">·</span>
                                            <span id="containerGateRef" class="font-monospace fw-semibold"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ── Survey-specific fields ── --}}
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Survey Type <span class="text-danger">*</span></label>
                            <select name="inquiry_type" class="form-select" required>
                                <option value="">— Select Type —</option>
                                <option value="damage_survey"           {{ old('inquiry_type') === 'damage_survey'           ? 'selected' : '' }}>Damage Survey</option>
                                <option value="pre_trip_inspection"     {{ old('inquiry_type') === 'pre_trip_inspection'     ? 'selected' : '' }}>Pre-trip Inspection</option>
                                <option value="repair_assessment"       {{ old('inquiry_type') === 'repair_assessment'       ? 'selected' : '' }}>Repair Assessment</option>
                                <option value="condition_survey"        {{ old('inquiry_type') === 'condition_survey'        ? 'selected' : '' }}>Condition Survey</option>
                                <option value="pre_delivery_inspection" {{ old('inquiry_type') === 'pre_delivery_inspection' ? 'selected' : '' }}>Pre-delivery Inspection</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Assigned Inspector</label>
                            <select name="inspector_id" class="form-select select2">
                                <option value="">— Inspector —</option>
                                @foreach($inspectors as $ins)
                                <option value="{{ $ins->id }}" {{ old('inspector_id') == $ins->id ? 'selected' : '' }}>
                                    {{ $ins->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Inspection Date</label>
                            <input type="date" name="inspection_date" class="form-control"
                                   value="{{ old('inspection_date', date('Y-m-d')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="normal"   {{ old('priority', 'normal') === 'normal'   ? 'selected' : '' }}>Normal</option>
                                <option value="urgent"   {{ old('priority') === 'urgent'   ? 'selected' : '' }}>Urgent</option>
                                <option value="critical" {{ old('priority') === 'critical' ? 'selected' : '' }}>Critical</option>
                            </select>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Damage Assessment -->
            <div class="card content-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Damage Assessment</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success" id="pullFromRulesBtn">
                            <i class="bi bi-journal-check me-1"></i>Pull From Rules
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addDamageRow">
                            <i class="bi bi-plus-circle me-1"></i>Add Row
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="damageTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="min-width:120px">Location</th>
                                    <th style="min-width:110px">Component</th>
                                    <th style="min-width:110px">Damage</th>
                                    <th style="min-width:110px">Repair</th>
                                    <th style="min-width:80px">Resp.</th>
                                    <th style="min-width:90px">Severity</th>
                                    <th style="min-width:130px">Dim. L / W ({{ $dimUom === 'ft_in' ? 'ft/in' : 'cm' }})</th>
                                    <th style="min-width:60px">Qty</th>
                                    <th>Description</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="damageRows">
                                <tr class="damage-row">
                                    <td class="ps-3">
                                        <select name="damages[0][location_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrLocationCodes as $c)
                                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][component_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrComponentCodes as $c)
                                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][damage_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrDamageCodes as $c)
                                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][repair_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrRepairCodes as $c)
                                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} {{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][responsibility_code_id]" class="form-select form-select-sm s2 s2-code">
                                            <option value="">—</option>
                                            @foreach($mrResponsibilityCodes as $c)
                                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->code }}">{{ $c->code }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][severity]" class="form-select form-select-sm">
                                            <option value="minor">Minor</option>
                                            <option value="moderate">Moderate</option>
                                            <option value="severe">Severe</option>
                                        </select>
                                    </td>
                                    <td>
                                        @if($dimUom === 'ft_in')
                                        <div class="dim-cell d-flex flex-column gap-1" style="min-width:145px;">
                                            <div class="d-flex align-items-center gap-1">
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-ft-l" placeholder="0" min="0" step="1" style="width:42px" title="Length feet">
                                                <span class="dim-unit-lbl">ft</span>
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-in-l" placeholder="0" min="0" max="11.75" step="0.25" style="width:42px" title="Length inches">
                                                <span class="dim-unit-lbl">in</span>
                                                <span class="dim-axis-lbl">L</span>
                                                <input type="hidden" name="damages[0][dim_length]" class="dim-hidden-l">
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-ft-w" placeholder="0" min="0" step="1" style="width:42px" title="Width feet">
                                                <span class="dim-unit-lbl">ft</span>
                                                <input type="number" class="form-control form-control-sm dim-no-spin dim-in-w" placeholder="0" min="0" max="11.75" step="0.25" style="width:42px" title="Width inches">
                                                <span class="dim-unit-lbl">in</span>
                                                <span class="dim-axis-lbl">W</span>
                                                <input type="hidden" name="damages[0][dim_width]" class="dim-hidden-w">
                                            </div>
                                        </div>
                                        @else
                                        <div class="d-flex gap-1">
                                            <input type="number" name="damages[0][dim_length]" class="form-control form-control-sm" placeholder="L" step="0.1" min="0" style="width:58px">
                                            <input type="number" name="damages[0][dim_width]"  class="form-control form-control-sm" placeholder="W" step="0.1" min="0" style="width:58px">
                                        </div>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="number" name="damages[0][quantity]" class="form-control form-control-sm" value="1" step="0.01" min="0.01" style="width:64px">
                                    </td>
                                    <td>
                                        <input type="text" name="damages[0][description]" class="form-control form-control-sm" placeholder="Details…">
                                    </td>
                                    <td class="pe-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Inspector's Notes -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-pencil-square me-2 text-primary"></i>Inspector's Notes & Findings
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Overall Condition</label>
                            <div class="d-flex gap-3 mb-3">
                                @foreach(['excellent'=>'Excellent','good'=>'Good','fair'=>'Fair','poor'=>'Poor','condemned'=>'Condemned'] as $val => $lbl)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="overall_condition"
                                           value="{{ $val }}" id="cond_{{ $val }}"
                                           {{ old('overall_condition', 'good') === $val ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="cond_{{ $val }}">{{ $lbl }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Detailed Findings</label>
                            <textarea name="findings" class="form-control" rows="4"
                                      placeholder="Describe the condition and findings in detail…">{{ old('findings') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Recommended Action</label>
                            <select name="recommended_action" class="form-select">
                                <option value="repair"     {{ old('recommended_action') === 'repair'     ? 'selected' : '' }}>Repair Required</option>
                                <option value="monitor"    {{ old('recommended_action') === 'monitor'    ? 'selected' : '' }}>Monitor Only</option>
                                <option value="scrap"      {{ old('recommended_action') === 'scrap'      ? 'selected' : '' }}>Scrap/Condemn</option>
                                <option value="no_action"  {{ old('recommended_action') === 'no_action'  ? 'selected' : '' }}>No Action Required</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photo Upload -->
            <div class="card content-card mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-camera me-2 text-primary"></i>Photo Evidence</span>
                    <span id="photoCounter" class="badge bg-secondary-subtle text-secondary">0 / 10 photos</span>
                </div>
                <div class="card-body">

                    {{-- File inputs --}}
                    <input type="file" id="photoInput" multiple accept="image/*" class="d-none">
                    <input type="file" id="photoCameraInput" accept="image/*" capture="environment" class="d-none">

                    {{-- Drop Zone --}}
                    <div id="photoDropZone"
                         class="border border-2 border-dashed rounded-3 text-center p-4 mb-3"
                         style="border-color:#dee2e6!important;cursor:pointer;transition:background .2s;">
                        <i class="bi bi-cloud-arrow-up text-primary" style="font-size:2.5rem;"></i>
                        <div class="d-flex justify-content-center gap-2 flex-wrap mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="photoBrowseBtn">
                                <i class="bi bi-folder2-open me-1"></i>Browse
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" id="photoCameraBtn">
                                <i class="bi bi-camera me-1"></i>Camera
                            </button>
                        </div>
                        <div class="text-muted mt-2" style="font-size:.75rem;">
                            or drag &amp; drop &nbsp;·&nbsp; Max 20 MB per file &nbsp;·&nbsp; Up to 10 files
                        </div>
                    </div>

                    {{-- Error alert --}}
                    <div id="photoError" class="alert alert-danger alert-dismissible py-2 small d-none" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <span id="photoErrorMsg"></span>
                        <button type="button" class="btn-close btn-sm" onclick="document.getElementById('photoError').classList.add('d-none')"></button>
                    </div>

                    {{-- Preview Grid --}}
                    <div class="row g-2" id="photoPreviewGrid"></div>

                </div>
            </div>

        </div>

        <!-- Right Sidebar -->
        <div class="col-lg-4">

            <!-- Inquiry Checklist -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-check2-square me-2 text-primary"></i>Inspection Checklist
                </div>
                <div class="card-body">
                    @forelse($checklistItems as $item)
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="checklist[]"
                               value="{{ $item->code }}" id="chk_{{ $item->code }}">
                        <label class="form-check-label small" for="chk_{{ $item->code }}"
                               @if($item->description) title="{{ $item->description }}" @endif>
                            {{ $item->label }}
                        </label>
                    </div>
                    @empty
                    <p class="text-muted small mb-0">No checklist items configured. Add items via Masters → Inspection Checklist.</p>
                    @endforelse
                </div>
            </div>

            <!-- Actions -->
            <div class="card content-card mb-3">
                <div class="card-body d-grid gap-2">
                    <button type="submit" name="action" value="save" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Survey
                    </button>
                    <button type="submit" name="action" value="save_estimate" class="btn btn-warning">
                        <i class="bi bi-tools me-2"></i>Save & Create Estimate
                    </button>
                    <a href="{{ route('surveys.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
            </div>

        </div>
    </div>
</form>

@endsection

{{-- Pull From Rules Modal (outside the form to avoid nesting) --}}
<div class="modal fade" id="pullFromRulesModal" tabindex="-1" aria-labelledby="pullFromRulesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="pullFromRulesModalLabel">
                    <i class="bi bi-journal-check me-2 text-primary"></i>Pull From Assessment Rules
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 border-bottom bg-light">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <input type="text" id="ruleSearchQ" class="form-control form-control-sm" placeholder="Search rule name…">
                        </div>
                        <div class="col-md-2">
                            <select id="ruleFilterLoc" class="form-select form-select-sm">
                                <option value="">All Locations</option>
                                @foreach($mrLocationCodes as $c)
                                <option value="{{ $c->id }}">{{ $c->code }} {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="ruleFilterCmp" class="form-select form-select-sm">
                                <option value="">All Components</option>
                                @foreach($mrComponentCodes as $c)
                                <option value="{{ $c->id }}">{{ $c->code }} {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="ruleFilterDmg" class="form-select form-select-sm">
                                <option value="">All Damage Types</option>
                                @foreach($mrDamageCodes as $c)
                                <option value="{{ $c->id }}">{{ $c->code }} {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="clearRuleFilters">
                                <i class="bi bi-x-circle me-1"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                            <tr>
                                <th class="ps-3" style="width:36px">
                                    <input type="checkbox" class="form-check-input" id="selectAllRules" title="Select all">
                                </th>
                                <th>Rule Name</th>
                                <th style="width:100px">Location</th>
                                <th style="width:130px">Component</th>
                                <th style="width:130px">Damage</th>
                                <th style="width:130px">Repair</th>
                                <th style="width:85px">Severity</th>
                            </tr>
                        </thead>
                        <tbody id="ruleModalBody">
                            <tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <span class="me-auto small text-muted" id="ruleSelectedCount">0 selected</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="addSelectedRulesBtn" disabled>
                    <i class="bi bi-plus-circle me-1"></i>Add Selected Lines
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const DIM_UOM = '{{ $dimUom }}';

    // Convert ft+in sub-inputs → total decimal inches in hidden field
    function syncDimHidden(cell) {
        const ftL  = parseFloat(cell.querySelector('.dim-ft-l')?.value) || 0;
        const inL  = parseFloat(cell.querySelector('.dim-in-l')?.value) || 0;
        const ftW  = parseFloat(cell.querySelector('.dim-ft-w')?.value) || 0;
        const inW  = parseFloat(cell.querySelector('.dim-in-w')?.value) || 0;
        const hidL = cell.querySelector('.dim-hidden-l');
        const hidW = cell.querySelector('.dim-hidden-w');
        if (hidL) hidL.value = ftL * 12 + inL || '';
        if (hidW) hidW.value = ftW * 12 + inW || '';
    }

    // Wire up ft/in inputs inside a newly-added row
    function initDimInputs(row) {
        row.querySelectorAll('.dim-cell').forEach(cell => {
            cell.querySelectorAll('input[type=number]').forEach(inp => {
                inp.addEventListener('input', () => syncDimHidden(cell));
            });
        });
    }

    // Build the dim cell HTML for JS-injected rows
    function buildDimCell(i) {
        if (DIM_UOM === 'ft_in') {
            return `<div class="dim-cell d-flex flex-column gap-1" style="min-width:145px;">
                <div class="d-flex align-items-center gap-1">
                    <input type="number" class="form-control form-control-sm dim-no-spin dim-ft-l" placeholder="0" min="0" step="1" style="width:42px" title="Length feet">
                    <span class="dim-unit-lbl">ft</span>
                    <input type="number" class="form-control form-control-sm dim-no-spin dim-in-l" placeholder="0" min="0" max="11.75" step="0.25" style="width:42px" title="Length inches">
                    <span class="dim-unit-lbl">in</span>
                    <span class="dim-axis-lbl">L</span>
                    <input type="hidden" name="damages[${i}][dim_length]" class="dim-hidden-l">
                </div>
                <div class="d-flex align-items-center gap-1">
                    <input type="number" class="form-control form-control-sm dim-no-spin dim-ft-w" placeholder="0" min="0" step="1" style="width:42px" title="Width feet">
                    <span class="dim-unit-lbl">ft</span>
                    <input type="number" class="form-control form-control-sm dim-no-spin dim-in-w" placeholder="0" min="0" max="11.75" step="0.25" style="width:42px" title="Width inches">
                    <span class="dim-unit-lbl">in</span>
                    <span class="dim-axis-lbl">W</span>
                    <input type="hidden" name="damages[${i}][dim_width]" class="dim-hidden-w">
                </div>
            </div>`;
        }
        return `<div class="d-flex gap-1">
            <input type="number" name="damages[${i}][dim_length]" class="form-control form-control-sm" placeholder="L" step="0.1" min="0" style="width:58px">
            <input type="number" name="damages[${i}][dim_width]"  class="form-control form-control-sm" placeholder="W" step="0.1" min="0" style="width:58px">
        </div>`;
    }

    // ── Container selection → auto-fill Customer, Gate-In Ref, Equipment Type display ──
    $(function () {
        var containerSel   = document.getElementById('containerSelect');
        if (!containerSel) return;

        var infoEmpty    = document.getElementById('containerInfoEmpty');
        var infoFilled   = document.getElementById('containerInfoFilled');
        var eqtCodeSpan  = document.getElementById('eqtCodeDisplay');
        var eqtNameSpan  = document.getElementById('eqtNameDisplay');
        var eqtSizeBadge = document.getElementById('eqtSizeBadge');
        var eqtTypeBadge = document.getElementById('eqtTypeBadge');
        var custDisplay  = document.getElementById('customerDisplay');
        var gateDateSpan = document.getElementById('containerGateDate');
        var gateRefSpan  = document.getElementById('containerGateRef');
        var custHidden   = document.getElementById('customerIdHidden');
        var gateRefHid   = document.getElementById('gateRefHidden');

        function fillFromContainer(opt) {
            if (!opt || !opt.value) {
                if (infoEmpty)  infoEmpty.classList.remove('d-none');
                if (infoFilled) infoFilled.classList.add('d-none');
                if (custHidden) custHidden.value = '';
                if (gateRefHid) gateRefHid.value = '';
                return;
            }

            // Hidden form values
            if (custHidden) custHidden.value = opt.dataset.customerId || '';
            if (gateRefHid) gateRefHid.value = opt.dataset.gateRef   || '';

            // Equipment type
            var eqtCode = opt.dataset.eqtCode || '';
            var eqtSize = opt.dataset.eqtSize || '';
            var eqtType = opt.dataset.eqtType || '';
            if (eqtCodeSpan) eqtCodeSpan.textContent = eqtCode;
            if (eqtNameSpan) eqtNameSpan.textContent = opt.dataset.eqtName ? '— ' + opt.dataset.eqtName : '';
            if (eqtSizeBadge) {
                eqtSizeBadge.textContent = eqtSize ? eqtSize + "'" : '';
                eqtSizeBadge.classList.toggle('d-none', !eqtSize);
            }
            if (eqtTypeBadge) {
                var isReefer = eqtType === 'RF' || eqtType === 'RH';
                eqtTypeBadge.textContent = eqtType;
                eqtTypeBadge.className   = 'badge text-nowrap ms-1' + (isReefer ? ' badge-reefer' : ' bg-info-subtle text-info');
                eqtTypeBadge.classList.toggle('d-none', !eqtType);
            }

            // Customer and Gate-In
            if (custDisplay)  custDisplay.textContent  = opt.dataset.customerName || '—';
            if (gateDateSpan) gateDateSpan.textContent = opt.dataset.gateDate     || '—';
            if (gateRefSpan)  gateRefSpan.textContent  = opt.dataset.gateRef      || '—';

            if (infoEmpty)  infoEmpty.classList.add('d-none');
            if (infoFilled) infoFilled.classList.remove('d-none');
        }

        $(containerSel).on('select2:select', function (e) {
            var opt = $(containerSel).find('option[value="' + e.params.data.id + '"]')[0];
            fillFromContainer(opt);
        });

        $(containerSel).on('select2:clear', function () {
            fillFromContainer(null);
        });

        if (containerSel.value) {
            fillFromContainer(containerSel.options[containerSel.selectedIndex]);
        }
    });

    // ── Damage rows ───────────────────────────────────────────────────────────
    // Store as data arrays (same pattern as edit.blade.php — proven working).
    // buildSel() is called from click handlers, which fire after DOMContentLoaded,
    // so window.initS2Code is guaranteed to be defined by then.
    const mrLocOpts  = @json($mrLocationCodes->map(fn($c)  => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));
    const mrCmpOpts  = @json($mrComponentCodes->map(fn($c) => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));
    const mrDmgOpts  = @json($mrDamageCodes->map(fn($c)    => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));
    const mrRepOpts  = @json($mrRepairCodes->map(fn($c)    => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]));
    const mrResOpts  = @json($mrResponsibilityCodes->map(fn($c) => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->code]));

    function buildSel(name, opts, codeOnly) {
        let html = `<select name="${name}" class="form-select form-select-sm s2 s2-code"><option value="">—</option>`;
        opts.forEach(o => {
            html += `<option value="${o.id}" data-code="${o.code}" data-name="${codeOnly ? o.code : o.name}">${o.code}${codeOnly ? '' : ' ' + o.name}</option>`;
        });
        return html + '</select>';
    }

    function initRowSelects(tr) {
        $(tr).find('select.s2').each(function () { window.initS2Code($(this), { width: '100%' }); });
    }

    // Wire up ft/in inputs on the initial static row
    document.querySelectorAll('#damageRows .damage-row').forEach(row => initDimInputs(row));

    let damageRowIndex = 1;

    document.getElementById('addDamageRow').addEventListener('click', function () {
        const tbody = document.getElementById('damageRows');
        const i     = damageRowIndex++;
        const row   = document.createElement('tr');
        row.className = 'damage-row';
        row.innerHTML = `
            <td class="ps-3">${buildSel('damages['+i+'][location_code_id]',      mrLocOpts)}</td>
            <td>${buildSel('damages['+i+'][component_code_id]',  mrCmpOpts)}</td>
            <td>${buildSel('damages['+i+'][damage_code_id]',     mrDmgOpts)}</td>
            <td>${buildSel('damages['+i+'][repair_code_id]',     mrRepOpts)}</td>
            <td>${buildSel('damages['+i+'][responsibility_code_id]', mrResOpts, true)}</td>
            <td>
                <select name="damages[${i}][severity]" class="form-select form-select-sm">
                    <option value="minor">Minor</option>
                    <option value="moderate">Moderate</option>
                    <option value="severe">Severe</option>
                </select>
            </td>
            <td>${buildDimCell(i)}</td>
            <td><input type="number" name="damages[${i}][quantity]" class="form-control form-control-sm" value="1" step="0.01" min="0.01" style="width:64px"></td>
            <td><input type="text"   name="damages[${i}][description]" class="form-control form-control-sm" placeholder="Details…"></td>
            <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
        initRowSelects(row);
        initDimInputs(row);
    });

    document.getElementById('damageRows').addEventListener('click', function (e) {
        if (e.target.closest('.remove-row')) {
            const rows = document.querySelectorAll('.damage-row');
            if (rows.length > 1) e.target.closest('.damage-row').remove();
        }
    });

    // ── Pull From Rules ───────────────────────────────────────────────
    (function () {
        const modal      = new bootstrap.Modal(document.getElementById('pullFromRulesModal'));
        const bodyEl     = document.getElementById('ruleModalBody');
        const countEl    = document.getElementById('ruleSelectedCount');
        const addBtn     = document.getElementById('addSelectedRulesBtn');
        const selectAll  = document.getElementById('selectAllRules');
        let debounceT    = null;

        document.getElementById('pullFromRulesBtn').addEventListener('click', function () {
            modal.show();
            fetchRules();
        });

        function fetchRules() {
            const params = new URLSearchParams({
                q:                  document.getElementById('ruleSearchQ').value,
                location_code_id:   document.getElementById('ruleFilterLoc').value,
                component_code_id:  document.getElementById('ruleFilterCmp').value,
                damage_code_id:     document.getElementById('ruleFilterDmg').value,
            });
            bodyEl.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</td></tr>';
            fetch('/masters/damage-assessment-rules/search?' + params, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => renderRules(data.rules || []))
                .catch(() => { bodyEl.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger">Failed to load rules.</td></tr>'; });
        }

        function renderRules(rules) {
            if (!rules.length) {
                bodyEl.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-journal-x me-1"></i>No matching rules found.</td></tr>';
                updateCount(); return;
            }
            const sevClass = s => s === 'severe' ? 'bg-danger' : s === 'moderate' ? 'bg-warning text-dark' : 'bg-light text-dark border';
            bodyEl.innerHTML = rules.map(r => `
                <tr class="rule-row">
                    <td class="ps-3"><input type="checkbox" class="form-check-input rule-chk" value="${r.id}" data-rule='${JSON.stringify(r).replace(/'/g, "&#39;")}'></td>
                    <td class="small fw-semibold">${escHtml(r.name)}</td>
                    <td class="small">${r.location_code ? `<span class="badge bg-secondary-subtle text-secondary border font-monospace">${escHtml(r.location_code)}</span>` : '<span class="text-muted fst-italic">Any</span>'}</td>
                    <td class="small"><span class="badge bg-primary-subtle text-primary border font-monospace">${escHtml(r.component_code)}</span> <span class="text-muted">${escHtml(r.component_name)}</span></td>
                    <td class="small"><span class="badge bg-warning-subtle text-warning-emphasis border font-monospace">${escHtml(r.damage_code)}</span> <span class="text-muted">${escHtml(r.damage_name)}</span></td>
                    <td class="small"><span class="badge bg-info-subtle text-info-emphasis border font-monospace">${escHtml(r.repair_code)}</span> <span class="text-muted">${escHtml(r.repair_name)}</span></td>
                    <td class="small">${r.default_severity ? `<span class="badge ${sevClass(r.default_severity)}">${r.default_severity.charAt(0).toUpperCase() + r.default_severity.slice(1)}</span>` : '<span class="text-muted">—</span>'}</td>
                </tr>`).join('');
            updateCount();
        }

        function escHtml(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }

        function updateCount() {
            const n = document.querySelectorAll('.rule-chk:checked').length;
            countEl.textContent = n > 0 ? n + ' selected' : '0 selected';
            addBtn.disabled = n === 0;
            addBtn.textContent = n > 0 ? `Add ${n} Selected Line${n !== 1 ? 's' : ''}` : 'Add Selected Lines';
            if (addBtn.disabled) addBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Add Selected Lines';
            else addBtn.innerHTML = `<i class="bi bi-plus-circle me-1"></i>Add ${n} Selected Line${n !== 1 ? 's' : ''}`;
        }

        // Filter events
        ['ruleSearchQ'].forEach(id => {
            document.getElementById(id).addEventListener('input', function () {
                clearTimeout(debounceT);
                debounceT = setTimeout(fetchRules, 280);
            });
        });
        ['ruleFilterLoc', 'ruleFilterCmp', 'ruleFilterDmg'].forEach(id => {
            document.getElementById(id).addEventListener('change', fetchRules);
        });

        // Clear filters
        document.getElementById('clearRuleFilters').addEventListener('click', function () {
            document.getElementById('ruleSearchQ').value = '';
            document.getElementById('ruleFilterLoc').value = '';
            document.getElementById('ruleFilterCmp').value = '';
            document.getElementById('ruleFilterDmg').value = '';
            fetchRules();
        });

        // Select all
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.rule-chk').forEach(c => c.checked = this.checked);
            updateCount();
        });

        // Individual checkbox
        bodyEl.addEventListener('change', function (e) {
            if (!e.target.classList.contains('rule-chk')) return;
            updateCount();
            const all = document.querySelectorAll('.rule-chk');
            const chk = document.querySelectorAll('.rule-chk:checked');
            selectAll.indeterminate = chk.length > 0 && chk.length < all.length;
            selectAll.checked = chk.length === all.length && all.length > 0;
        });

        // Add selected rules as rows
        addBtn.addEventListener('click', function () {
            const rules = Array.from(document.querySelectorAll('.rule-chk:checked'))
                               .map(chk => JSON.parse(chk.dataset.rule.replace(/&#39;/g, "'")));
            if (!rules.length) return;

            // If the first damage row is completely blank, fill it instead of appending
            const firstRow = document.querySelector('#damageRows .damage-row');
            let startIdx = 0;
            if (firstRow && isRowBlank(firstRow)) {
                fillExistingRow(firstRow, rules[0]);
                startIdx = 1;
            }
            for (let i = startIdx; i < rules.length; i++) {
                addRuleAsRow(rules[i]);
            }

            modal.hide();
            // Reset state
            selectAll.checked = false;
            selectAll.indeterminate = false;
            document.querySelectorAll('.rule-chk').forEach(c => c.checked = false);
            updateCount();
        });

        function isRowBlank(row) {
            return ['component_code_id','damage_code_id','repair_code_id'].every(f => {
                const s = row.querySelector(`select[name*="[${f}]"]`);
                return !s || !s.value;
            });
        }

        function fillExistingRow(row, r) {
            const sevSel = row.querySelector('select[name*="[severity]"]');
            if (sevSel && r.default_severity) sevSel.value = r.default_severity;
            const descInput = row.querySelector('input[name*="[description]"]');
            if (descInput) descInput.value = r.description || '';
            if (r.location_code_id) { const s = row.querySelector('select[name*="[location_code_id]"]'); if (s) { s.value = r.location_code_id; $(s).trigger('change'); } }
            const cs = row.querySelector('select[name*="[component_code_id]"]'); if (cs) { cs.value = r.component_code_id; $(cs).trigger('change'); }
            const ds = row.querySelector('select[name*="[damage_code_id]"]');    if (ds) { ds.value = r.damage_code_id;    $(ds).trigger('change'); }
            const rs = row.querySelector('select[name*="[repair_code_id]"]');    if (rs) { rs.value = r.repair_code_id;    $(rs).trigger('change'); }
            row.style.transition = 'background-color 0.6s';
            row.style.backgroundColor = '#d1fae5';
            setTimeout(() => { row.style.backgroundColor = ''; }, 1400);
        }

        function addRuleAsRow(r) {
            const i   = damageRowIndex++;
            const row = document.createElement('tr');
            row.className = 'damage-row';
            const sevOpts = ['minor','moderate','severe'].map(s =>
                `<option value="${s}"${r.default_severity === s ? ' selected' : ''}>${s.charAt(0).toUpperCase()+s.slice(1)}</option>`
            ).join('');
            row.innerHTML = `
                <td class="ps-3">${buildSel('damages['+i+'][location_code_id]', mrLocOpts)}</td>
                <td>${buildSel('damages['+i+'][component_code_id]', mrCmpOpts)}</td>
                <td>${buildSel('damages['+i+'][damage_code_id]', mrDmgOpts)}</td>
                <td>${buildSel('damages['+i+'][repair_code_id]', mrRepOpts)}</td>
                <td>${buildSel('damages['+i+'][responsibility_code_id]', mrResOpts, true)}</td>
                <td><select name="damages[${i}][severity]" class="form-select form-select-sm">${sevOpts}</select></td>
                <td>${buildDimCell(i)}</td>
                <td><input type="number" name="damages[${i}][quantity]" class="form-control form-control-sm" value="1" step="0.01" min="0.01" style="width:64px"></td>
                <td><input type="text" name="damages[${i}][description]" class="form-control form-control-sm" placeholder="Details…" value="${escHtml(r.description || '')}"></td>
                <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>`;
            document.getElementById('damageRows').appendChild(row);
            initRowSelects(row);
            initDimInputs(row);
            // Pre-select codes via Select2
            if (r.location_code_id) { const s = row.querySelector(`[name="damages[${i}][location_code_id]"]`); s.value = r.location_code_id; $(s).trigger('change'); }
            const cs = row.querySelector(`[name="damages[${i}][component_code_id]"]`); cs.value = r.component_code_id; $(cs).trigger('change');
            const ds = row.querySelector(`[name="damages[${i}][damage_code_id]"]`);    ds.value = r.damage_code_id;    $(ds).trigger('change');
            const rs = row.querySelector(`[name="damages[${i}][repair_code_id]"]`);    rs.value = r.repair_code_id;    $(rs).trigger('change');
            // Brief highlight to show the row was added from a rule
            row.style.transition = 'background-color 0.6s';
            row.style.backgroundColor = '#d1fae5';
            setTimeout(() => { row.style.backgroundColor = ''; }, 1400);
        }
    })();

    // ── Photo Uploader ────────────────────────────────────────────────
    const MAX_FILES     = 10;
    const MAX_SIZE_MB   = 20;
    const MAX_SIZE_BYTE = MAX_SIZE_MB * 1024 * 1024;

    const photoInput    = document.getElementById('photoInput');
    const cameraInput   = document.getElementById('photoCameraInput');
    const dropZone      = document.getElementById('photoDropZone');
    const browseBtn     = document.getElementById('photoBrowseBtn');
    const cameraBtn     = document.getElementById('photoCameraBtn');
    const previewGrid   = document.getElementById('photoPreviewGrid');
    const counter       = document.getElementById('photoCounter');
    const errorBox      = document.getElementById('photoError');
    const errorMsg      = document.getElementById('photoErrorMsg');

    // Plain array — no DataTransfer; works reliably on Windows Chrome/Edge
    let files = [];

    function isImage(file) {
        if (/^image\//i.test(file.type || '')) return true;
        return /\.(jpe?g|png|webp|gif|bmp|tiff?)$/i.test(file.name || '');
    }

    function showError(msg) { errorMsg.textContent = msg; errorBox.classList.remove('d-none'); }

    function updateCounter() {
        const n = files.length;
        counter.textContent = `${n} / ${MAX_FILES} photo${n !== 1 ? 's' : ''}`;
        counter.className = n >= MAX_FILES ? 'badge bg-warning-subtle text-warning' : 'badge bg-secondary-subtle text-secondary';
    }

    function formatSize(bytes) {
        return bytes < 1024 * 1024 ? (bytes / 1024).toFixed(1) + ' KB' : (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function renderPreviews() {
        previewGrid.innerHTML = '';
        files.forEach(function (file, idx) {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-lg-3';
            col.dataset.idx = idx;
            const reader = new FileReader();
            reader.onload = function (e) {
                col.innerHTML = `
                    <div class="card border h-100 shadow-sm position-relative photo-card" style="overflow:hidden;">
                        <img src="${e.target.result}" class="card-img-top" style="height:110px;object-fit:cover;" alt="${file.name}">
                        <div class="card-body p-1 pb-2">
                            <div class="small fw-semibold text-truncate" style="max-width:100%;font-size:.72rem;" title="${file.name}">${file.name}</div>
                            <div class="text-muted" style="font-size:.68rem;">${formatSize(file.size)}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger position-absolute remove-photo"
                                data-idx="${idx}" style="top:4px;right:4px;padding:2px 6px;font-size:.7rem;line-height:1.2;border-radius:50%;" title="Remove">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>`;
                previewGrid.appendChild(col);
            };
            reader.readAsDataURL(file);
        });
        updateCounter();
    }

    function addFiles(newFiles) {
        errorBox.classList.add('d-none');
        Array.from(newFiles).forEach(function (file) {
            if (!isImage(file))            { showError('"' + file.name + '" is not a supported image.'); return; }
            if (file.size > MAX_SIZE_BYTE) { showError('"' + file.name + '" exceeds ' + MAX_SIZE_MB + ' MB.'); return; }
            if (files.length >= MAX_FILES) { showError('Maximum ' + MAX_FILES + ' photos allowed.'); return; }
            if (!files.some(function (f) { return f.name === file.name && f.size === file.size; })) files.push(file);
        });
        renderPreviews();
    }

    previewGrid.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-photo');
        if (!btn) return;
        files.splice(parseInt(btn.dataset.idx, 10), 1);
        renderPreviews();
    });

    browseBtn.addEventListener('click', function (e) { e.stopPropagation(); photoInput.click(); });
    cameraBtn.addEventListener('click', function (e) { e.stopPropagation(); cameraInput.click(); });
    dropZone.addEventListener('click', function (e) { if (!e.target.closest('button')) photoInput.click(); });
    photoInput.addEventListener('change', function () { addFiles(this.files); this.value = ''; });
    cameraInput.addEventListener('change', function () { addFiles(this.files); this.value = ''; });

    dropZone.addEventListener('dragover',  function (e) { e.preventDefault(); dropZone.style.background = '#e8f0fe'; dropZone.style.borderColor = '#2196F3'; });
    dropZone.addEventListener('dragleave', function ()  { dropZone.style.background = ''; dropZone.style.borderColor = ''; });
    dropZone.addEventListener('drop',      function (e) { e.preventDefault(); dropZone.style.background = ''; dropZone.style.borderColor = ''; addFiles(e.dataTransfer.files); });

    // Submit via fetch — appends File objects from plain array directly into FormData
    const _form      = photoInput.closest('form');
    const _submitBtn = _form.querySelector('[type="submit"]');
    const _origHtml  = _submitBtn ? _submitBtn.innerHTML : '';
    const _errBag    = document.getElementById('jsErrorBag');
    _form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (_errBag) _errBag.classList.add('d-none');
        if (_submitBtn) { _submitBtn.disabled = true; _submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving…'; }
        const fd = new FormData(_form);
        files.forEach(function (file) { fd.append('photos[]', file); });
        fetch(_form.getAttribute('action'), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (response.status === 422 && data.errors) {
                        var msgs = Object.values(data.errors).flat();
                        if (_errBag) {
                            _errBag.innerHTML = '<strong>Please fix the following errors:</strong><ul class="mb-0 mt-1 ps-3">' +
                                msgs.map(function (m) { return '<li>' + m + '</li>'; }).join('') + '</ul>';
                            _errBag.classList.remove('d-none');
                            _errBag.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                        if (_submitBtn) { _submitBtn.disabled = false; _submitBtn.innerHTML = _origHtml; }
                    } else if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        if (_submitBtn) { _submitBtn.disabled = false; _submitBtn.innerHTML = _origHtml; }
                    }
                });
            })
            .catch(function () { if (_submitBtn) { _submitBtn.disabled = false; _submitBtn.innerHTML = _origHtml; } });
    });
</script>
@endpush
