@extends('layouts.app')

@section('title', 'New Container Inquiry')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inquiries.index') }}">Container Inquiries</a></li>
    <li class="breadcrumb-item active">New Inquiry</li>
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
    <h4><i class="bi bi-card-checklist me-2 text-primary"></i>New Container Inquiry</h4>
    <p class="text-muted mb-0 small">Record container inspection details and damage findings</p>
</div>

<form method="POST" action="{{ route('inquiries.store') }}" enctype="multipart/form-data" id="inquiryForm">
    @csrf

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
                            <label class="form-label fw-semibold">Container Number <span class="text-danger">*</span></label>
                            <input type="text" name="container_no" id="containerNo"
                                   class="form-control text-uppercase font-monospace"
                                   placeholder="e.g. MSCU 123456 7"
                                   maxlength="12" required>
                            <div class="form-text">Format: XXXX NNNNNN C (ISO 6346)</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Container Size <span class="text-danger">*</span></label>
                            <select name="size" class="form-select" required>
                                <option value="">— Size —</option>
                                <option>20'</option>
                                <option>40'</option>
                                <option>45'</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Container Type <span class="text-danger">*</span></label>
                            <select name="type_code" class="form-select" required>
                                <option value="">— Type —</option>
                                <option value="GP">GP — General Purpose</option>
                                <option value="HC">HC — High Cube</option>
                                <option value="RF">RF — Reefer</option>
                                <option value="OT">OT — Open Top</option>
                                <option value="FR">FR — Flat Rack</option>
                                <option value="TK">TK — Tank</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Customer / Owner <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select select2" required>
                                <option value="">— Select Customer —</option>
                                @foreach($customers ?? [] as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                                <!-- Dummy options -->
                                <option value="1">Maersk Line</option>
                                <option value="2">CMA CGM Malaysia</option>
                                <option value="3">Hapag-Lloyd</option>
                                <option value="4">PIL Shipping</option>
                                <option value="5">OOCL Malaysia</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Inquiry Type <span class="text-danger">*</span></label>
                            <select name="inquiry_type" class="form-select" required>
                                <option value="">— Select Type —</option>
                                <option>Damage Survey</option>
                                <option>Pre-trip Inspection</option>
                                <option>Repair Assessment</option>
                                <option>Condition Survey</option>
                                <option>Pre-delivery Inspection</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Assigned Inspector <span class="text-danger">*</span></label>
                            <select name="inspector_id" class="form-select" required>
                                <option value="">— Inspector —</option>
                                <option value="1">Lee Wen Hao</option>
                                <option value="2">Tan Boon Keat</option>
                                <option value="3">Mohd Faizal</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Inspection Date <span class="text-danger">*</span></label>
                            <input type="date" name="inspection_date" class="form-control"
                                   value="{{ old('inspection_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gate-In Reference</label>
                            <input type="text" name="gate_in_ref" class="form-control"
                                   placeholder="GI-XXXX" value="{{ old('gate_in_ref') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                                <option value="critical">Critical</option>
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
                                            <option>Floor</option>
                                            <option>Roof</option>
                                            <option>Left Side Wall</option>
                                            <option>Right Side Wall</option>
                                            <option>Front Wall</option>
                                            <option>Door</option>
                                            <option>Door Seal</option>
                                            <option>Corner Post</option>
                                            <option>Base Rail</option>
                                            <option>Cross Member</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="damages[0][damage_type]" class="form-select form-select-sm">
                                            <option>Dent</option>
                                            <option>Hole</option>
                                            <option>Crack</option>
                                            <option>Rust/Corrosion</option>
                                            <option>Missing Part</option>
                                            <option>Broken</option>
                                            <option>Bent</option>
                                            <option>Delamination</option>
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
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="damages[0][dimensions]" class="form-control"
                                                   placeholder="L×W×D">
                                        </div>
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
                                @foreach(['Excellent','Good','Fair','Poor','Condemned'] as $cond)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="overall_condition"
                                           value="{{ strtolower($cond) }}" id="cond_{{ strtolower($cond) }}"
                                           {{ $loop->iteration === 2 ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="cond_{{ strtolower($cond) }}">{{ $cond }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Detailed Findings</label>
                            <textarea name="findings" class="form-control" rows="4"
                                      placeholder="Describe the condition and findings in detail…"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Recommended Action</label>
                            <select name="recommended_action" class="form-select">
                                <option value="repair">Repair Required</option>
                                <option value="monitor">Monitor Only</option>
                                <option value="scrap">Scrap/Condemn</option>
                                <option value="no_action">No Action Required</option>
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

            <!-- Quick Info -->
            <div class="card content-card mb-3 border-primary">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle me-2"></i>Container in Yard
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0 small">
                        <tr><td class="text-muted">Location:</td><td class="fw-semibold">Row A, Bay 12, Tier 1</td></tr>
                        <tr><td class="text-muted">Gate-In:</td><td class="fw-semibold">15 Feb 2026</td></tr>
                        <tr><td class="text-muted">Days in Yard:</td><td class="fw-semibold">13 days</td></tr>
                        <tr><td class="text-muted">Previous Inq.:</td><td class="fw-semibold">INQ-0071</td></tr>
                    </table>
                </div>
            </div>

            <!-- Inquiry Checklist -->
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-check2-square me-2 text-primary"></i>Inspection Checklist
                </div>
                <div class="card-body">
                    @foreach([
                        'Exterior panels inspected',
                        'Floor board condition checked',
                        'Door mechanism tested',
                        'Door seals/gaskets checked',
                        'Roof integrity verified',
                        'Corner castings inspected',
                        'Base rails & cross members',
                        'Forklift pockets checked',
                        'CSC plate visible & valid',
                        'Photos documented',
                    ] as $item)
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="checklist[]"
                               value="{{ Str::slug($item) }}" id="chk_{{ Str::slug($item) }}">
                        <label class="form-check-label small" for="chk_{{ Str::slug($item) }}">{{ $item }}</label>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Actions -->
            <div class="card content-card mb-3">
                <div class="card-body d-grid gap-2">
                    <button type="submit" name="action" value="save" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Inquiry
                    </button>
                    <button type="submit" name="action" value="save_estimate" class="btn btn-warning">
                        <i class="bi bi-tools me-2"></i>Save & Create Estimate
                    </button>
                    <a href="{{ route('inquiries.index') }}" class="btn btn-outline-secondary">
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
    let damageRowIndex = 1;

    document.getElementById('addDamageRow').addEventListener('click', function () {
        const tbody = document.getElementById('damageRows');
        const i = damageRowIndex++;
        const row = document.createElement('tr');
        row.className = 'damage-row';
        row.innerHTML = `
            <td class="ps-3">
                <select name="damages[${i}][location]" class="form-select form-select-sm">
                    <option>Floor</option><option>Roof</option><option>Left Side Wall</option>
                    <option>Right Side Wall</option><option>Front Wall</option><option>Door</option>
                    <option>Door Seal</option><option>Corner Post</option><option>Base Rail</option>
                </select>
            </td>
            <td>
                <select name="damages[${i}][damage_type]" class="form-select form-select-sm">
                    <option>Dent</option><option>Hole</option><option>Crack</option>
                    <option>Rust/Corrosion</option><option>Missing Part</option><option>Broken</option>
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
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    const photoInput    = document.getElementById('photoInput');
    const dropZone      = document.getElementById('photoDropZone');
    const browseBtn     = document.getElementById('photoBrowseBtn');
    const previewGrid   = document.getElementById('photoPreviewGrid');
    const counter       = document.getElementById('photoCounter');
    const errorBox      = document.getElementById('photoError');
    const errorMsg      = document.getElementById('photoErrorMsg');

    // Accumulated DataTransfer object — holds all selected files
    let dt = new DataTransfer();

    function showError(msg) {
        errorMsg.textContent = msg;
        errorBox.classList.remove('d-none');
    }

    function updateCounter() {
        const n = dt.files.length;
        counter.textContent = `${n} / ${MAX_FILES} photo${n !== 1 ? 's' : ''}`;
        counter.className = n >= MAX_FILES
            ? 'badge bg-warning-subtle text-warning'
            : 'badge bg-secondary-subtle text-secondary';
    }

    function formatSize(bytes) {
        return bytes < 1024 * 1024
            ? (bytes / 1024).toFixed(1) + ' KB'
            : (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function renderPreviews() {
        previewGrid.innerHTML = '';
        Array.from(dt.files).forEach((file, idx) => {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-lg-3';
            col.dataset.idx = idx;

            const reader = new FileReader();
            reader.onload = e => {
                col.innerHTML = `
                    <div class="card border h-100 shadow-sm position-relative photo-card" style="overflow:hidden;">
                        <img src="${e.target.result}"
                             class="card-img-top"
                             style="height:110px;object-fit:cover;"
                             alt="${file.name}">
                        <div class="card-body p-1 pb-2">
                            <div class="small fw-semibold text-truncate" style="max-width:100%;font-size:.72rem;"
                                 title="${file.name}">${file.name}</div>
                            <div class="text-muted" style="font-size:.68rem;">${formatSize(file.size)}</div>
                        </div>
                        <button type="button"
                                class="btn btn-sm btn-danger position-absolute remove-photo"
                                data-idx="${idx}"
                                style="top:4px;right:4px;padding:2px 6px;font-size:.7rem;line-height:1.2;border-radius:50%;"
                                title="Remove">
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
        const errors = [];

        Array.from(newFiles).forEach(file => {
            if (!ALLOWED_TYPES.includes(file.type)) {
                errors.push(`"${file.name}" is not a supported image type.`);
                return;
            }
            if (file.size > MAX_SIZE_BYTE) {
                errors.push(`"${file.name}" exceeds ${MAX_SIZE_MB} MB.`);
                return;
            }
            if (dt.files.length >= MAX_FILES) {
                errors.push(`Maximum ${MAX_FILES} photos allowed. Some files were skipped.`);
                return;
            }
            // Skip duplicates by name + size
            const duplicate = Array.from(dt.files).some(
                f => f.name === file.name && f.size === file.size
            );
            if (!duplicate) {
                dt.items.add(file);
            }
        });

        if (errors.length) showError(errors[0]);

        // Assign accumulated files back to the hidden input
        photoInput.files = dt.files;
        renderPreviews();
    }

    // Remove a photo by index
    previewGrid.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-photo');
        if (!btn) return;

        const removeIdx = parseInt(btn.dataset.idx, 10);
        const newDt = new DataTransfer();
        Array.from(dt.files).forEach((f, i) => {
            if (i !== removeIdx) newDt.items.add(f);
        });
        dt = newDt;
        photoInput.files = dt.files;
        renderPreviews();
    });

    // Trigger file picker
    browseBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        photoInput.click();
    });
    dropZone.addEventListener('click', () => photoInput.click());

    // File input change (browse)
    photoInput.addEventListener('change', function () {
        addFiles(this.files);
        // Reset so same file can be re-added after removal
        this.value = '';
    });

    // Drag & drop
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.background = '#e8f0fe';
        dropZone.style.borderColor = '#2196F3';
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.style.background = '';
        dropZone.style.borderColor = '';
    });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.background = '';
        dropZone.style.borderColor = '';
        addFiles(e.dataTransfer.files);
    });

    // ── Auto-format container number ──────────────────────────────────
    document.getElementById('containerNo').addEventListener('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });
</script>
@endpush
