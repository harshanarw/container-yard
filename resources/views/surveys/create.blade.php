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
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Container (in yard) <span class="text-danger">*</span></label>
                            <select name="container_id" id="containerSelect" class="form-select select2" required>
                                <option value="">— Select Container —</option>
                                @foreach($containers as $c)
                                <option value="{{ $c->id }}"
                                        data-container-no="{{ $c->container_no }}"
                                        data-eqt-id="{{ $c->equipment_type_id }}"
                                        data-customer-id="{{ $c->customer_id }}"
                                        data-gate-ref="{{ $c->gate_movement_ref }}"
                                        data-gate-date="{{ $c->gate_movement_date }}"
                                        {{ (old('container_id') ?? $selectedContainer?->id) == $c->id ? 'selected' : '' }}>
                                    {{ $c->container_no }}
                                    @if($c->customer) — {{ $c->customer->name }} @endif
                                    @if($c->gate_movement_date) [GI: {{ $c->gate_movement_date }}] @endif
                                </option>
                                @endforeach
                            </select>
                            <div id="containerGateInfo" class="mt-1 small text-muted d-none">
                                <i class="bi bi-info-circle me-1"></i>
                                Gate-in: <span id="containerGateDate" class="fw-semibold"></span>
                                &nbsp;·&nbsp; Ref: <span id="containerGateRef" class="font-monospace fw-semibold"></span>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Equipment Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2 align-items-center">
                                <select name="equipment_type_id" id="eqtSelect" class="form-select" required>
                                    <option value="">— Select Equipment Type —</option>
                                    @foreach($equipmentTypes as $eqt)
                                    <option value="{{ $eqt->id }}"
                                            data-size="{{ $eqt->size }}"
                                            data-type="{{ $eqt->type_code }}"
                                            data-eqt="{{ $eqt->eqt_code }}"
                                            {{ old('equipment_type_id') == $eqt->id ? 'selected' : '' }}>
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
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Customer / Owner <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customerSelect" class="form-select select2" required>
                                <option value="">— Select Customer —</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}"
                                            {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                                        {{ $c->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Survey Type <span class="text-danger">*</span></label>
                            <select name="inquiry_type" class="form-select" required>
                                <option value="">— Select Type —</option>
                                <option value="damage_survey"         {{ old('inquiry_type') === 'damage_survey'         ? 'selected' : '' }}>Damage Survey</option>
                                <option value="pre_trip_inspection"   {{ old('inquiry_type') === 'pre_trip_inspection'   ? 'selected' : '' }}>Pre-trip Inspection</option>
                                <option value="repair_assessment"     {{ old('inquiry_type') === 'repair_assessment'     ? 'selected' : '' }}>Repair Assessment</option>
                                <option value="condition_survey"      {{ old('inquiry_type') === 'condition_survey'      ? 'selected' : '' }}>Condition Survey</option>
                                <option value="pre_delivery_inspection" {{ old('inquiry_type') === 'pre_delivery_inspection' ? 'selected' : '' }}>Pre-delivery Inspection</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Assigned Inspector</label>
                            <select name="inspector_id" class="form-select">
                                <option value="">— Inspector —</option>
                                @foreach($inspectors as $ins)
                                <option value="{{ $ins->id }}" {{ old('inspector_id') == $ins->id ? 'selected' : '' }}>
                                    {{ $ins->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Inspection Date</label>
                            <input type="date" name="inspection_date" class="form-control"
                                   value="{{ old('inspection_date', date('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gate-In Reference</label>
                            <input type="text" name="gate_in_ref" class="form-control"
                                   placeholder="GI-XXXX" value="{{ old('gate_in_ref') }}">
                        </div>
                        <div class="col-md-4">
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
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addDamageRow">
                        <i class="bi bi-plus-circle me-1"></i>Add Row
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="damageTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width:22%">Location</th>
                                    <th style="width:22%">Damage Type</th>
                                    <th style="width:15%">Severity</th>
                                    <th style="width:20%">Dimensions (cm)</th>
                                    <th>Description</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="damageRows">
                                <tr class="damage-row">
                                    <td class="ps-3">
                                        <select name="damages[0][location]" class="form-select form-select-sm">
                                            <option value="floor">Floor</option>
                                            <option value="roof">Roof</option>
                                            <option value="left_side_wall">Left Side Wall</option>
                                            <option value="right_side_wall">Right Side Wall</option>
                                            <option value="front_wall">Front Wall</option>
                                            <option value="door">Door</option>
                                            <option value="door_seal">Door Seal</option>
                                            <option value="corner_post">Corner Post</option>
                                            <option value="base_rail">Base Rail</option>
                                            <option value="cross_member">Cross Member</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][damage_type]" class="form-select form-select-sm">
                                            <option value="dent">Dent</option>
                                            <option value="hole">Hole</option>
                                            <option value="crack">Crack</option>
                                            <option value="rust_corrosion">Rust/Corrosion</option>
                                            <option value="missing_part">Missing Part</option>
                                            <option value="broken">Broken</option>
                                            <option value="bent">Bent</option>
                                            <option value="delamination">Delamination</option>
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
                                        <input type="text" name="damages[0][dimensions]" class="form-control form-control-sm"
                                               placeholder="L×W×D">
                                    </td>
                                    <td>
                                        <input type="text" name="damages[0][description]" class="form-control form-control-sm"
                                               placeholder="Additional details…">
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

                    {{-- Hidden real file input --}}
                    <input type="file" id="photoInput" name="photos[]"
                           multiple accept="image/jpeg,image/png,image/webp,image/gif"
                           class="d-none">

                    {{-- Drop Zone --}}
                    <div id="photoDropZone"
                         class="border border-2 border-dashed rounded-3 text-center p-4 mb-3"
                         style="border-color:#dee2e6!important;cursor:pointer;transition:background .2s;">
                        <i class="bi bi-cloud-arrow-up text-primary" style="font-size:2.5rem;"></i>
                        <div class="fw-semibold mt-2">Drag &amp; drop photos here</div>
                        <div class="text-muted small mt-1">or click to browse files</div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-3" id="photoBrowseBtn">
                            <i class="bi bi-folder2-open me-1"></i>Browse Photos
                        </button>
                        <div class="text-muted mt-2" style="font-size:.75rem;">
                            JPG, PNG, WEBP &nbsp;·&nbsp; Max 5 MB per file &nbsp;·&nbsp; Up to 10 files
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

@push('scripts')
<script>
    // ── Container selection → auto-fill Equipment Type, Customer, Gate-In Ref ──
    (function () {
        const containerSel   = document.getElementById('containerSelect');
        const eqtSelect      = document.getElementById('eqtSelect');
        const customerSelect = document.getElementById('customerSelect');
        const gateRefInput   = document.querySelector('[name="gate_in_ref"]');
        const gateInfoBox    = document.getElementById('containerGateInfo');
        const gateDateSpan   = document.getElementById('containerGateDate');
        const gateRefSpan    = document.getElementById('containerGateRef');

        function applyEqtBadges(opt) {
            const sizeHid   = document.getElementById('eqtSize');
            const typeHid   = document.getElementById('eqtTypeCode');
            const sizeBadge = document.getElementById('eqtSizeBadge');
            const typeBadge = document.getElementById('eqtTypeBadge');
            if (!opt || !opt.value) {
                sizeHid.value = typeHid.value = '';
                sizeBadge.classList.add('d-none');
                typeBadge.classList.add('d-none');
                return;
            }
            sizeHid.value = opt.dataset.size || '';
            typeHid.value = opt.dataset.type || '';
            sizeBadge.textContent = opt.dataset.size ? opt.dataset.size + "'" : '';
            typeBadge.textContent = opt.dataset.type || '';
            sizeBadge.classList.toggle('d-none', !opt.dataset.size);
            typeBadge.classList.toggle('d-none', !opt.dataset.type);
        }

        function fillFromContainer(opt) {
            if (!opt || !opt.value) {
                gateInfoBox.classList.add('d-none');
                return;
            }

            // 1. Equipment Type (plain select — native API works fine)
            if (eqtSelect && opt.dataset.eqtId) {
                eqtSelect.value = opt.dataset.eqtId;
                applyEqtBadges(eqtSelect.selectedOptions[0]);
            }

            // 2. Customer (Select2 — must use jQuery val().trigger('change'))
            if (customerSelect && opt.dataset.customerId) {
                if (typeof $ !== 'undefined') {
                    $(customerSelect).val(opt.dataset.customerId).trigger('change');
                } else {
                    customerSelect.value = opt.dataset.customerId;
                }
            }

            // 3. Gate-In Reference
            if (gateRefInput) {
                gateRefInput.value = opt.dataset.gateRef || '';
            }

            // 4. Gate-in info strip
            const hasGateInfo = opt.dataset.gateDate || opt.dataset.gateRef;
            gateDateSpan.textContent = opt.dataset.gateDate || '—';
            gateRefSpan.textContent  = opt.dataset.gateRef  || '—';
            gateInfoBox.classList.toggle('d-none', !hasGateInfo);
        }

        // Select2 fires the native 'change' event — but binding via jQuery is
        // more reliable when Select2 is initialised after this script runs.
        function bindChange() {
            if (typeof $ !== 'undefined') {
                $(containerSel).on('change', function () {
                    fillFromContainer(this.selectedOptions[0]);
                });
            } else {
                containerSel.addEventListener('change', function () {
                    fillFromContainer(this.selectedOptions[0]);
                });
            }
        }

        // Wait for Select2 init if jQuery is present, else bind immediately
        if (typeof $ !== 'undefined') {
            $(function () { bindChange(); });
        } else {
            bindChange();
        }

        // Apply on page load if a container is pre-selected (e.g. ?container_id=X)
        if (containerSel.value) fillFromContainer(containerSel.selectedOptions[0]);
    })();

    // ── Equipment Type manual override (size/type badges) ─────────────────────
    (function () {
        const sel       = document.getElementById('eqtSelect');
        const sizeHid   = document.getElementById('eqtSize');
        const typeHid   = document.getElementById('eqtTypeCode');
        const sizeBadge = document.getElementById('eqtSizeBadge');
        const typeBadge = document.getElementById('eqtTypeBadge');

        function applyEqt(opt) {
            if (!opt || !opt.value) {
                sizeHid.value = typeHid.value = '';
                sizeBadge.classList.add('d-none');
                typeBadge.classList.add('d-none');
                return;
            }
            sizeHid.value = opt.dataset.size;
            typeHid.value = opt.dataset.type;
            sizeBadge.textContent = opt.dataset.size + "'";
            typeBadge.textContent = opt.dataset.type;
            sizeBadge.classList.remove('d-none');
            typeBadge.classList.remove('d-none');
        }

        sel.addEventListener('change', () => applyEqt(sel.selectedOptions[0]));
        if (sel.value) applyEqt(sel.selectedOptions[0]);
    })();

    let damageRowIndex = 1;

    document.getElementById('addDamageRow').addEventListener('click', function () {
        const tbody = document.getElementById('damageRows');
        const i = damageRowIndex++;
        const row = document.createElement('tr');
        row.className = 'damage-row';
        row.innerHTML = `
            <td class="ps-3">
                <select name="damages[${i}][location]" class="form-select form-select-sm">
                    <option value="floor">Floor</option><option value="roof">Roof</option>
                    <option value="left_side_wall">Left Side Wall</option>
                    <option value="right_side_wall">Right Side Wall</option>
                    <option value="front_wall">Front Wall</option><option value="door">Door</option>
                    <option value="door_seal">Door Seal</option><option value="corner_post">Corner Post</option>
                    <option value="base_rail">Base Rail</option>
                </select>
            </td>
            <td>
                <select name="damages[${i}][damage_type]" class="form-select form-select-sm">
                    <option value="dent">Dent</option><option value="hole">Hole</option>
                    <option value="crack">Crack</option><option value="rust_corrosion">Rust/Corrosion</option>
                    <option value="missing_part">Missing Part</option><option value="broken">Broken</option>
                </select>
            </td>
            <td>
                <select name="damages[${i}][severity]" class="form-select form-select-sm">
                    <option value="minor">Minor</option>
                    <option value="moderate">Moderate</option>
                    <option value="severe">Severe</option>
                </select>
            </td>
            <td><input type="text" name="damages[${i}][dimensions]" class="form-control form-control-sm" placeholder="L×W×D"></td>
            <td><input type="text" name="damages[${i}][description]" class="form-control form-control-sm" placeholder="Details…"></td>
            <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
    });

    document.getElementById('damageRows').addEventListener('click', function (e) {
        if (e.target.closest('.remove-row')) {
            const rows = document.querySelectorAll('.damage-row');
            if (rows.length > 1) e.target.closest('.damage-row').remove();
        }
    });

    // ── Photo Uploader ────────────────────────────────────────────────
    const MAX_FILES     = 10;
    const MAX_SIZE_MB   = 5;
    const MAX_SIZE_BYTE = MAX_SIZE_MB * 1024 * 1024;

    const photoInput    = document.getElementById('photoInput');
    const dropZone      = document.getElementById('photoDropZone');
    const browseBtn     = document.getElementById('photoBrowseBtn');
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
    dropZone.addEventListener('click', function () { photoInput.click(); });
    photoInput.addEventListener('change', function () { addFiles(this.files); this.value = ''; });

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
