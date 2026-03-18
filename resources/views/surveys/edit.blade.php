@extends('layouts.app')

@section('title', 'Edit Survey — ' . $inquiry->inquiry_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('surveys.index') }}" class="text-decoration-none">Container Surveys</a></li>
    <li class="breadcrumb-item"><a href="{{ route('surveys.show', $inquiry) }}" class="text-decoration-none">{{ $inquiry->inquiry_no }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@push('styles')
<style>
    #photoDropZone { border-style: dashed !important; }
    #photoDropZone:hover { background: #f0f4ff; border-color: #2196F3 !important; }
    .photo-card { transition: transform .15s; }
    .photo-card:hover { transform: translateY(-2px); }
    .existing-photo-card { position: relative; overflow: hidden; }
    .existing-photo-card .delete-overlay {
        position: absolute; inset: 0;
        background: rgba(220,53,69,.75);
        display: flex; align-items: center; justify-content: center;
        opacity: 0; transition: opacity .2s;
        color: #fff; font-size: .8rem; font-weight: 600;
        flex-direction: column; gap: 4px;
    }
    .existing-photo-card:hover .delete-overlay { opacity: 1; }
</style>
@endpush

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Survey — {{ $inquiry->inquiry_no }}</h4>
        <p class="text-muted mb-0 small">
            Container <span class="font-monospace fw-semibold">{{ $inquiry->container_no }}</span>
            &nbsp;·&nbsp; {{ $inquiry->size }}ft {{ $inquiry->type_code }}
            &nbsp;·&nbsp; {{ $inquiry->customer?->name }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('surveys.show', $inquiry) }}" class="btn btn-outline-info btn-sm">
            <i class="bi bi-eye me-1"></i>View
        </a>
        <a href="{{ route('surveys.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Please fix the errors below:</strong>
    <ul class="mb-0 mt-1 ps-3">
        @foreach($errors->all() as $e)<li class="small">{{ $e }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div id="jsErrorBag" class="alert alert-danger alert-dismissible fade show mb-3 d-none" role="alert">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<form method="POST" action="{{ route('surveys.update', $inquiry) }}"
      enctype="multipart/form-data" id="editSurveyForm">
    @csrf
    @method('PUT')

    <div class="row g-3">

        {{-- ════════ LEFT: main ════════ --}}
        <div class="col-lg-8">

            {{-- Container Info (read-only) --}}
            <div class="card content-card mb-3 border-primary">
                <div class="card-header bg-primary-subtle py-2 small fw-semibold">
                    <i class="bi bi-box-seam me-2 text-primary"></i>Container Details (read-only)
                </div>
                <div class="card-body">
                    <div class="row g-3 small">
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Container No.</div>
                            <div class="font-monospace fw-bold fs-6">{{ $inquiry->container_no }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Equipment Type</div>
                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                @if($inquiry->equipmentType)
                                    <span class="badge bg-primary fw-bold" style="font-size:.8rem;">
                                        {{ $inquiry->equipmentType->eqt_code }}
                                    </span>
                                @endif
                                <span class="badge bg-light border text-dark">{{ $inquiry->size }}'</span>
                                <span class="badge bg-info-subtle text-info">{{ $inquiry->type_code }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Customer</div>
                            <div class="fw-semibold">{{ $inquiry->customer?->name ?? '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Survey Type</div>
                            <div class="fw-semibold">{{ ucwords(str_replace('_', ' ', $inquiry->inquiry_type)) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Gate-In Reference</div>
                            <div class="font-monospace">{{ $inquiry->gate_in_ref ?? '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted mb-1">Survey No.</div>
                            <div class="fw-semibold">{{ $inquiry->inquiry_no }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Editable Survey Fields --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2 small fw-semibold">
                    <i class="bi bi-sliders me-2 text-primary"></i>Inspection Details
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assigned Inspector</label>
                            <select name="inspector_id" class="form-select">
                                <option value="">— Select Inspector —</option>
                                @foreach($inspectors as $ins)
                                <option value="{{ $ins->id }}"
                                    {{ old('inspector_id', $inquiry->inspector_id) == $ins->id ? 'selected' : '' }}>
                                    {{ $ins->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Inspection Date</label>
                            <input type="date" name="inspection_date" class="form-control"
                                   value="{{ old('inspection_date', $inquiry->inspection_date?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="normal"   {{ old('priority', $inquiry->priority) === 'normal'   ? 'selected' : '' }}>Normal</option>
                                <option value="urgent"   {{ old('priority', $inquiry->priority) === 'urgent'   ? 'selected' : '' }}>Urgent</option>
                                <option value="critical" {{ old('priority', $inquiry->priority) === 'critical' ? 'selected' : '' }}>Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror">
                                @foreach(['open'=>'Open','in_progress'=>'In Progress','estimate_sent'=>'Estimate Sent','approved'=>'Approved','closed'=>'Closed'] as $val => $label)
                                <option value="{{ $val }}" {{ old('status', $inquiry->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estimated Repair Cost (LKR)</label>
                            <div class="input-group">
                                <span class="input-group-text">LKR</span>
                                <input type="number" name="estimated_repair_cost" class="form-control"
                                       step="0.01" min="0" placeholder="0.00"
                                       value="{{ old('estimated_repair_cost', $inquiry->estimated_repair_cost) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Damage Assessment --}}
            <div class="card content-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="small fw-semibold"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Damage Assessment</span>
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
                                @php
                                    $locations    = ['floor'=>'Floor','roof'=>'Roof','left_side_wall'=>'Left Side Wall','right_side_wall'=>'Right Side Wall','front_wall'=>'Front Wall','door'=>'Door','door_seal'=>'Door Seal','corner_post'=>'Corner Post','base_rail'=>'Base Rail','cross_member'=>'Cross Member'];
                                    $damageTypes  = ['dent'=>'Dent','hole'=>'Hole','crack'=>'Crack','rust_corrosion'=>'Rust/Corrosion','missing_part'=>'Missing Part','broken'=>'Broken','bent'=>'Bent','delamination'=>'Delamination'];
                                    $existingDmgs = $inquiry->damages;
                                @endphp

                                @forelse($existingDmgs as $di => $dmg)
                                <tr class="damage-row">
                                    <td class="ps-3">
                                        <select name="damages[{{ $di }}][location]" class="form-select form-select-sm">
                                            @foreach($locations as $val => $lbl)
                                            <option value="{{ $val }}" {{ $dmg->location === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[{{ $di }}][damage_type]" class="form-select form-select-sm">
                                            @foreach($damageTypes as $val => $lbl)
                                            <option value="{{ $val }}" {{ $dmg->damage_type === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[{{ $di }}][severity]" class="form-select form-select-sm">
                                            <option value="minor"    {{ $dmg->severity === 'minor'    ? 'selected' : '' }}>Minor</option>
                                            <option value="moderate" {{ $dmg->severity === 'moderate' ? 'selected' : '' }}>Moderate</option>
                                            <option value="severe"   {{ $dmg->severity === 'severe'   ? 'selected' : '' }}>Severe</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="damages[{{ $di }}][dimensions]"
                                               class="form-control form-control-sm"
                                               placeholder="L×W×D" value="{{ $dmg->dimensions }}">
                                    </td>
                                    <td>
                                        <input type="text" name="damages[{{ $di }}][description]"
                                               class="form-control form-control-sm"
                                               placeholder="Details…" value="{{ $dmg->description }}">
                                    </td>
                                    <td class="pe-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr class="damage-row">
                                    <td class="ps-3">
                                        <select name="damages[0][location]" class="form-select form-select-sm">
                                            @foreach($locations as $val => $lbl)
                                            <option value="{{ $val }}">{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][damage_type]" class="form-select form-select-sm">
                                            @foreach($damageTypes as $val => $lbl)
                                            <option value="{{ $val }}">{{ $lbl }}</option>
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
                                    <td><input type="text" name="damages[0][dimensions]" class="form-control form-control-sm" placeholder="L×W×D"></td>
                                    <td><input type="text" name="damages[0][description]" class="form-control form-control-sm" placeholder="Details…"></td>
                                    <td class="pe-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Inspector's Notes --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2 small fw-semibold">
                    <i class="bi bi-pencil-square me-2 text-primary"></i>Inspector's Findings
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Overall Condition</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach(['excellent'=>'Excellent','good'=>'Good','fair'=>'Fair','poor'=>'Poor','condemned'=>'Condemned'] as $val => $lbl)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="overall_condition"
                                           value="{{ $val }}" id="cond_{{ $val }}"
                                           {{ old('overall_condition', $inquiry->overall_condition) === $val ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="cond_{{ $val }}">{{ $lbl }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Detailed Findings</label>
                            <textarea name="findings" class="form-control" rows="4"
                                      placeholder="Describe findings…">{{ old('findings', $inquiry->findings) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Recommended Action</label>
                            <select name="recommended_action" class="form-select">
                                @foreach(['repair'=>'Repair Required','monitor'=>'Monitor Only','scrap'=>'Scrap/Condemn','no_action'=>'No Action Required'] as $val => $lbl)
                                <option value="{{ $val }}" {{ old('recommended_action', $inquiry->recommended_action) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Photo Management --}}
            <div class="card content-card mb-3">
                <div class="card-header d-flex align-items-center justify-content-between py-2">
                    <span class="small fw-semibold"><i class="bi bi-camera me-2 text-primary"></i>Photo Evidence</span>
                    <span id="newPhotoCounter" class="badge bg-secondary-subtle text-secondary">+0 new</span>
                </div>
                <div class="card-body">

                    {{-- Existing photos --}}
                    @if($inquiry->photos->isNotEmpty())
                    <p class="small fw-semibold text-muted mb-2">
                        Existing Photos ({{ $inquiry->photos->count() }}) — hover to remove
                    </p>
                    <div class="row g-2 mb-4" id="existingPhotos">
                        @foreach($inquiry->photos as $photo)
                        <div class="col-6 col-md-4 col-lg-3" id="photo-col-{{ $photo->id }}">
                            <div class="card border shadow-sm existing-photo-card" style="overflow:hidden;">
                                <img src="{{ asset($photo->photo_path) }}"
                                     class="card-img-top"
                                     style="height:110px;object-fit:cover;"
                                     alt="photo"
                                     onerror="this.src='https://via.placeholder.com/200x110?text=Photo'">
                                <div class="delete-overlay">
                                    <form method="POST"
                                          action="{{ route('surveys.photos.destroy', [$inquiry, $photo]) }}"
                                          onsubmit="return confirm('Remove this photo?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash me-1"></i>Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <hr class="my-3">
                    @endif

                    {{-- New photo upload --}}
                    <p class="small fw-semibold text-muted mb-2">Add New Photos</p>

                    <input type="file" id="photoInput" name="photos[]"
                           multiple accept="image/jpeg,image/png,image/webp,image/gif"
                           class="d-none">

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
                            JPG, PNG, WEBP &nbsp;·&nbsp; Max 5 MB per file &nbsp;·&nbsp; Up to 10 new files
                        </div>
                    </div>

                    <div id="photoError" class="alert alert-danger alert-dismissible py-2 small d-none" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <span id="photoErrorMsg"></span>
                        <button type="button" class="btn-close btn-sm"
                                onclick="document.getElementById('photoError').classList.add('d-none')"></button>
                    </div>

                    <div class="row g-2" id="photoPreviewGrid"></div>

                </div>
            </div>

        </div>{{-- /col-lg-8 --}}

        {{-- ════════ RIGHT: sidebar ════════ --}}
        <div class="col-lg-4">

            {{-- Checklist --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2 small fw-semibold">
                    <i class="bi bi-check2-square me-2 text-primary"></i>Inspection Checklist
                </div>
                <div class="card-body">
                    @php $checklistMap = $inquiry->checklists->keyBy('checklist_item'); @endphp
                    @forelse($checklistItems as $item)
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox"
                               name="checklist[]" value="{{ $item->code }}"
                               id="chk_{{ $item->code }}"
                               {{ optional($checklistMap->get($item->code))->is_checked ? 'checked' : '' }}>
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

            {{-- Save actions --}}
            <div class="card content-card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                    <a href="{{ route('surveys.show', $inquiry) }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>

        </div>{{-- /col-lg-4 --}}

    </div>
</form>

@endsection

@push('scripts')
<script>
    // ── Damage rows ───────────────────────────────────────────
    let damageRowIndex = {{ $inquiry->damages->count() ?: 1 }};

    const locationOptions = `
        <option value="floor">Floor</option><option value="roof">Roof</option>
        <option value="left_side_wall">Left Side Wall</option><option value="right_side_wall">Right Side Wall</option>
        <option value="front_wall">Front Wall</option><option value="door">Door</option>
        <option value="door_seal">Door Seal</option><option value="corner_post">Corner Post</option>
        <option value="base_rail">Base Rail</option><option value="cross_member">Cross Member</option>`;

    const damageTypeOptions = `
        <option value="dent">Dent</option><option value="hole">Hole</option>
        <option value="crack">Crack</option><option value="rust_corrosion">Rust/Corrosion</option>
        <option value="missing_part">Missing Part</option><option value="broken">Broken</option>
        <option value="bent">Bent</option><option value="delamination">Delamination</option>`;

    document.getElementById('addDamageRow').addEventListener('click', function () {
        const i = damageRowIndex++;
        const row = document.createElement('tr');
        row.className = 'damage-row';
        row.innerHTML = `
            <td class="ps-3"><select name="damages[${i}][location]" class="form-select form-select-sm">${locationOptions}</select></td>
            <td><select name="damages[${i}][damage_type]" class="form-select form-select-sm">${damageTypeOptions}</select></td>
            <td>
                <select name="damages[${i}][severity]" class="form-select form-select-sm">
                    <option value="minor">Minor</option>
                    <option value="moderate">Moderate</option>
                    <option value="severe">Severe</option>
                </select>
            </td>
            <td><input type="text" name="damages[${i}][dimensions]" class="form-control form-control-sm" placeholder="L×W×D"></td>
            <td><input type="text" name="damages[${i}][description]" class="form-control form-control-sm" placeholder="Details…"></td>
            <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>`;
        document.getElementById('damageRows').appendChild(row);
    });

    document.getElementById('damageRows').addEventListener('click', function (e) {
        if (e.target.closest('.remove-row')) {
            const rows = document.querySelectorAll('.damage-row');
            if (rows.length > 1) e.target.closest('.damage-row').remove();
        }
    });

    // ── Photo Uploader ────────────────────────────────────────
    const MAX_FILES     = 10;
    const MAX_SIZE_MB   = 5;
    const MAX_SIZE_BYTE = MAX_SIZE_MB * 1024 * 1024;

    const photoInput  = document.getElementById('photoInput');
    const dropZone    = document.getElementById('photoDropZone');
    const browseBtn   = document.getElementById('photoBrowseBtn');
    const previewGrid = document.getElementById('photoPreviewGrid');
    const counter     = document.getElementById('newPhotoCounter');
    const errorBox    = document.getElementById('photoError');
    const errorMsg    = document.getElementById('photoErrorMsg');

    // Plain array — no DataTransfer; works reliably on Windows Chrome/Edge
    let files = [];

    function isImage(file) {
        if (/^image\//i.test(file.type || '')) return true;
        return /\.(jpe?g|png|webp|gif|bmp|tiff?)$/i.test(file.name || '');
    }

    function showError(msg) { errorMsg.textContent = msg; errorBox.classList.remove('d-none'); }

    function updateCounter() {
        const n = files.length;
        counter.textContent = `+${n} new`;
        counter.className = n > 0 ? 'badge bg-primary-subtle text-primary' : 'badge bg-secondary-subtle text-secondary';
    }

    function formatSize(bytes) {
        return bytes < 1024 * 1024 ? (bytes / 1024).toFixed(1) + ' KB' : (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function renderPreviews() {
        previewGrid.innerHTML = '';
        files.forEach(function (file, idx) {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-lg-3';
            const reader = new FileReader();
            reader.onload = function (e) {
                col.innerHTML = `
                    <div class="card border h-100 shadow-sm position-relative photo-card" style="overflow:hidden;">
                        <img src="${e.target.result}" class="card-img-top" style="height:110px;object-fit:cover;" alt="${file.name}">
                        <div class="card-body p-1 pb-2">
                            <div class="small fw-semibold text-truncate" style="font-size:.72rem;" title="${file.name}">${file.name}</div>
                            <div class="text-muted" style="font-size:.68rem;">${formatSize(file.size)}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger position-absolute remove-photo"
                                data-idx="${idx}" style="top:4px;right:4px;padding:2px 6px;font-size:.7rem;line-height:1.2;border-radius:50%;">
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
            if (files.length >= MAX_FILES) { showError('Maximum ' + MAX_FILES + ' new photos allowed.'); return; }
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

    dropZone.addEventListener('dragover',  function (e) { e.preventDefault(); dropZone.style.background='#e8f0fe'; dropZone.style.borderColor='#2196F3'; });
    dropZone.addEventListener('dragleave', function ()  { dropZone.style.background=''; dropZone.style.borderColor=''; });
    dropZone.addEventListener('drop',      function (e) { e.preventDefault(); dropZone.style.background=''; dropZone.style.borderColor=''; addFiles(e.dataTransfer.files); });

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
        fetch(_form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
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
