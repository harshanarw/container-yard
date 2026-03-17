@extends('layouts.app')

@section('title', 'Gate In / Gate Out')

@section('breadcrumb')
    <li class="breadcrumb-item active">Gate In / Gate Out</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-arrow-left-right me-2 text-primary"></i>Gate In / Gate Out</h4>
        <p class="text-muted mb-0 small">Record container arrivals and departures from the yard</p>
    </div>
    <div class="text-muted small">
        <i class="bi bi-clock me-1"></i>{{ now()->format('d M Y, H:i') }}
    </div>
</div>

<!-- Quick Toggle: Gate In / Gate Out -->
<div class="d-flex gap-3 mb-4">
    <button class="btn btn-primary px-4" id="btnGateIn">
        <i class="bi bi-box-arrow-in-right me-2"></i>Gate In
    </button>
    <button class="btn btn-outline-success px-4" id="btnGateOut">
        <i class="bi bi-box-arrow-right me-2"></i>Gate Out
    </button>
</div>

<div class="row g-3">

    <!-- Gate In / Out Form -->
    <div class="col-lg-6">
        <div class="card content-card" id="gateInCard">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-box-arrow-in-right me-2"></i>Gate In — Container Arrival
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('yard.gate.in') }}" id="gateInForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="movement_type" value="in">

                    @if($errors->any())
                    <div class="alert alert-danger py-2 small">
                        <strong><i class="bi bi-exclamation-triangle me-1"></i>Please fix the following:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Container Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="container_no" id="containerNoIn"
                                   class="form-control font-monospace text-uppercase"
                                   placeholder="XXXX0000000" required autocomplete="off"
                                   maxlength="11">
                            <button type="button" class="btn btn-outline-secondary" id="scanBtn" title="Scan">
                                <i class="bi bi-upc-scan"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Equipment Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2 align-items-center">
                                <select name="equipment_type_id" id="gateEqtSelect" class="form-select" required>
                                    <option value="">— Select Equipment Type —</option>
                                    @foreach($equipmentTypes as $eqt)
                                    <option value="{{ $eqt->id }}"
                                            data-size="{{ $eqt->size }}"
                                            data-type="{{ $eqt->type_code }}"
                                            data-eqt="{{ $eqt->eqt_code }}">
                                        {{ $eqt->eqt_code }} — {{ $eqt->description }}
                                    </option>
                                    @endforeach
                                </select>
                                <span id="gateEqtSizeBadge" class="badge bg-light border text-dark text-nowrap d-none"></span>
                                <span id="gateEqtTypeBadge" class="badge bg-info-subtle text-info text-nowrap d-none"></span>
                            </div>
                            <input type="hidden" name="size" id="gateEqtSize">
                            <input type="hidden" name="type_code" id="gateEqtTypeCode">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Customer / Owner <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select select2" required>
                                <option value="">— Select Customer —</option>
                                @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Condition</label>
                            <select name="condition" class="form-select">
                                <option value="sound">Sound</option>
                                <option value="damaged">Damaged</option>
                                <option value="require_repair">Requires Repair</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Empty / Full</label>
                            <select name="cargo_status" class="form-select">
                                <option value="empty">Empty</option>
                                <option value="full">Full</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Yard Location</label>
                            <div class="input-group">
                                <select name="location_row" class="form-select form-select-sm" required>
                                    <option value="">Row</option>
                                    @foreach($emptySlots->pluck('row')->unique() as $row)
                                    <option value="{{ $row }}">{{ $row }}</option>
                                    @endforeach
                                </select>
                                <select name="location_bay" class="form-select form-select-sm" required>
                                    <option value="">Bay</option>
                                    @for($b = 1; $b <= 8; $b++)
                                    <option value="{{ $b }}">{{ $b }}</option>
                                    @endfor
                                </select>
                                <select name="location_tier" class="form-select form-select-sm" required>
                                    <option value="">Tier</option>
                                    @for($t = 1; $t <= 5; $t++)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Seal Number</label>
                            <input type="text" name="seal_no" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Truck/Vehicle Plate</label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase"
                                   placeholder="e.g. WQR 1234">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"
                                      placeholder="Any remarks about this container…"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Gate In Date &amp; Time
                                @if(!auth()->user()->isAdmin())
                                    <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">
                                        <i class="bi bi-lock me-1"></i>Auto
                                    </span>
                                @endif
                            </label>
                            <input type="datetime-local" name="gate_in_time"
                                   id="gateInTime"
                                   class="form-control"
                                   value="{{ now()->format('Y-m-d\TH:i') }}"
                                   {{ auth()->user()->isAdmin() ? '' : 'readonly' }}>
                            @if(!auth()->user()->isAdmin())
                                <div class="form-text text-muted" style="font-size:.72rem;">
                                    <i class="bi bi-info-circle me-1"></i>Date/time is set automatically. Only administrators can change it.
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Photo Evidence -->
                    <div class="mt-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-camera me-1 text-primary"></i>Photo Evidence
                            <span class="text-muted fw-normal small">(optional, max 5)</span>
                            <span id="inPhotoCounter" class="badge bg-secondary-subtle text-secondary ms-1">0 / 5</span>
                        </label>

                        {{-- Hidden inputs --}}
                        <input type="file" id="inPhotoInput"
                               multiple accept="image/*" class="d-none">
                        <input type="file" id="inCameraInput" accept="image/*"
                               capture="environment" class="d-none">

                        {{-- Drop zone --}}
                        <div id="inDropZone"
                             class="border border-2 rounded-3 text-center p-3 mb-2"
                             style="border-color:#dee2e6!important;border-style:dashed!important;cursor:pointer;transition:background .2s;">
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="inBrowseBtn">
                                    <i class="bi bi-folder2-open me-1"></i>Browse
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" id="inCameraBtn">
                                    <i class="bi bi-camera me-1"></i>Camera
                                </button>
                            </div>
                            <div class="text-muted mt-1" style="font-size:.72rem;">
                                or drag &amp; drop images here &nbsp;·&nbsp; JPG/PNG/WEBP &nbsp;·&nbsp; max 5 MB each
                            </div>
                        </div>

                        <div id="inPhotoError" class="alert alert-danger py-1 small d-none mb-2"></div>
                        <div class="row g-1" id="inPhotoPreview"></div>
                    </div>

                    <div class="mt-3 d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Record Gate In
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Gate Out (hidden by default) -->
        <div class="card content-card d-none" id="gateOutCard">
            <div class="card-header bg-success text-white">
                <i class="bi bi-box-arrow-right me-2"></i>Gate Out — Container Departure
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('yard.gate.out') }}" id="gateOutForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="movement_type" value="out">

                    @if($errors->any())
                    <div class="alert alert-danger py-2 small">
                        <strong><i class="bi bi-exclamation-triangle me-1"></i>Please fix the following:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Container Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="container_no" class="form-control font-monospace text-uppercase"
                                   placeholder="Search container in yard…" required id="containerSearch">
                            <button type="button" class="btn btn-outline-secondary">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Container Info Card (populated via JS/AJAX) -->
                    <div class="alert alert-info small p-2 mb-3" id="containerInfoBox">
                        <i class="bi bi-info-circle me-1"></i>Enter a container number above to view details.
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Release Order No.</label>
                            <input type="text" name="release_order" class="form-control"
                                   placeholder="RO-XXXX">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Truck/Vehicle Plate</label>
                            <input type="text" name="vehicle_plate" class="form-control text-uppercase"
                                   placeholder="e.g. JHQ 5678">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Driver Name</label>
                            <input type="text" name="driver_name" class="form-control"
                                   placeholder="Driver's name">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Driver IC/Passport</label>
                            <input type="text" name="driver_ic" class="form-control"
                                   placeholder="ID number">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"
                                      placeholder="Any remarks…"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Gate Out Date &amp; Time
                                @if(!auth()->user()->isAdmin())
                                    <span class="badge bg-secondary-subtle text-secondary fw-normal ms-1" style="font-size:.7rem;">
                                        <i class="bi bi-lock me-1"></i>Auto
                                    </span>
                                @endif
                            </label>
                            <input type="datetime-local" name="gate_out_time"
                                   id="gateOutTime"
                                   class="form-control"
                                   value="{{ now()->format('Y-m-d\TH:i') }}"
                                   {{ auth()->user()->isAdmin() ? '' : 'readonly' }}>
                            @if(!auth()->user()->isAdmin())
                                <div class="form-text text-muted" style="font-size:.72rem;">
                                    <i class="bi bi-info-circle me-1"></i>Date/time is set automatically. Only administrators can change it.
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Photo Evidence -->
                    <div class="mt-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-camera me-1 text-success"></i>Photo Evidence
                            <span class="text-muted fw-normal small">(optional, max 5)</span>
                            <span id="outPhotoCounter" class="badge bg-secondary-subtle text-secondary ms-1">0 / 5</span>
                        </label>

                        {{-- Hidden inputs --}}
                        <input type="file" id="outPhotoInput"
                               multiple accept="image/*" class="d-none">
                        <input type="file" id="outCameraInput" accept="image/*"
                               capture="environment" class="d-none">

                        {{-- Drop zone --}}
                        <div id="outDropZone"
                             class="border border-2 rounded-3 text-center p-3 mb-2"
                             style="border-color:#dee2e6!important;border-style:dashed!important;cursor:pointer;transition:background .2s;">
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="outBrowseBtn">
                                    <i class="bi bi-folder2-open me-1"></i>Browse
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" id="outCameraBtn">
                                    <i class="bi bi-camera me-1"></i>Camera
                                </button>
                            </div>
                            <div class="text-muted mt-1" style="font-size:.72rem;">
                                or drag &amp; drop images here &nbsp;·&nbsp; JPG/PNG/WEBP &nbsp;·&nbsp; max 5 MB each
                            </div>
                        </div>

                        <div id="outPhotoError" class="alert alert-danger py-1 small d-none mb-2"></div>
                        <div class="row g-1" id="outPhotoPreview"></div>
                    </div>

                    <div class="mt-3 d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-box-arrow-right me-2"></i>Record Gate Out
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Movements -->
    <div class="col-lg-6">
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2 text-primary"></i>Today's Gate Movements</span>
                <span class="badge bg-primary rounded-pill">{{ $recentMovements->count() }}</span>
            </div>
            <div class="card-body p-0" style="max-height:600px;overflow-y:auto;">
                <div class="list-group list-group-flush">
                    @forelse($recentMovements as $mv)
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-muted small" style="width:45px;">
                                {{ ($mv->gate_in_time ?? $mv->gate_out_time)?->format('H:i') }}
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="font-monospace fw-semibold small">{{ $mv->container_no }}</span>
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size:.65rem;">{{ $mv->size }}' {{ $mv->container_type }}</span>
                                    @if($mv->movement_type === 'in')
                                        <span class="badge bg-primary-subtle text-primary" style="font-size:.65rem;"><i class="bi bi-arrow-down-circle"></i> In</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success" style="font-size:.65rem;"><i class="bi bi-arrow-up-circle"></i> Out</span>
                                    @endif
                                </div>
                                <div class="text-muted" style="font-size:.72rem;">
                                    {{ $mv->customer?->name }} &nbsp;·&nbsp; {{ $mv->vehicle_plate }}
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-1">
                                <span class="badge rounded-pill {{ $mv->movement_status === 'done' ? 'bg-success' : 'bg-warning text-dark' }}" style="font-size:.65rem;">
                                    {{ ucfirst($mv->movement_status) }}
                                </span>
                                <a href="{{ route('yard.movements.edit', $mv) }}"
                                   class="btn btn-outline-secondary btn-sm py-0 px-1"
                                   style="font-size:.65rem;line-height:1.5;" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="list-group-item text-center text-muted small py-4">
                        No gate movements recorded yet.
                    </div>
                    @endforelse
                </div>
            </div>
            @php
                $inCount  = $recentMovements->where('movement_type', 'in')->count();
                $outCount = $recentMovements->where('movement_type', 'out')->count();
            @endphp
            <div class="card-footer bg-white">
                <div class="row text-center small">
                    <div class="col">
                        <div class="text-muted">Gate-In</div>
                        <strong class="text-primary">{{ $inCount }}</strong>
                    </div>
                    <div class="col border-start border-end">
                        <div class="text-muted">Gate-Out</div>
                        <strong class="text-success">{{ $outCount }}</strong>
                    </div>
                    <div class="col">
                        <div class="text-muted">Total</div>
                        <strong>{{ $recentMovements->count() }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
    const btnIn   = document.getElementById('btnGateIn');
    const btnOut  = document.getElementById('btnGateOut');
    const cardIn  = document.getElementById('gateInCard');
    const cardOut = document.getElementById('gateOutCard');

    btnIn.addEventListener('click', () => {
        cardIn.classList.remove('d-none');
        cardOut.classList.add('d-none');
        btnIn.classList.replace('btn-outline-primary','btn-primary');
        btnOut.classList.replace('btn-success','btn-outline-success');
    });

    btnOut.addEventListener('click', () => {
        cardOut.classList.remove('d-none');
        cardIn.classList.add('d-none');
        btnOut.classList.replace('btn-outline-success','btn-success');
        btnIn.classList.replace('btn-primary','btn-outline-primary');
    });

    // Container number: 4 letters then 7 digits, max 11 chars
    (function () {
        const inp = document.getElementById('containerNoIn');

        // Enforce positional rules on keydown (blocks wrong key before it appears)
        inp.addEventListener('keydown', function (e) {
            const ctrl = e.ctrlKey || e.metaKey;
            const nav  = ['Backspace','Delete','ArrowLeft','ArrowRight',
                          'Home','End','Tab','Enter'].includes(e.key);
            if (ctrl || nav) return;               // always allow control keys

            const pos = this.selectionStart;
            const sel = this.selectionEnd;
            const atEnd = pos === sel;             // no text selected

            // Block if already at 11 chars and nothing is selected
            if (this.value.length >= 11 && atEnd) { e.preventDefault(); return; }

            // Position 0-3 → letters only; position 4-10 → digits only
            if (pos < 4) {
                if (!/^[A-Za-z]$/.test(e.key)) { e.preventDefault(); return; }
            } else {
                if (!/^[0-9]$/.test(e.key)) { e.preventDefault(); return; }
            }
        });

        // Sanitise pasted / auto-filled values
        inp.addEventListener('input', function () {
            const cursor = this.selectionStart;
            const raw    = this.value.toUpperCase();
            let out = '', letters = 0, digits = 0;

            for (let i = 0; i < raw.length; i++) {
                if (letters < 4 && /[A-Z]/.test(raw[i]))        { out += raw[i]; letters++; }
                else if (letters === 4 && digits < 7 && /[0-9]/.test(raw[i])) { out += raw[i]; digits++; }
                if (out.length >= 11) break;
            }

            this.value = out;
            // Restore cursor position so it doesn't jump to end
            const newCursor = Math.min(cursor, out.length);
            this.setSelectionRange(newCursor, newCursor);
        });

        // Live hint: show how many letters / digits still needed
        const hint = document.createElement('div');
        hint.className = 'form-text text-muted mt-1';
        hint.id = 'containerNoHint';
        inp.closest('.input-group').insertAdjacentElement('afterend', hint);

        function updateHint() {
            const v = inp.value;
            const l = Math.min(v.length, 4);
            const d = Math.max(v.length - 4, 0);
            if (v.length === 0) {
                hint.textContent = '4 letters + 7 digits required';
            } else if (l < 4) {
                hint.textContent = (4 - l) + ' more letter' + (4 - l > 1 ? 's' : '') + ' needed, then 7 digits';
            } else if (d < 7) {
                hint.textContent = (7 - d) + ' more digit' + (7 - d > 1 ? 's' : '') + ' needed';
            } else {
                hint.textContent = '';
            }
        }

        inp.addEventListener('input', updateHint);
        inp.addEventListener('focus', updateHint);
        inp.addEventListener('blur',  function () { hint.textContent = ''; });
    }());

    // Equipment Type auto-fill
    (function () {
        const sel       = document.getElementById('gateEqtSelect');
        const sizeHid   = document.getElementById('gateEqtSize');
        const typeHid   = document.getElementById('gateEqtTypeCode');
        const sizeBadge = document.getElementById('gateEqtSizeBadge');
        const typeBadge = document.getElementById('gateEqtTypeBadge');

        function applyEqt(opt) {
            if (!opt || !opt.value) {
                sizeHid.value = '';
                typeHid.value = '';
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

    // ── Photo uploader factory (used for both Gate In and Gate Out) ──────────
    function initPhotoUploader(cfg) {
        // cfg: { fileInput, cameraInput, browseBtn, cameraBtn, dropZone,
        //        errorEl, previewGrid, counterEl, max }
        const MAX       = cfg.max || 5;
        const MAX_BYTES = 5 * 1024 * 1024;
        const ALLOWED   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        let dt = new DataTransfer();

        function updateCounter() {
            const n = dt.files.length;
            cfg.counterEl.textContent = n + ' / ' + MAX;
            cfg.counterEl.className = n >= MAX
                ? 'badge bg-warning-subtle text-warning ms-1'
                : 'badge bg-secondary-subtle text-secondary ms-1';
        }

        function showError(msg) {
            cfg.errorEl.textContent = msg;
            cfg.errorEl.classList.remove('d-none');
            setTimeout(() => cfg.errorEl.classList.add('d-none'), 4000);
        }

        function renderPreviews() {
            cfg.previewGrid.innerHTML = '';
            Array.from(dt.files).forEach((file, idx) => {
                const col = document.createElement('div');
                col.className = 'col-4 col-md-3';
                const reader = new FileReader();
                reader.onload = e => {
                    col.innerHTML =
                        '<div class="position-relative" style="border-radius:6px;overflow:hidden;">' +
                            '<img src="' + e.target.result + '" style="width:100%;height:70px;object-fit:cover;" alt="">' +
                            '<button type="button" class="btn btn-danger btn-sm rm-photo position-absolute" ' +
                                    'data-idx="' + idx + '" ' +
                                    'style="top:2px;right:2px;padding:1px 5px;font-size:.7rem;line-height:1.2;border-radius:50%;">' +
                                '<i class="bi bi-x"></i>' +
                            '</button>' +
                        '</div>';
                    cfg.previewGrid.appendChild(col);
                };
                reader.readAsDataURL(file);
            });
            updateCounter();
        }

        function addFiles(files) {
            Array.from(files).forEach(file => {
                if (!ALLOWED.includes(file.type)) { showError('"' + file.name + '" is not a supported image type.'); return; }
                if (file.size > MAX_BYTES)         { showError('"' + file.name + '" exceeds 5 MB.'); return; }
                if (dt.files.length >= MAX)        { showError('Maximum ' + MAX + ' photos allowed.'); return; }
                const dup = Array.from(dt.files).some(f => f.name === file.name && f.size === file.size);
                if (!dup) dt.items.add(file);
            });
            cfg.fileInput.files = dt.files;
            renderPreviews();
        }

        cfg.previewGrid.addEventListener('click', function (e) {
            const btn = e.target.closest('.rm-photo');
            if (!btn) return;
            const idx = parseInt(btn.dataset.idx, 10);
            const nd = new DataTransfer();
            Array.from(dt.files).forEach((f, i) => { if (i !== idx) nd.items.add(f); });
            dt = nd;
            cfg.fileInput.files = dt.files;
            renderPreviews();
        });

        // Browse button
        cfg.browseBtn.addEventListener('click', e => { e.stopPropagation(); cfg.fileInput.click(); });
        cfg.dropZone.addEventListener('click',  () => cfg.fileInput.click());

        // Camera button — opens device camera directly
        cfg.cameraBtn.addEventListener('click', e => { e.stopPropagation(); cfg.cameraInput.click(); });

        // File input change
        cfg.fileInput.addEventListener('change', function () { addFiles(this.files); this.value = ''; });

        // Camera input change — single capture, add to accumulator
        cfg.cameraInput.addEventListener('change', function () { addFiles(this.files); this.value = ''; });

        // Inject accumulated files into the outgoing request.
        // The `formdata` event fires during form serialisation (Chrome 77+,
        // Edge 79+, Firefox 72+), giving direct access to the FormData that
        // the browser is about to send — more reliable than input.files= which
        // gets wiped by value='' on Windows Chrome/Edge.
        const form = cfg.fileInput.closest('form');
        form.addEventListener('formdata', function (e) {
            Array.from(dt.files).forEach(function (file) {
                e.formData.append('photos[]', file);
            });
        });

        // Drag & drop
        cfg.dropZone.addEventListener('dragover',  e => { e.preventDefault(); cfg.dropZone.style.background = '#e8f0fe'; });
        cfg.dropZone.addEventListener('dragleave', () => { cfg.dropZone.style.background = ''; });
        cfg.dropZone.addEventListener('drop',      e => {
            e.preventDefault();
            cfg.dropZone.style.background = '';
            addFiles(e.dataTransfer.files);
        });
    }

    // Initialise for Gate In
    initPhotoUploader({
        fileInput:   document.getElementById('inPhotoInput'),
        cameraInput: document.getElementById('inCameraInput'),
        browseBtn:   document.getElementById('inBrowseBtn'),
        cameraBtn:   document.getElementById('inCameraBtn'),
        dropZone:    document.getElementById('inDropZone'),
        errorEl:     document.getElementById('inPhotoError'),
        previewGrid: document.getElementById('inPhotoPreview'),
        counterEl:   document.getElementById('inPhotoCounter'),
        max: 5,
    });

    // Initialise for Gate Out
    initPhotoUploader({
        fileInput:   document.getElementById('outPhotoInput'),
        cameraInput: document.getElementById('outCameraInput'),
        browseBtn:   document.getElementById('outBrowseBtn'),
        cameraBtn:   document.getElementById('outCameraBtn'),
        dropZone:    document.getElementById('outDropZone'),
        errorEl:     document.getElementById('outPhotoError'),
        previewGrid: document.getElementById('outPhotoPreview'),
        counterEl:   document.getElementById('outPhotoCounter'),
        max: 5,
    });
</script>
@endpush
